import { defineStore } from 'pinia'
import * as mapApi from '@/api/map'
import * as programsApi from '@/api/programs'
import { errorMessage } from '@/lib/apiErrors'
import type { Program } from '@/types/accounts'
import type { Role } from '@/types/auth'
import type {
  LatLng,
  MapConfig,
  MapFilters,
  MapSummary,
  NearbyPartner,
  PartnerFeature,
  Saturation,
  UnlocatedPartner,
} from '@/types/map'
import { SATURATIONS } from '@/types/map'

/** Drops responses from superseded feature requests (fast filter clicks). */
let latestFeatures = 0

export const useMapStore = defineStore('map', {
  state: () => ({
    role: null as Role | null,
    config: null as MapConfig | null,
    programs: [] as Program[],

    features: [] as PartnerFeature[],
    summary: null as MapSummary | null,
    unlocated: [] as UnlocatedPartner[],
    nearby: [] as NearbyPartner[],
    homeMissing: false,
    home: null as LatLng | null,

    filters: { programId: null, province: '', saturation: [...SATURATIONS] } as MapFilters,
    showCoverage: false,
    radiusKm: 25,

    loading: false,
    error: null as string | null,

    // Editing pins
    /** Admin "Edit pins" / partner "Fix my pin": markers become draggable. */
    editMode: false,
    /** Waiting for a click on the map to place this partner. */
    placing: null as { id: number; company: string } | null,
    /** Where the placed (still draggable) marker is. */
    draft: null as LatLng | null,
    /** A dragged marker waiting for Save / Undo. */
    pendingMove: null as { id: number; company: string; lat: number; lng: number } | null,
    saving: false,
    saveError: null as string | null,
  }),

  getters: {
    /** Located partners, most interns first (the panel's keyboard-friendly list). */
    partnerList: (state): PartnerFeature[] =>
      state.features
        .filter((f) => f.geometry !== null)
        .slice()
        .sort(
          (a, b) =>
            b.properties.filled - a.properties.filled ||
            a.properties.company_name.localeCompare(b.properties.company_name),
        ),
  },

  actions: {
    /** Load everything the page needs for this role. Also used by "Retry". */
    async load(role: Role, defaultProgramId: number | null = null): Promise<void> {
      this.role = role
      this.loading = true
      this.error = null

      try {
        if (!this.config) {
          this.config = await mapApi.getConfig()
          this.radiusKm = this.config.coverage_radius_km
        }

        if (role === 'coordinator' && this.filters.programId === null) {
          this.filters.programId = defaultProgramId
        }

        const tasks: Promise<unknown>[] = [this.fetchFeatures(true)]
        if (role === 'admin' || role === 'coordinator') {
          tasks.push(this.fetchSummary(true), this.loadPrograms())
        }
        if (role === 'admin') tasks.push(this.fetchUnlocated(true))
        if (role === 'student') tasks.push(this.fetchNearby(true))
        await Promise.all(tasks)
      } catch (e) {
        this.error = errorMessage(e, 'Could not load the map data.')
      } finally {
        this.loading = false
      }
    },

    /** `rethrow` lets load() report the first failure as a page error. */
    async fetchFeatures(rethrow = false): Promise<void> {
      const request = ++latestFeatures

      if (this.filters.saturation.length === 0) {
        this.features = [] // nothing ticked in the legend
        return
      }

      try {
        const collection = await mapApi.getPartners(this.filters)
        if (request === latestFeatures) this.features = collection.features
      } catch (e) {
        if (rethrow) throw e
        this.error = errorMessage(e, 'Could not load partners.')
      }
    },

    async fetchSummary(rethrow = false): Promise<void> {
      try {
        this.summary = await mapApi.getSummary(this.radiusKm)
      } catch (e) {
        if (rethrow) throw e
      }
    },

    async fetchUnlocated(rethrow = false): Promise<void> {
      try {
        this.unlocated = await mapApi.getUnlocated()
      } catch (e) {
        if (rethrow) throw e
      }
    },

    async fetchNearby(rethrow = false): Promise<void> {
      try {
        const result = await mapApi.getNearby()
        this.nearby = result.partners
        this.homeMissing = result.homeMissing
        this.home = result.home
      } catch (e) {
        if (rethrow) throw e
      }
    },

    async loadPrograms(): Promise<void> {
      if (this.programs.length === 0) this.programs = await programsApi.list()
    },

    // Filters

    setProgram(programId: number | null): Promise<void> {
      this.filters.programId = programId
      return this.fetchFeatures()
    },

    setProvince(province: MapFilters['province']): Promise<void> {
      this.filters.province = province
      return this.fetchFeatures()
    },

    toggleSaturation(saturation: Saturation): Promise<void> {
      const set = new Set(this.filters.saturation)
      if (set.has(saturation)) set.delete(saturation)
      else set.add(saturation)
      this.filters.saturation = SATURATIONS.filter((s) => set.has(s))
      return this.fetchFeatures()
    },

    setRadius(radiusKm: number): Promise<void> {
      this.radiusKm = radiusKm
      return this.fetchSummary()
    },

    // Editing pins

    startPlacing(id: number, company: string): void {
      this.cancelMove()
      this.placing = { id, company }
      this.draft = null
      this.saveError = null
    },

    placeDraft(at: LatLng): void {
      if (this.placing) this.draft = at
    },

    cancelPlacing(): void {
      this.placing = null
      this.draft = null
      this.saveError = null
    },

    proposeMove(id: number, company: string, at: LatLng): void {
      this.pendingMove = { id, company, lat: at.lat, lng: at.lng }
      this.saveError = null
    },

    cancelMove(): void {
      this.pendingMove = null
      this.saveError = null
    },

    async saveLocation(id: number, at: LatLng): Promise<boolean> {
      this.saving = true
      this.saveError = null
      try {
        const feature = await mapApi.updateLocation(id, at.lat, at.lng)
        const index = this.features.findIndex((f) => f.id === feature.id)
        if (index >= 0) this.features.splice(index, 1, feature)
        else this.features.push(feature)

        this.placing = null
        this.draft = null
        this.pendingMove = null

        // A new location changes the "needs location" list and coverage.
        if (this.role === 'admin') void this.fetchUnlocated()
        if (this.role === 'admin' || this.role === 'coordinator') void this.fetchSummary()
        return true
      } catch (e) {
        this.saveError = errorMessage(e, 'Could not save the location.')
        return false
      } finally {
        this.saving = false
      }
    },
  },
})
