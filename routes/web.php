<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BlockController;
use App\Http\Controllers\Admin\BlockFormController;
use App\Http\Controllers\Admin\CwpController;
use App\Http\Controllers\Admin\InvestmentController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\ClearanceController;
use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\ComplianceItemController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\McMeetingController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\MilestoneController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Login page is the default home page
Route::middleware('guest')->group(function () {
    Route::get('/', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Every page below needs login + active account + the permission in ->can()
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/home', [LoginController::class, 'home'])->name('home');

    // 1. Executive Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard')->can('dashboard.view');

    // 2. Block Overview, 4. Block Detail, and block-wise lists
    Route::get('/blocks', [BlockController::class, 'index'])->name('blocks.index')->can('blocks.view');
    Route::get('/blocks/create', [BlockFormController::class, 'create'])->name('blocks.create')->can('blocks.create');
    Route::post('/blocks', [BlockFormController::class, 'store'])->name('blocks.store')->can('blocks.create');
    Route::get('/blocks/{block}', [BlockController::class, 'show'])->name('blocks.show')->can('view', 'block');
    Route::get('/blocks/{block}/edit', [BlockFormController::class, 'edit'])->name('blocks.edit')->can('blocks.edit');
    Route::put('/blocks/{block}', [BlockFormController::class, 'update'])->name('blocks.update')->can('blocks.edit');
    Route::delete('/blocks/{block}', [BlockFormController::class, 'destroy'])->name('blocks.destroy')->can('blocks.delete');
    Route::get('/work-programme', [BlockController::class, 'cwp'])->name('cwp.index')->can('blocks.view');
    Route::get('/work-programme/{block}/edit', [CwpController::class, 'edit'])->name('cwp.edit')->can('blocks.edit');
    Route::put('/work-programme/{block}', [CwpController::class, 'update'])->name('cwp.update')->can('blocks.edit');

    // Investment Monitoring
    Route::get('/investment', [BlockController::class, 'investment'])->name('investment.index')->can('investment.view');
    Route::get('/investment/create', [InvestmentController::class, 'create'])->name('investment.create')->can('investment.create');
    Route::post('/investment', [InvestmentController::class, 'store'])->name('investment.store')->can('investment.create');
    Route::get('/investment/{block}/edit', [InvestmentController::class, 'edit'])->name('investment.edit')->can('investment.edit');
    Route::put('/investment/{block}', [InvestmentController::class, 'update'])->name('investment.update')->can('investment.edit');

    // Location Inventory
    Route::get('/locations', [BlockController::class, 'locations'])->name('locations.index')->can('locations.view');
    Route::get('/locations/create', [LocationController::class, 'create'])->name('locations.create')->can('locations.create');
    Route::post('/locations', [LocationController::class, 'store'])->name('locations.store')->can('locations.create');
    Route::get('/locations/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit')->can('locations.edit');
    Route::put('/locations/{location}', [LocationController::class, 'update'])->name('locations.update')->can('locations.edit');
    Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy')->can('locations.delete');

    // 3. Compliance Monitor
    Route::get('/compliance', [ComplianceController::class, 'index'])->name('compliance.index')->can('milestones.view');
    Route::put('/milestones/{milestone}', [MilestoneController::class, 'update'])->name('milestones.update')->can('milestones.edit');

    // 5. Statutory Clearances
    Route::get('/clearances', [ClearanceController::class, 'index'])->name('clearances.index')->can('clearances.view');
    Route::get('/clearances/create', [ClearanceController::class, 'create'])->name('clearances.create')->can('clearances.create');
    Route::post('/clearances', [ClearanceController::class, 'store'])->name('clearances.store')->can('clearances.create');
    Route::get('/clearances/edit', [ClearanceController::class, 'edit'])->name('clearances.edit')->can('clearances.edit');
    Route::put('/clearances', [ClearanceController::class, 'update'])->name('clearances.update')->can('clearances.edit');
    Route::delete('/clearances', [ClearanceController::class, 'destroy'])->name('clearances.destroy')->can('clearances.delete');

    // MC Meetings: add meeting, upload minutes
    Route::get('/mc-meetings', [McMeetingController::class, 'index'])->name('mc-meetings.index')->can('mc_meetings.view');
    Route::post('/mc-meetings', [McMeetingController::class, 'store'])->name('mc-meetings.store')->can('mc_meetings.edit');
    Route::post('/mc-meetings/{mcMeeting}/minutes', [McMeetingController::class, 'uploadMinutes'])->name('mc-meetings.minutes')->can('mc_meetings.edit');

    // Document Repository
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index')->can('documents.view');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store')->can('documents.create');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download')->can('documents.view');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy')->can('documents.delete');

    // 6. Reports & Downloads
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index')->can('reports.view');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export')->can('reports.export');

    Route::view('/short-forms', 'admin.glossary')->name('glossary');

    // Notifications (in the portal only, no email)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index')->can('milestones.view');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all')->can('milestones.view');
    Route::post('/notifications/run', [NotificationController::class, 'run'])->name('notifications.run')->can('notifications.run');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open')->can('milestones.view');
    Route::post('/notifications/{id}/toggle', [NotificationController::class, 'toggle'])->name('notifications.toggle')->can('milestones.view');

    Route::get('/change-password', [ProfileController::class, 'editPassword'])->name('profile.password');
    Route::put('/change-password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Admin
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index')->can('users.view');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create')->can('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store')->can('users.create');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit')->can('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update')->can('users.edit');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->can('users.delete');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index')->can('roles.view');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create')->can('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store')->can('roles.create');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit')->can('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update')->can('roles.edit');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->can('roles.delete');

        Route::get('compliance-register', [ComplianceItemController::class, 'index'])->name('compliance-items.index')->can('compliance_register.view');
        Route::get('compliance-register/{complianceItem}/edit', [ComplianceItemController::class, 'edit'])->name('compliance-items.edit')->can('compliance_register.edit');
        Route::put('compliance-register/{complianceItem}', [ComplianceItemController::class, 'update'])->name('compliance-items.update')->can('compliance_register.edit');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index')->can('audit_logs.view');
    });
});
