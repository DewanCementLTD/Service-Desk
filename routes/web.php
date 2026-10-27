<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevelopmentRequestController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScopeController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamLeadController;
use App\Http\Controllers\TeamReportsController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// ---------------------------------------------------------------------- guest
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// ------------------------------------------------------------ password change
// Outside the 'permission' group: a user forced to change their password must be
// able to reach this even before anything else is available to them.
Route::middleware('auth')->group(function () {
    Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
});

// -------------------------------------------------------------- authenticated
Route::middleware(['auth', 'permission'])->group(function () {

    // Active company / location switcher.
    Route::post('scope/company', [ScopeController::class, 'switchCompany'])->name('scope.company');
    Route::post('scope/location', [ScopeController::class, 'switchLocation'])->name('scope.location');

    // ------------------------------------------------------------- dashboards
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/dgm', [DashboardController::class, 'dgm'])
        ->middleware('permission:dashboard.dgm')->name('dashboard.dgm');
    Route::get('dashboard/management', [DashboardController::class, 'management'])
        ->middleware('permission:dashboard.management')->name('dashboard.management');
    Route::get('dashboard/development', [DashboardController::class, 'development'])
        ->middleware('permission:dashboard.development')->name('dashboard.development');
    // Reachable directly for roles the default redirect sends elsewhere
    // (DGM/Admin/IT Manager, Management, Developer) — see the "My dashboard"
    // sidebar link in layouts/app.blade.php.
    Route::get('dashboard/personal', [DashboardController::class, 'personal'])
        ->middleware('permission:dashboard.view')->name('dashboard.personal');

    // ---------------------------------------------------------- team management
    Route::prefix('team')->name('team-lead.')->middleware('permission:team.manage')->group(function () {
        Route::get('/', [TeamLeadController::class, 'index'])->name('index');
        Route::get('create', [TeamLeadController::class, 'create'])->name('create');
        Route::post('/', [TeamLeadController::class, 'store'])->name('store');
        Route::delete('assignments/{assignment}', [TeamLeadController::class, 'destroy'])->name('destroy');
    });

    // ---------------------------------------------------------- team reports
    Route::get('team-reports', [TeamReportsController::class, 'index'])
        ->middleware('permission:team.manage')
        ->name('team-reports.index');

    // ---------------------------------------------------------------- tickets
    Route::middleware('permission:tickets.view')->group(function () {
        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        // Intake chooser: bugs and issues, or a new development request. Declared
        // before tickets/{ticket} so "new" is not read as a ticket identifier.
        Route::get('tickets/new', [TicketController::class, 'chooseType'])
            ->middleware('permission:tickets.create')->name('tickets.new');
        Route::get('tickets/create', [TicketController::class, 'create'])
            ->middleware('permission:tickets.create')->name('tickets.create');
        Route::post('tickets', [TicketController::class, 'store'])
            ->middleware('permission:tickets.create')->name('tickets.store');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::get('tickets/{ticket}/edit', [TicketController::class, 'edit'])->name('tickets.edit');
        Route::put('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
        Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
        Route::post('tickets/{ticket}/status', [TicketController::class, 'changeStatus'])->name('tickets.status');
        Route::post('tickets/{ticket}/resolve', [TicketController::class, 'resolve'])->name('tickets.resolve');
        Route::post('tickets/{ticket}/comment', [TicketController::class, 'comment'])->name('tickets.comment');
        Route::post('tickets/{ticket}/rate', [TicketController::class, 'rate'])->name('tickets.rate');
        Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
        Route::get('attachments/{attachment}', [TicketController::class, 'downloadAttachment'])
            ->name('tickets.attachment');
    });

    // ------------------------------------------------------------------ board
    // Reading the board needs board.view; every move re-checks board.manage in
    // the controller, so a viewer can watch the queue without moving anything.
    Route::middleware('permission:board.view')->prefix('board')->name('board.')->group(function () {
        Route::get('/', [BoardController::class, 'index'])->name('index');
        Route::get('backlog', [BoardController::class, 'backlog'])->name('backlog');
        Route::post('move', [BoardController::class, 'move'])->name('move');
        Route::post('commit', [BoardController::class, 'commit'])->name('commit');

        Route::middleware('permission:sprints.manage')->group(function () {
            Route::post('sprints', [BoardController::class, 'storeSprint'])->name('sprints.store');
            Route::post('sprints/{sprint}/start', [BoardController::class, 'startSprint'])->name('sprints.start');
            Route::post('sprints/{sprint}/close', [BoardController::class, 'closeSprint'])->name('sprints.close');
        });
    });

    // ------------------------------------------------------------ development
    Route::middleware('permission:development.view')->group(function () {
        Route::get('development', [DevelopmentRequestController::class, 'index'])->name('development.index');
        Route::get('development/create', [DevelopmentRequestController::class, 'create'])
            ->middleware('permission:development.create')->name('development.create');
        Route::post('development', [DevelopmentRequestController::class, 'store'])
            ->middleware('permission:development.create')->name('development.store');
        Route::get('development/{development}', [DevelopmentRequestController::class, 'show'])->name('development.show');
        Route::put('development/{development}', [DevelopmentRequestController::class, 'update'])->name('development.update');
        Route::post('development/{development}/decide', [DevelopmentRequestController::class, 'decide'])
            ->middleware('permission:development.approve')->name('development.decide');
        Route::post('development/{development}/update', [DevelopmentRequestController::class, 'addUpdate'])
            ->name('development.progress');
        Route::delete('development/{development}', [DevelopmentRequestController::class, 'destroy'])
            ->middleware('permission:development.delete')->name('development.destroy');
    });

    // ------------------------------------------------------------------ tasks
    Route::middleware('permission:tasks.view')->group(function () {
        Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::get('tasks/create', [TaskController::class, 'create'])
            ->middleware('permission:tasks.create')->name('tasks.create');
        Route::post('tasks', [TaskController::class, 'store'])
            ->middleware('permission:tasks.create')->name('tasks.store');
        Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
        Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::post('tasks/{task}/comment', [TaskController::class, 'comment'])->name('tasks.comment');
        Route::delete('tasks/{task}', [TaskController::class, 'destroy'])
            ->middleware('permission:tasks.delete')->name('tasks.destroy');
        Route::post('tasks/{task}/priority', [TaskController::class, 'updatePriority'])
            ->middleware('permission:tasks.manage_priority')->name('tasks.priority');
    });

    // ---------------------------------------------------------------- profile
    // Everyone maintains their own record, so this carries no permission check
    // beyond being signed in.
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    // ------------------------------------------------------------- user guide
    // Every signed-in user can read it; the page itself hides the steps their
    // role cannot perform.
    Route::get('guide', [GuideController::class, 'index'])->name('guide');

    // ---------------------------------------------------------- notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');

    // Browser push: one registration per browser the user allows.
    Route::post('push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::post('push/test', [PushSubscriptionController::class, 'test'])->name('push.test');

    // ---------------------------------------------------------------- reports
    Route::middleware('permission:reports.view')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('tickets', [ReportController::class, 'tickets'])->name('tickets');
        Route::get('sla', [ReportController::class, 'sla'])->name('sla');
        Route::get('development', [ReportController::class, 'development'])->name('development');
        Route::get('tickets/export', [ReportController::class, 'exportTickets'])
            ->middleware('permission:reports.export')->name('tickets.export');
    });

    // ------------------------------------------------------------------ users
    Route::middleware('permission:users.view')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::middleware('permission:users.manage')->group(function () {
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            // Feeds the Department/Designation selects on the create/edit form
            // once a company is chosen — see resources/js/user-form.js.
            Route::get('users/company-options/{company}', [UserController::class, 'companyOptions'])
                ->name('users.companyOptions');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
                ->name('users.resetPassword');
        });
    });

    // -------------------------------------------------------------- audit log
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit/{audit}', [AuditLogController::class, 'show'])->name('audit.show');
    });

    // ----------------------------------------------------- organisation admin
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('permission:organizations.manage')->group(function () {
            Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
            Route::get('organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
            Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');
            Route::get('organizations/{organization}/edit', [OrganizationController::class, 'edit'])->name('organizations.edit');
            Route::put('organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
        });

        Route::middleware('permission:companies.manage')->group(function () {
            Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
            Route::get('companies/create', [CompanyController::class, 'create'])->name('companies.create');
            Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
            Route::get('companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
            Route::put('companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
        });

        Route::middleware('permission:locations.manage')->group(function () {
            Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
            Route::get('locations/create', [LocationController::class, 'create'])->name('locations.create');
            Route::post('locations', [LocationController::class, 'store'])->name('locations.store');
            Route::get('locations/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit');
            Route::put('locations/{location}', [LocationController::class, 'update'])->name('locations.update');
        });

        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::post('roles/{role}/toggle', [RoleController::class, 'toggleActive'])->name('roles.toggle');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });

        Route::middleware('permission:departments.manage')->group(function () {
            Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
            Route::get('departments/create', [DepartmentController::class, 'create'])->name('departments.create');
            Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
            Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
            Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        });
    });
});
