import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { Role } from '@/types/auth'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
    roles?: Role[]
    /** Last breadcrumb segment. */
    title?: string
    /** Page fills the content area: no padding, fixed viewport height (the map). */
    fullBleed?: boolean
  }
}

const DashboardPage = () => import('@/views/DashboardPage.vue')
const AnalyticsPage = () => import('@/views/AnalyticsPage.vue')
const ApprovalsPage = () => import('@/views/ApprovalsPage.vue')
const MapPage = () => import('@/views/MapPage.vue')
const RecommendationsPage = () => import('@/views/RecommendationsPage.vue')
const MonitoringPage = () => import('@/views/MonitoringPage.vue')
const EvaluationsPage = () => import('@/views/EvaluationsPage.vue')

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginPage.vue'),
      meta: { guestOnly: true, title: 'Sign in' },
    },
    {
      // Opened from the set-password / reset email: ?token=…&email=…
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/views/ResetPasswordPage.vue'),
      meta: { guestOnly: true, title: 'Set password' },
    },
    {
      path: '/register/student',
      name: 'register-student',
      component: () => import('@/views/StudentRegisterPage.vue'),
      meta: { guestOnly: true, title: 'Register as a student' },
    },
    {
      path: '/register/partner',
      name: 'register-partner',
      component: () => import('@/views/PartnerRegisterPage.vue'),
      meta: { guestOnly: true, title: 'Register your company' },
    },
    {
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: 'admin',
          name: 'admin',
          component: DashboardPage,
          meta: { roles: ['admin'], title: 'Dashboard' },
        },
        {
          path: 'admin/accounts',
          name: 'admin-accounts',
          component: () => import('@/views/AccountsPage.vue'),
          meta: { roles: ['admin'], title: 'User accounts' },
        },
        {
          path: 'admin/approvals',
          name: 'admin-approvals',
          component: ApprovalsPage,
          meta: { roles: ['admin'], title: 'Approvals' },
        },
        {
          path: 'coordinator',
          name: 'coordinator',
          component: DashboardPage,
          meta: { roles: ['coordinator'], title: 'Dashboard' },
        },
        {
          path: 'coordinator/approvals',
          name: 'coordinator-approvals',
          component: ApprovalsPage,
          meta: { roles: ['coordinator'], title: 'Student approvals' },
        },
        {
          path: 'partner',
          name: 'partner',
          component: DashboardPage,
          meta: { roles: ['partner'], title: 'Dashboard' },
        },
        {
          path: 'student',
          name: 'student',
          component: DashboardPage,
          meta: { roles: ['student'], title: 'Dashboard' },
        },
        {
          path: 'admin/map',
          name: 'admin-map',
          component: MapPage,
          meta: { roles: ['admin'], title: 'GIS map', fullBleed: true },
        },
        {
          path: 'coordinator/map',
          name: 'coordinator-map',
          component: MapPage,
          meta: { roles: ['coordinator'], title: 'GIS map', fullBleed: true },
        },
        {
          path: 'partner/map',
          name: 'partner-map',
          component: MapPage,
          meta: { roles: ['partner'], title: 'Our site on map', fullBleed: true },
        },
        {
          path: 'student/map',
          name: 'student-map',
          component: MapPage,
          meta: { roles: ['student'], title: 'Find a partner', fullBleed: true },
        },
        {
          path: 'admin/recommendations',
          name: 'admin-recommendations',
          component: RecommendationsPage,
          meta: { roles: ['admin'], title: 'Placement recommendations' },
        },
        {
          path: 'coordinator/recommendations',
          name: 'coordinator-recommendations',
          component: RecommendationsPage,
          meta: { roles: ['coordinator'], title: 'Placement recommendations' },
        },
        {
          path: 'admin/skills',
          name: 'admin-skills',
          component: () => import('@/views/SkillsAdminPage.vue'),
          meta: { roles: ['admin'], title: 'Skills & matching' },
        },
        {
          path: 'partner/skills',
          name: 'partner-skills',
          component: () => import('@/views/PartnerSkillsPage.vue'),
          meta: { roles: ['partner'], title: 'Skills we need' },
        },
        {
          path: 'student/skills',
          name: 'student-skills',
          component: () => import('@/views/StudentSkillsPage.vue'),
          meta: { roles: ['student'], title: 'My skills' },
        },
        {
          path: 'student/deployment',
          name: 'student-deployment',
          component: () => import('@/views/StudentDeploymentPage.vue'),
          meta: { roles: ['student'], title: 'My deployment' },
        },
        {
          path: 'partner/interns',
          name: 'partner-interns',
          component: () => import('@/views/PartnerInternsPage.vue'),
          meta: { roles: ['partner'], title: 'Our interns' },
        },
        {
          path: 'coordinator/monitoring',
          name: 'coordinator-monitoring',
          component: MonitoringPage,
          meta: { roles: ['coordinator'], title: 'Intern monitoring' },
        },
        {
          path: 'admin/monitoring',
          name: 'admin-monitoring',
          component: MonitoringPage,
          meta: { roles: ['admin'], title: 'All deployments' },
        },
        {
          path: 'partner/evaluations',
          name: 'partner-evaluations',
          component: () => import('@/views/PartnerEvaluationsPage.vue'),
          meta: { roles: ['partner'], title: 'Evaluate interns' },
        },
        {
          path: 'coordinator/evaluations',
          name: 'coordinator-evaluations',
          component: EvaluationsPage,
          meta: { roles: ['coordinator'], title: 'Evaluations' },
        },
        {
          path: 'admin/evaluations',
          name: 'admin-evaluations',
          component: EvaluationsPage,
          meta: { roles: ['admin'], title: 'Evaluations' },
        },
        {
          path: 'admin/analytics',
          name: 'admin-analytics',
          component: AnalyticsPage,
          meta: { roles: ['admin'], title: 'Analytics' },
        },
        {
          path: 'coordinator/analytics',
          name: 'coordinator-analytics',
          component: AnalyticsPage,
          meta: { roles: ['coordinator'], title: 'Analytics' },
        },
        {
          // Every role; reached from the bell panel (not in the side nav).
          path: 'notifications',
          name: 'notifications',
          component: () => import('@/views/NotificationsPage.vue'),
          meta: { title: 'Notifications' },
        },
        {
          path: 'student/evaluation',
          name: 'student-evaluation',
          component: () => import('@/views/StudentEvaluationPage.vue'),
          meta: { roles: ['student'], title: 'My evaluation' },
        },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.init()

  if (to.path === '/') {
    return auth.isAuthenticated ? auth.homeRoute : { name: 'login' }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return auth.homeRoute
  }

  if (to.matched.some((record) => record.meta.requiresAuth) && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.roles && auth.role && !to.meta.roles.includes(auth.role)) {
    return auth.homeRoute
  }
})

export default router
