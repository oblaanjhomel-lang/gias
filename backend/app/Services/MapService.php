<?php

namespace App\Services;

use App\Enums\DeploymentStatus;
use App\Enums\GeocodeStatus;
use App\Enums\Province;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\IndustryPartner;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Everything spatial for the GIS map. PostGIS functions run through bound raw SQL;
 * user-supplied values are never concatenated into SQL.
 *
 * filled = a partner's ACTIVE deployments; open = max(intern_slots − filled, 0).
 */
class MapService
{
    /** Correlated subquery: active deployments of the partner row `industry_partners`. Binds the status. */
    private const FILLED_SQL = 'select count(*) from deployments d where d.industry_partner_id = industry_partners.id and d.status = ?';

    /**
     * @return array{campus: array{name: string, lat: float, lng: float}, region_bounds: array<string, float>, coverage_radius_km: int}
     */
    public function config(): array
    {
        return [
            'campus' => config('gias.campus'),
            'region_bounds' => config('gias.region_bounds'),
            'coverage_radius_km' => (int) config('gias.coverage_radius_km'),
        ];
    }

    /**
     * GeoJSON FeatureCollection of Industry Partners for the viewer's role:
     *  - admin: active + pending, with contact fields
     *  - coordinator: active, with contact fields
     *  - student: active with open slots; no contact fields, no interns_by_program
     *  - partner: only their own feature (geometry null if not located yet)
     *
     * @param  array{program_id?: int|null, province?: string|null, saturation?: list<string>|null}  $filters
     * @return array{type: 'FeatureCollection', features: list<array<string, mixed>>}
     */
    public function partnersGeoJson(User $viewer, array $filters): array
    {
        $isPartner = $viewer->role === UserRole::Partner;
        $statuses = $viewer->role === UserRole::Admin
            ? [UserStatus::Active, UserStatus::Pending]
            : [UserStatus::Active];

        $partners = $this->partnersQuery()
            ->whereHas('user', fn (Builder $q) => $q
                ->where('role', UserRole::Partner)
                ->when(
                    $isPartner,
                    fn (Builder $q) => $q->whereKey($viewer->id),
                    fn (Builder $q) => $q->whereIn('status', $statuses),
                ))
            ->when(! $isPartner, fn (Builder $q) => $q->whereNotNull('location'))
            ->when($filters['province'] ?? null, fn (Builder $q, string $province) => $q->where('province', $province))
            ->when($filters['program_id'] ?? null, fn (Builder $q, int|string $programId) => $q->whereHas(
                'activeDeployments',
                fn (Builder $d) => $d->whereHas('student.user', fn (Builder $u) => $u->where('program_id', (int) $programId)),
            ))
            ->orderBy('company_name')
            ->get();

        $internsByProgram = $viewer->role === UserRole::Student
            ? collect()
            : $this->internsByProgram($partners->modelKeys());

        $saturation = array_values(array_filter((array) ($filters['saturation'] ?? [])));

        $features = $partners
            ->map(fn (IndustryPartner $p) => $this->feature($p, $viewer, $internsByProgram->get($p->id, [])))
            ->filter(fn (array $f) => $saturation === [] || in_array($f['properties']['saturation'], $saturation, true))
            ->filter(fn (array $f) => $viewer->role !== UserRole::Student || $f['properties']['open'] > 0)
            ->values()
            ->all();

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * Active and pending partners whose address could not be located (admin).
     *
     * @return Collection<int, IndustryPartner>
     */
    public function unlocated(): Collection
    {
        return IndustryPartner::query()
            ->with('user')
            ->whereNull('location')
            ->whereHas('user', fn (Builder $q) => $q
                ->where('role', UserRole::Partner)
                ->whereIn('status', [UserStatus::Active, UserStatus::Pending]))
            ->orderBy('company_name')
            ->get();
    }

    /**
     * Admin: any partner. Partner: only their own pin. Coordinates are validated against
     * config('gias.region_bounds') by UpdatePartnerLocationRequest. Returns the updated feature.
     *
     * @return array<string, mixed>
     */
    public function updatePartnerLocation(User $actor, User $partnerUser, float $lat, float $lng): array
    {
        $allowed = $actor->role === UserRole::Admin
            || ($actor->role === UserRole::Partner && $actor->is($partnerUser));

        if (! $allowed) {
            throw new AccessDeniedHttpException('You can only move your own pin.');
        }

        $profile = $partnerUser->industryPartner()->firstOrFail();
        $profile->location = Point::makeGeodetic($lat, $lng);
        $profile->geocode_status = GeocodeStatus::Manual;
        $profile->save();

        $partner = $this->partnersQuery()->findOrFail($profile->id);

        return $this->feature($partner, $actor, $this->internsByProgram([$partner->id])->get($partner->id, []));
    }

    /**
     * Active partners with open slots, nearest to the student's home first.
     *
     * @return array{partners: Collection<int, IndustryPartner>, home_missing: bool, home: array{latitude: float, longitude: float}|null}
     */
    public function nearbyForStudent(User $student, int $limit = 10): array
    {
        $profile = $student->student;

        if ($profile?->home_location === null) {
            return ['partners' => new Collection, 'home_missing' => true, 'home' => null];
        }

        $partners = IndustryPartner::query()
            ->with('user')
            ->select('industry_partners.*')
            ->selectRaw(
                'ST_Distance(industry_partners.location::geography, '
                .'(select s.home_location from students s where s.id = ?)::geography) / 1000.0 as distance_km',
                [$profile->id],
            )
            ->withCount(['deployments as filled' => fn (Builder $q) => $q->where('status', DeploymentStatus::Active)])
            ->whereNotNull('location')
            ->whereHas('user', fn (Builder $q) => $q->where('role', UserRole::Partner)->where('status', UserStatus::Active))
            ->whereRaw('industry_partners.intern_slots > ('.self::FILLED_SQL.')', [DeploymentStatus::Active->value])
            ->orderBy('distance_km')
            ->limit($limit)
            ->get();

        return [
            'partners' => $partners,
            'home_missing' => false,
            'home' => [
                'latitude' => $profile->home_location->getLatitude(),
                'longitude' => $profile->home_location->getLongitude(),
            ],
        ];
    }

    /**
     * Headline numbers, a per-province breakdown (all six, zeros included) and coverage.
     * Partner/slot figures cover all active partners; intern and coverage figures are scoped
     * to the viewer's students (admin: all; coordinator: own program).
     *
     * @return array<string, mixed>
     */
    public function summary(User $viewer, int $radiusKm): array
    {
        $partners = $this->partnersQuery()
            ->whereHas('user', fn (Builder $q) => $q->where('role', UserRole::Partner)->where('status', UserStatus::Active))
            ->get();

        $internsQuery = DB::table('deployments as d')
            ->join('industry_partners as ip', 'ip.id', '=', 'd.industry_partner_id')
            ->join('students as s', 's.id', '=', 'd.student_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('d.status', DeploymentStatus::Active->value);
        $this->limitToViewersStudents($internsQuery, $viewer);

        $internsByProvince = $internsQuery
            ->groupBy('ip.province')
            ->selectRaw('ip.province, count(*) as interns')
            ->pluck('interns', 'province');

        $provinces = collect(Province::cases())->map(function (Province $province) use ($partners, $internsByProvince) {
            $inProvince = $partners->filter(fn (IndustryPartner $p) => $p->province === $province);

            return [
                'province' => $province->value,
                'partners' => $inProvince->count(),
                'interns' => (int) ($internsByProvince[$province->value] ?? 0),
                'open_slots' => (int) $inProvince->sum(fn (IndustryPartner $p) => $this->openSlots($p)),
            ];
        });

        return [
            'partners' => $partners->count(),
            'interns_deployed' => (int) $internsByProvince->sum(),
            'open_slots' => (int) $provinces->sum('open_slots'),
            'provinces' => $provinces->values()->all(),
            'coverage' => $this->coverage($viewer, $radiusKm),
        ];
    }

    /**
     * Share of in-scope, active, UNPLACED students whose home is within $radiusKm of at
     * least one active partner with open slots (ST_DWithin on geography, in metres).
     *
     * @return array{radius_km: int, students_unplaced: int, within_radius: int, percent: float|null}
     */
    private function coverage(User $viewer, int $radiusKm): array
    {
        $unplaced = DB::table('students as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('u.role', UserRole::Student->value)
            ->where('u.status', UserStatus::Active->value)
            ->whereNotExists(fn (QueryBuilder $q) => $q->selectRaw('1')
                ->from('deployments as d')
                ->whereColumn('d.student_id', 's.id')
                ->where('d.status', DeploymentStatus::Active->value));
        $this->limitToViewersStudents($unplaced, $viewer);

        $total = (clone $unplaced)->count();

        $within = (clone $unplaced)
            ->whereNotNull('s.home_location')
            ->whereExists(fn (QueryBuilder $q) => $q->selectRaw('1')
                ->from('industry_partners')
                ->join('users as pu', 'pu.id', '=', 'industry_partners.user_id')
                ->where('pu.role', UserRole::Partner->value)
                ->where('pu.status', UserStatus::Active->value)
                ->whereNotNull('industry_partners.location')
                ->whereRaw('industry_partners.intern_slots > ('.self::FILLED_SQL.')', [DeploymentStatus::Active->value])
                ->whereRaw(
                    'ST_DWithin(s.home_location::geography, industry_partners.location::geography, ?)',
                    [$radiusKm * 1000],
                ))
            ->count();

        return [
            'radius_km' => $radiusKm,
            'students_unplaced' => $total,
            'within_radius' => $within,
            'percent' => $total > 0 ? round($within / $total * 100, 1) : null,
        ];
    }

    /**
     * @return Builder<IndustryPartner>
     */
    private function partnersQuery(): Builder
    {
        return IndustryPartner::query()
            ->with('user')
            ->withCount(['deployments as filled' => fn (Builder $q) => $q->where('status', DeploymentStatus::Active)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function feature(IndustryPartner $partner, User $viewer, array $internsByProgram): array
    {
        $filled = (int) $partner->filled;
        $open = $this->openSlots($partner);
        $status = $partner->user->status;

        $saturation = match (true) {
            $status === UserStatus::Pending => 'pending',
            $open >= 3 => 'open',
            $open >= 1 => 'near',
            default => 'full',
        };

        $properties = [
            'id' => $partner->user_id,
            'company_name' => $partner->company_name,
            'city' => $partner->city,
            'province' => $partner->province->value,
            'status' => $status->value,
            'intern_slots' => $partner->intern_slots,
            'filled' => $filled,
            'open' => $open,
            'saturation' => $saturation,
            'geocode_status' => $partner->geocode_status->value,
        ];

        if ($viewer->role !== UserRole::Student) {
            $properties['interns_by_program'] = $internsByProgram;
        }

        $seesContacts = in_array($viewer->role, [UserRole::Admin, UserRole::Coordinator], true)
            || ($viewer->role === UserRole::Partner && $viewer->id === $partner->user_id);

        if ($seesContacts) {
            $properties += [
                'contact_person' => $partner->contact_person,
                'contact_number' => $partner->contact_number,
                'email' => $partner->user->email,
                'address' => $partner->address,
            ];
        }

        return [
            'type' => 'Feature',
            'id' => $partner->user_id,
            'geometry' => $partner->location ? [
                'type' => 'Point',
                'coordinates' => [
                    round($partner->location->getLongitude(), 6),
                    round($partner->location->getLatitude(), 6),
                ],
            ] : null,
            'properties' => $properties,
        ];
    }

    private function openSlots(IndustryPartner $partner): int
    {
        return max($partner->intern_slots - (int) $partner->filled, 0);
    }

    /**
     * Active interns per program for each partner: [partner_id => [{code, count}, …]].
     *
     * @param  list<int>  $partnerIds
     * @return SupportCollection<int, list<array{code: string, count: int}>>
     */
    private function internsByProgram(array $partnerIds): SupportCollection
    {
        if ($partnerIds === []) {
            return collect();
        }

        return DB::table('deployments as d')
            ->join('students as s', 's.id', '=', 'd.student_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('programs as p', 'p.id', '=', 'u.program_id')
            ->where('d.status', DeploymentStatus::Active->value)
            ->whereIn('d.industry_partner_id', $partnerIds)
            ->groupBy('d.industry_partner_id', 'p.code')
            ->select('d.industry_partner_id', 'p.code', DB::raw('count(*) as count'))
            ->orderByDesc('count')
            ->orderBy('p.code')
            ->get()
            ->groupBy('industry_partner_id')
            ->map(fn (SupportCollection $rows) => $rows
                ->map(fn (object $row) => ['code' => $row->code, 'count' => (int) $row->count])
                ->values()
                ->all());
    }

    /**
     * Admin: every student. Coordinator (or anyone else): students of their own program only.
     * Expects the students' users table aliased as `u`.
     */
    private function limitToViewersStudents(QueryBuilder $query, User $viewer): void
    {
        if ($viewer->role === UserRole::Admin) {
            return;
        }

        $viewer->program_id !== null
            ? $query->where('u.program_id', $viewer->program_id)
            : $query->whereRaw('false');
    }
}
