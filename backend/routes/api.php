<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CoordinatorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\EvaluationExportController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\IndustryPartnerController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PartnerEvaluationController;
use App\Http\Controllers\PartnerInternController;
use App\Http\Controllers\PartnerReportController;
use App\Http\Controllers\PartnerSkillController;
use App\Http\Controllers\PlacementController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\RecommendationExportController;
use App\Http\Controllers\RecommendationSettingsController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\StudentDeploymentController;
use App\Http\Controllers\StudentEvaluationController;
use App\Http\Controllers\StudentReportController;
use App\Http\Controllers\StudentSkillController;
use App\Http\Controllers\TermController;
use App\Models\Evaluation;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/health', HealthController::class);
Route::get('/programs', ProgramController::class); // the registration form needs it

// Throttles: the third argument is a key prefix so each limit has its own counter.
// Laravel keys guest throttles by IP only, so without it every `throttle:X,1` route
// would share one counter (e.g. failed registrations would use up login attempts).

// Auth — Sanctum SPA cookie session
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:5,1,login']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware(['guest', 'throttle:5,1,reset-password']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Self-registration (accounts start as pending)
Route::middleware(['guest', 'throttle:3,1,register'])->prefix('register')->group(function () {
    Route::post('/student', [RegistrationController::class, 'student']);
    Route::post('/partner', [RegistrationController::class, 'partner']);
});

// Approvals. {student} / {partner} resolve only users with that role (404 otherwise).
Route::middleware('auth:sanctum')->prefix('approvals')->controller(ApprovalController::class)->group(function () {
    Route::middleware('role:admin,coordinator')->group(function () {
        Route::get('/counts', 'counts');
        Route::get('/students', 'students');
        // Coordinators: own program only (UserPolicy::reviewStudent → 403 otherwise).
        Route::post('/students/{student}/approve', 'approveStudent')->can('reviewStudent', 'student');
        Route::post('/students/{student}/return', 'returnStudent')->can('reviewStudent', 'student');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/partners', 'partners');
        Route::post('/partners/{partner}/approve', 'approvePartner');
        Route::post('/partners/{partner}/return', 'returnPartner');
        Route::get('/partners/{partner}/documents/{document}', 'document')->whereNumber('document');
    });
});

// GIS map
Route::middleware('auth:sanctum')->prefix('map')->controller(MapController::class)->group(function () {
    Route::get('/config', 'config');
    Route::get('/partners', 'partners')->middleware('role:admin,coordinator,student,partner');
    Route::get('/unlocated', 'unlocated')->middleware('role:admin');
    // Partners may only move their own pin (checked in MapService).
    Route::patch('/partners/{partner}/location', 'updateLocation')->middleware('role:admin,partner');
    Route::get('/nearby', 'nearby')->middleware('role:student');
    Route::get('/summary', 'summary')->middleware('role:admin,coordinator');
});

// Skills, recommendations (advisory) and placements
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/skills', [SkillController::class, 'index']);

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::post('/skills', [SkillController::class, 'store']);
        Route::put('/skills/{skill}', [SkillController::class, 'update'])->whereNumber('skill');
        Route::delete('/skills/{skill}', [SkillController::class, 'destroy'])->whereNumber('skill');
        Route::get('/recommendation-settings', [RecommendationSettingsController::class, 'show']);
        Route::put('/recommendation-settings', [RecommendationSettingsController::class, 'update']);
        Route::get('/recommendations/export', RecommendationExportController::class);
    });

    Route::middleware('role:partner')->prefix('partner')->group(function () {
        Route::get('/skills', [PartnerSkillController::class, 'show']);
        Route::put('/skills', [PartnerSkillController::class, 'update']);
        Route::get('/matching-students', [RecommendationController::class, 'forPartner']);
    });

    Route::middleware('role:student')->prefix('student')->group(function () {
        Route::get('/skills', [StudentSkillController::class, 'show']);
        Route::put('/skills', [StudentSkillController::class, 'update']);
        Route::get('/recommendations', [RecommendationController::class, 'forStudent']);
    });

    // {student} = student user id; coordinators only for their own program (UserPolicy::manageStudent).
    Route::middleware('role:admin,coordinator')->group(function () {
        Route::get('/recommendations/students', [RecommendationController::class, 'students']);
        Route::get('/recommendations/students/{student}', [RecommendationController::class, 'show'])
            ->can('manageStudent', 'student');
        Route::post('/recommendations/draft', [RecommendationController::class, 'draft']);
        Route::post('/recommendations/students/{student}/feedback', [RecommendationController::class, 'feedback'])
            ->can('manageStudent', 'student');

        Route::post('/placements', [PlacementController::class, 'store']);
        Route::post('/placements/batch', [PlacementController::class, 'batch']);
        Route::patch('/placements/{deployment}/withdraw', [PlacementController::class, 'withdraw'])
            ->whereNumber('deployment');
    });
});

// Deployment monitoring: weekly reports, hours, countersigning, completion
Route::middleware('auth:sanctum')->group(function () {
    // Student: own deployment and reports ({report}: own only, WeeklyReportPolicy::manage)
    Route::middleware('role:student')->prefix('student')->group(function () {
        Route::get('/deployment', [StudentDeploymentController::class, 'show']);
        Route::post('/reports', [StudentReportController::class, 'store']);
        Route::put('/reports/{report}', [StudentReportController::class, 'update'])->whereNumber('report')->can('manage', 'report');
        Route::post('/reports/{report}/submit', [StudentReportController::class, 'submit'])->whereNumber('report')->can('manage', 'report');
        Route::delete('/reports/{report}', [StudentReportController::class, 'destroy'])->whereNumber('report')->can('manage', 'report');
    });

    // Industry partner: interns at their site ({report}: own site only, WeeklyReportPolicy::review)
    Route::middleware('role:partner')->prefix('partner')->group(function () {
        Route::get('/interns', [PartnerInternController::class, 'index']);
        Route::get('/reports', [PartnerReportController::class, 'index']);
        Route::get('/reports/{report}', [PartnerReportController::class, 'show'])->whereNumber('report')->can('review', 'report');
        Route::post('/reports/{report}/sign', [PartnerReportController::class, 'sign'])->whereNumber('report')->can('review', 'report');
        Route::post('/reports/{report}/return', [PartnerReportController::class, 'return'])->whereNumber('report')->can('review', 'report');
    });

    // Coordinator (own program) / admin (all). Withdraw: PATCH /api/placements/{deployment}/withdraw.
    Route::middleware('role:admin,coordinator')->prefix('monitoring')->group(function () {
        Route::get('/summary', [MonitoringController::class, 'summary']);
        Route::get('/deployments', [MonitoringController::class, 'index']);
        Route::get('/deployments/{deployment}', [MonitoringController::class, 'show'])
            ->whereNumber('deployment')->can('view', 'deployment');
        Route::post('/deployments/{deployment}/complete', [MonitoringController::class, 'complete'])
            ->whereNumber('deployment')->can('complete', 'deployment');
    });

    Route::put('/admin/programs/{program}/required-hours', [ProgramController::class, 'updateRequiredHours'])
        ->middleware('role:admin')->whereNumber('program');
});

// Evaluations: partner rates, coordinator reviews (70/30), student sees reviewed results
Route::middleware('auth:sanctum')->group(function () {
    // Partner: interns at their own site ({deployment}: EvaluationPolicy::partnerEvaluate)
    Route::middleware('role:partner')->prefix('partner/evaluations')->controller(PartnerEvaluationController::class)->group(function () {
        $own = fn ($route) => $route->whereNumber('deployment')->whereIn('type', ['midterm', 'final'])
            ->can('partnerEvaluate', [Evaluation::class, 'deployment']);

        Route::get('/', 'index');
        $own(Route::get('/{deployment}/{type}', 'show'));
        $own(Route::put('/{deployment}/{type}', 'update'));
        $own(Route::post('/{deployment}/{type}/submit', 'submit'));
    });

    // Coordinator (own program) / admin ({evaluation}: EvaluationPolicy::review)
    Route::middleware('role:admin,coordinator')->prefix('evaluations')->controller(EvaluationController::class)->group(function () {
        $scoped = fn ($route) => $route->whereNumber('evaluation')->can('review', 'evaluation');

        Route::get('/summary', 'summary');
        Route::get('/', 'index');
        $scoped(Route::get('/{evaluation}', 'show'));
        $scoped(Route::put('/{evaluation}/coordinator-rating', 'coordinatorRating'));
        $scoped(Route::post('/{evaluation}/review', 'review'));
        $scoped(Route::post('/{evaluation}/reopen', 'reopen'));
        $scoped(Route::post('/{evaluation}/return', 'return'));
    });

    Route::get('/admin/evaluations/export', EvaluationExportController::class)->middleware('role:admin');
    Route::get('/student/evaluations', [StudentEvaluationController::class, 'index'])->middleware('role:student');
});

// Admin
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // Temporary: only proves the role middleware works. Remove once real admin routes exist.
    Route::get('/ping', fn () => response()->json(['ok' => true]));

    // {coordinator} / {partner} resolve only users with that role (see AppServiceProvider).
    Route::controller(CoordinatorController::class)->prefix('coordinators')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{coordinator}', 'update');
        Route::patch('/{coordinator}/status', 'updateStatus');
        Route::delete('/{coordinator}', 'destroy');
    });

    Route::controller(IndustryPartnerController::class)->prefix('partners')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{partner}', 'update');
        Route::patch('/{partner}/status', 'updateStatus');
        Route::delete('/{partner}', 'destroy');
    });
});

// Notifications (Module 8): every role, own notifications only
Route::middleware(['auth:sanctum', 'role:admin,coordinator,partner,student'])->controller(NotificationController::class)->group(function () {
    Route::get('/notifications', 'index');
    Route::get('/notifications/unread-count', 'unreadCount');
    Route::post('/notifications/read-all', 'markAllRead');
    Route::post('/notifications/{notification}/read', 'markRead')->whereUuid('notification');
    Route::get('/me/notification-preferences', 'preferences');
    Route::put('/me/notification-preferences', 'updatePreferences');
});

// Dashboards, analytics and reports (Module 9)
Route::middleware(['auth:sanctum', 'role:admin,coordinator,partner,student'])->group(function () {
    Route::get('/terms', TermController::class);
    Route::get('/dashboard', DashboardController::class);
});
Route::middleware(['auth:sanctum', 'role:admin,coordinator'])->group(function () {
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/reports/{type}', ReportController::class)->whereIn('type', ['deployments', 'partners', 'programs', 'algorithms']);
});
