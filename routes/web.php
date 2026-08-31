<?php

use App\Http\Controllers\BrandingAssetController;
use App\Http\Controllers\AffiliationController;
use App\Http\Controllers\AttendanceNotificationController;
use App\Http\Controllers\Auth\ForcedPasswordChangeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardContentAssetController;
use App\Http\Controllers\DashboardContentController;
use App\Http\Controllers\DashboardContentImageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardLinkController;
use App\Http\Controllers\DevelopmentRequestController;
use App\Http\Controllers\FinanceHrSsoController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\EmployeeItDeviceListController;
use App\Http\Controllers\EmployeeRegistryController;
use App\Http\Controllers\EquipmentPurchaseController;
use App\Http\Controllers\MonthlyAffiliationSnapshotController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileHrDetailController;
use App\Http\Controllers\DepartmentPortalProxyController;
use App\Http\Controllers\DriveAppSyncController;
use App\Http\Controllers\Internal\EmployeeDirectoryController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\SitePreparationController;
use App\Http\Controllers\WordPressMediaController;
use App\Support\DepartmentPortal;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/index');

Route::get('/index', SitePreparationController::class)->name('site-preparation');

Route::get('/branding/{path}', [BrandingAssetController::class, 'show'])
    ->where('path', '.*')
    ->name('branding.asset');

Route::middleware('employee.portal.proxy')->get(
    '/internal/portal/employee-directory',
    EmployeeDirectoryController::class,
)->name('internal.employee-directory');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/password/change', [ForcedPasswordChangeController::class, 'create'])->name('password.change');
    Route::post('/password/change', [ForcedPasswordChangeController::class, 'store'])->name('password.change.update');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/org-chart/{path?}', [OrgChartController::class, 'show'])
        ->where('path', '.*')
        ->name('org-chart.show');
    Route::get('/apps/finance-hr', FinanceHrSsoController::class)
        ->name('finance-hr.enter');

    Route::get('/development-requests', [DevelopmentRequestController::class, 'index'])->name('development-requests.index');
    Route::get('/development-requests/create', [DevelopmentRequestController::class, 'create'])->name('development-requests.create');
    Route::post('/development-requests', [DevelopmentRequestController::class, 'store'])->name('development-requests.store');
    Route::get('/development-requests/{developmentRequest}/complete', [DevelopmentRequestController::class, 'complete'])->name('development-requests.complete');
    Route::get('/development-requests/{developmentRequest}', [DevelopmentRequestController::class, 'show'])->name('development-requests.show');
    Route::put('/development-requests/{developmentRequest}', [DevelopmentRequestController::class, 'update'])->name('development-requests.update');
    Route::get('/dashboard/announcements/create', [DashboardContentController::class, 'createAnnouncement'])->name('dashboard.announcements.create');
    Route::post('/dashboard/announcements', [DashboardContentController::class, 'storeAnnouncement'])->name('dashboard.announcements.store');
    Route::get('/dashboard/announcements/{dashboardContent}/edit', [DashboardContentController::class, 'editAnnouncement'])->name('dashboard.announcements.edit');
    Route::put('/dashboard/announcements/{dashboardContent}', [DashboardContentController::class, 'updateAnnouncement'])->name('dashboard.announcements.update');
    Route::delete('/dashboard/announcements/{dashboardContent}', [DashboardContentController::class, 'destroyAnnouncement'])->name('dashboard.announcements.destroy');
    Route::get('/dashboard/links/edit', [DashboardLinkController::class, 'edit'])->name('dashboard.links.edit');
    Route::put('/dashboard/links', [DashboardLinkController::class, 'update'])->name('dashboard.links.update');
    Route::post('/dashboard/content/images', [DashboardContentImageController::class, 'store'])->name('dashboard.content.images.store');
    Route::get('/dashboard/content/assets/{path}', [DashboardContentAssetController::class, 'show'])
        ->where('path', '.*')
        ->name('dashboard-contents.asset');
    Route::get('/wordpress-media', WordPressMediaController::class)
        ->name('wordpress-media.show');

    foreach (DepartmentPortal::all() as $portal) {
        $proxyPath = trim((string) ($portal['proxy_path'] ?? ''), '/');
        $entryPath = trim((string) ($portal['entry_path'] ?? ''), '/');

        if ($proxyPath === '') {
            continue;
        }

        if ($entryPath !== '') {
            Route::redirect('/'.$proxyPath, '/'.$proxyPath.'/'.$entryPath);
        }

        $routeName = $proxyPath === 'realestate-portal'
            ? 'realestate-portal.proxy'
            : 'department-portal.proxy.'.$proxyPath;

        Route::any('/'.$proxyPath.'/{path?}', DepartmentPortalProxyController::class)
            ->where('path', '.*')
            ->defaults('portal', $proxyPath)
            ->name($routeName);
    }

    Route::post('/drive-app/sync', DriveAppSyncController::class)->name('drive-app.sync');

    Route::get('/attendance-notifications/create', [AttendanceNotificationController::class, 'create'])->name('attendance-notifications.create');
    Route::post('/attendance-notifications', [AttendanceNotificationController::class, 'store'])->name('attendance-notifications.store');
    Route::get('/attendance-notifications/{attendanceNotification}/complete', [AttendanceNotificationController::class, 'complete'])->name('attendance-notifications.complete');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/import', [EmployeeImportController::class, 'create'])->name('employees.import.create');
    Route::get('/employees/import/template', [EmployeeImportController::class, 'template'])->name('employees.import.template');
    Route::post('/employees/import', [EmployeeImportController::class, 'store'])->name('employees.import.store');
    Route::get('/employees/create', [EmployeeRegistryController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeRegistryController::class, 'store'])->name('employees.store');
    Route::get('/employees/{user}/edit', [EmployeeRegistryController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{user}', [EmployeeRegistryController::class, 'update'])->name('employees.update');
    Route::get('/it-devices', [EmployeeItDeviceListController::class, 'index'])->name('it-devices.index');
    Route::get('/it-devices/{user}', [EmployeeItDeviceListController::class, 'show'])->name('it-devices.show');
    Route::put('/it-devices/{user}', [EmployeeItDeviceListController::class, 'update'])->name('it-devices.update');

    Route::get('/hr-details/export', [ProfileHrDetailController::class, 'exportAll'])->name('hr-details.export');

    Route::get('/monthly-affiliations', [MonthlyAffiliationSnapshotController::class, 'index'])->name('monthly-affiliations.index');
    Route::post('/monthly-affiliations', [MonthlyAffiliationSnapshotController::class, 'store'])->name('monthly-affiliations.store');
    Route::get('/monthly-affiliations/{yearMonth}/export', [MonthlyAffiliationSnapshotController::class, 'export'])->name('monthly-affiliations.export');
    Route::get('/monthly-affiliations/{yearMonth}', [MonthlyAffiliationSnapshotController::class, 'show'])->name('monthly-affiliations.show');

    Route::get('/equipment-purchases', [EquipmentPurchaseController::class, 'index'])->name('equipment-purchases.index');
    Route::get('/equipment-purchases/list', [EquipmentPurchaseController::class, 'list'])->name('equipment-purchases.list');
    Route::get('/equipment-purchases/export', [EquipmentPurchaseController::class, 'export'])->name('equipment-purchases.export');
    Route::get('/equipment-purchases/pending', [EquipmentPurchaseController::class, 'pending'])->name('equipment-purchases.pending');
    Route::get('/equipment-purchases/create', [EquipmentPurchaseController::class, 'create'])->name('equipment-purchases.create');
    Route::post('/equipment-purchases', [EquipmentPurchaseController::class, 'store'])->name('equipment-purchases.store');
    Route::get('/equipment-purchases/{equipmentPurchase}/complete', [EquipmentPurchaseController::class, 'complete'])->name('equipment-purchases.complete');
    Route::get('/equipment-purchases/{equipmentPurchase}', [EquipmentPurchaseController::class, 'show'])->name('equipment-purchases.show');
    Route::get('/equipment-purchases/{equipmentPurchase}/approve', [EquipmentPurchaseController::class, 'approve'])->name('equipment-purchases.approve');
    Route::put('/equipment-purchases/{equipmentPurchase}/approve', [EquipmentPurchaseController::class, 'updateApproval'])->name('equipment-purchases.approve.update');
    Route::put('/equipment-purchases/{equipmentPurchase}/order', [EquipmentPurchaseController::class, 'updateOrder'])->name('equipment-purchases.order.update');
    Route::put('/equipment-purchases/{equipmentPurchase}/consumable', [EquipmentPurchaseController::class, 'updateConsumable'])->name('equipment-purchases.consumable.update');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/photo/{user}', [ProfilePhotoController::class, 'show'])->name('profile.photo');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/profile/hr-detail/edit', [ProfileHrDetailController::class, 'edit'])->name('profile.hr-detail.edit');
    Route::put('/profile/hr-detail', [ProfileHrDetailController::class, 'update'])->name('profile.hr-detail.update');
    Route::get('/profile/hr-detail/export', [ProfileHrDetailController::class, 'export'])->name('profile.hr-detail.export');

    Route::get('/users/{user}/profile', [ProfileController::class, 'show'])->name('users.profile.show');
    Route::get('/users/{user}/profile/edit', [ProfileController::class, 'edit'])->name('users.profile.edit');
    Route::put('/users/{user}/profile', [ProfileController::class, 'update'])->name('users.profile.update');
    Route::delete('/users/{user}/profile', [ProfileController::class, 'destroy'])->name('users.profile.destroy');

    Route::get('/users/{user}/profile/hr-detail/edit', [ProfileHrDetailController::class, 'edit'])->name('users.profile.hr-detail.edit');
    Route::put('/users/{user}/profile/hr-detail', [ProfileHrDetailController::class, 'update'])->name('users.profile.hr-detail.update');
    Route::get('/users/{user}/profile/hr-detail/export', [ProfileHrDetailController::class, 'export'])->name('users.profile.hr-detail.export');

    Route::get('/affiliations/create', [AffiliationController::class, 'create'])->name('affiliations.create');
    Route::post('/affiliations', [AffiliationController::class, 'store'])->name('affiliations.store');
    Route::get('/users/{user}/affiliations/create', [AffiliationController::class, 'create'])->name('users.affiliations.create');
    Route::post('/users/{user}/affiliations', [AffiliationController::class, 'store'])->name('users.affiliations.store');

    Route::get('/affiliations/{affiliation}/edit', [AffiliationController::class, 'edit'])->name('affiliations.edit');
    Route::put('/affiliations/{affiliation}', [AffiliationController::class, 'update'])->name('affiliations.update');
    Route::delete('/affiliations/{affiliation}', [AffiliationController::class, 'destroy'])->name('affiliations.destroy');
});
