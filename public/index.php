<?php
/**
 * Mengo Hospital Employee ID Card Management System
 * Entry Point — public/index.php
 *
 * Bootstraps the application, applies security middleware,
 * and dispatches to the appropriate controller.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_START', microtime(true));

require_once APP_ROOT . '/src/autoload.php';

use Mengo\IdApproval\Support\Config;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;
use Mengo\IdApproval\Support\Router;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Security\CsrfToken;
use Mengo\IdApproval\Middleware\SecurityHeadersMiddleware;
use Mengo\IdApproval\Middleware\AuthMiddleware;
use Mengo\IdApproval\Middleware\CsrfMiddleware;
use Mengo\IdApproval\Controllers\AdminController;
use Mengo\IdApproval\Controllers\AuthController;
use Mengo\IdApproval\Controllers\DashboardController;
use Mengo\IdApproval\Controllers\DesignerController;
use Mengo\IdApproval\Controllers\HrController;
use Mengo\IdApproval\Controllers\PrintingController;
use Mengo\IdApproval\Controllers\IdCardController;
use Mengo\IdApproval\Controllers\NotificationController;
use Mengo\IdApproval\Controllers\ReportController;
use Mengo\IdApproval\Controllers\AuditLogController;
use Mengo\IdApproval\Controllers\BackupController;
use Mengo\IdApproval\Controllers\HealthController;
use Mengo\IdApproval\Controllers\SyncController;
use Mengo\IdApproval\Models\Role;

// 1. Timezone
Timezone::configure();

// 2. Security Headers
SecurityHeadersMiddleware::apply();

// 3. Session
SessionManager::start();

// 4. Error Handling & Security Logging
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_exception_handler(function (\Throwable $e) {
    $logDir = APP_ROOT . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logMsg = sprintf(
        "[%s] Uncaught Exception (%s): %s in %s:%d\nTrace:\n%s\n\n",
        Timezone::nowString(),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    @file_put_contents($logDir . '/app.log', $logMsg, FILE_APPEND);

    http_response_code(500);
    $code = 500;
    $title = 'Something went wrong';
    $message = "We couldn't complete that request. Please contact the System Administrator if the problem continues.";
    include APP_ROOT . '/src/Views/errors/error.php';
    exit;
});

// 5. Helper: Role-based Dashboard URL
function getDashboardUrl(string $role): string
{
    return match($role) {
        Role::DESIGNER         => '/designer/dashboard',
        Role::HR_MANAGER       => '/hr/dashboard',
        Role::PRINTING_OFFICER => '/printing/dashboard',
        Role::ADMINISTRATOR    => '/admin/hr-accounts',
        default                => '/login',
    };
}

// 6. Router: Build & Dispatch
$router = new Router();
$request = new Request();
$response = new Response();

// ── PUBLIC: Auth ─────────────────────────────────────
$router->get('/', function() use ($request, $response) {
    if (SessionManager::isAuthenticated()) {
        $user = SessionManager::getUser();
        Response::redirect(getDashboardUrl($user['role'] ?? ''));
    }
    Response::redirect('/login');
});

$router->get('/login', function() use ($request) {
    $ctrl = new AuthController();
    $ctrl->showLogin($request);
});

$router->post('/login', function() use ($request) {
    CsrfMiddleware::verify($request);
    $ctrl = new AuthController();
    $ctrl->login($request);
});

$router->post('/logout', function() use ($request) {
    CsrfMiddleware::verify($request);
    $ctrl = new AuthController();
    $ctrl->logout($request);
});

$router->get('/change-password', function() use ($request, $response) {
    AuthMiddleware::require($request, $response);
    $ctrl = new AuthController();
    $ctrl->showChangePassword($request);
});

$router->post('/change-password', function() use ($request, $response) {
    AuthMiddleware::require($request, $response);
    CsrfMiddleware::verify($request);
    $ctrl = new AuthController();
    $ctrl->changePassword($request);
});

$router->get('/dashboard', function() use ($request, $response) {
    AuthMiddleware::require($request, $response);
    $user = SessionManager::getUser();
    Response::redirect(getDashboardUrl($user['role'] ?? ''));
});

// Quick Login (Dev/Demo only)
$router->post('/quick-login', function() use ($request) {
    if (Config::isProduction()) {
        http_response_code(403);
        echo 'Not available in production.';
        exit;
    }
    CsrfMiddleware::verify($request);
    $ctrl = new AuthController();
    $ctrl->quickLogin($request);
});

// ── SHARED: ID Card Detail (accessible by all authenticated staff) ─
$router->get('/id-cards/{id}', function(int $id) use ($request, $response) {
    AuthMiddleware::require($request, $response);
    $ctrl = new IdCardController();
    $ctrl->show($id, $request);
});

$router->get('/id-cards/{id}/pdf', function(int $id) use ($request, $response) {
    AuthMiddleware::require($request, $response);
    $ctrl = new IdCardController();
    $ctrl->servePdf($id, $request);
});

// ── DESIGNER Routes ───────────────────────────────────
$router->get('/designer/dashboard', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::DESIGNER);
    $ctrl = new DesignerController();
    $ctrl->dashboard($request);
});

$router->get('/designer/create', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::DESIGNER);
    $ctrl = new DesignerController();
    $ctrl->createForm($request);
});

$router->post('/designer/create', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::DESIGNER);
    CsrfMiddleware::verify($request);
    $ctrl = new DesignerController();
    $ctrl->create($request);
});

$router->get('/designer/my-ids', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::DESIGNER);
    $ctrl = new DesignerController();
    $ctrl->myIds($request);
});

$router->get('/designer/corrections', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::DESIGNER);
    $ctrl = new DesignerController();
    $ctrl->corrections($request);
});

$router->post('/designer/reupload', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::DESIGNER);
    CsrfMiddleware::verify($request);
    $ctrl = new DesignerController();
    $ctrl->reupload($request);
});

// ── HR Routes ─────────────────────────────────────────
$router->get('/hr/dashboard', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::HR_MANAGER);
    $ctrl = new HrController();
    $ctrl->dashboard($request);
});

$router->get('/hr/pending', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::HR_MANAGER);
    $ctrl = new HrController();
    $ctrl->pendingApprovals($request);
});

$router->get('/hr/all-ids', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::HR_MANAGER);
    $ctrl = new HrController();
    $ctrl->allIds($request);
});

$router->get('/hr/corrections', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::HR_MANAGER);
    $ctrl = new HrController();
    $ctrl->corrections($request);
});

$router->get('/hr/printing', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::HR_MANAGER);
    $ctrl = new HrController();
    $ctrl->printingStatus($request);
});

$router->get('/hr/collection', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::HR_MANAGER);
    $ctrl = new HrController();
    $ctrl->collection($request);
});

$router->post('/hr/approve', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::HR_MANAGER);
    CsrfMiddleware::verify($request);
    $ctrl = new HrController();
    $ctrl->approve($request);
});

$router->post('/hr/request-correction', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::HR_MANAGER);
    CsrfMiddleware::verify($request);
    $ctrl = new HrController();
    $ctrl->requestCorrection($request);
});

$router->post('/hr/mark-collected', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::HR_MANAGER);
    CsrfMiddleware::verify($request);
    $ctrl = new HrController();
    $ctrl->markCollected($request);
});

$router->get('/backups', function() use ($request, $response) {
    AuthMiddleware::requireRoles($request, $response, [Role::HR_MANAGER, Role::ADMINISTRATOR]);
    $ctrl = new BackupController();
    $ctrl->index($request);
});

$router->get('/hr/backups', function() use ($request, $response) {
    AuthMiddleware::requireRoles($request, $response, [Role::HR_MANAGER, Role::ADMINISTRATOR]);
    $ctrl = new BackupController();
    $ctrl->index($request);
});

$router->post('/backups/create', function() use ($request, $response) {
    AuthMiddleware::requireRoles($request, $response, [Role::HR_MANAGER, Role::ADMINISTRATOR]);
    CsrfMiddleware::verify($request);
    $ctrl = new BackupController();
    $ctrl->create($request);
});

$router->get('/backups/download', function() use ($request, $response) {
    AuthMiddleware::requireRoles($request, $response, [Role::HR_MANAGER, Role::ADMINISTRATOR]);
    $ctrl = new BackupController();
    $ctrl->download($request);
});

// ── SYSTEM ADMINISTRATOR Routes ───────────────────────
$router->get('/admin/hr-accounts', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::ADMINISTRATOR);
    $ctrl = new AdminController();
    $ctrl->hrAccounts($request);
});

$router->post('/admin/hr-accounts/create', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::ADMINISTRATOR);
    CsrfMiddleware::verify($request);
    $ctrl = new AdminController();
    $ctrl->createHrAccount($request);
});

$router->post('/admin/hr-accounts/toggle-status', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::ADMINISTRATOR);
    CsrfMiddleware::verify($request);
    $ctrl = new AdminController();
    $ctrl->toggleHrStatus($request);
});

$router->post('/admin/hr-accounts/reset-password', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::ADMINISTRATOR);
    CsrfMiddleware::verify($request);
    $ctrl = new AdminController();
    $ctrl->resetHrPassword($request);
});

$router->post('/admin/hr-accounts/update', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::ADMINISTRATOR);
    CsrfMiddleware::verify($request);
    $ctrl = new AdminController();
    $ctrl->updateUserAccount($request);
});

// ── PRINTING Routes ───────────────────────────────────
$router->get('/printing/dashboard', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::PRINTING_OFFICER);
    $ctrl = new PrintingController();
    $ctrl->dashboard($request);
});

$router->get('/printing/ready', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::PRINTING_OFFICER);
    $ctrl = new PrintingController();
    $ctrl->readyToPrint($request);
});

$router->get('/printing/printed', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::PRINTING_OFFICER);
    $ctrl = new PrintingController();
    $ctrl->printedIds($request);
});

$router->get('/printing/awaiting-collection', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::PRINTING_OFFICER);
    $ctrl = new PrintingController();
    $ctrl->awaitingCollection($request);
});

$router->post('/printing/mark-printed', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::PRINTING_OFFICER);
    CsrfMiddleware::verify($request);
    $ctrl = new PrintingController();
    $ctrl->markPrinted($request);
});

$router->post('/printing/bulk-print', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::PRINTING_OFFICER);
    CsrfMiddleware::verify($request);
    $ctrl = new PrintingController();
    $ctrl->bulkPrint($request);
});

$router->post('/printing/batches/validate', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::PRINTING_OFFICER);
    $ctrl = new PrintingController();
    $ctrl->validateBatch($request);
});

$router->post('/printing/batches/merge', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::PRINTING_OFFICER);
    $ctrl = new PrintingController();
    $ctrl->mergeBatch($request);
});

$router->post('/printing/batches/confirm-print', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::PRINTING_OFFICER);
    CsrfMiddleware::verify($request);
    $ctrl = new PrintingController();
    $ctrl->confirmBatchPrint($request);
});

$router->get('/printing/batches', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::PRINTING_OFFICER);
    $ctrl = new PrintingController();
    $ctrl->batchHistory($request);
});

$router->get('/printing/batches/{id}', function(int $id) use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::PRINTING_OFFICER);
    $ctrl = new PrintingController();
    $ctrl->showBatch($id, $request);
});

$router->get('/printing/batches/{id}/preview', function(int $id) use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::PRINTING_OFFICER);
    $ctrl = new PrintingController();
    $ctrl->previewBatch($id, $request);
});

$router->get('/printing/batches/{id}/download', function(int $id) use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::PRINTING_OFFICER);
    $ctrl = new PrintingController();
    $ctrl->downloadBatch($id, $request);
});

// ── REAL-TIME LIVE SYNC API ───────────────────────────
$router->get('/api/sync', function() use ($request, $response) {
    AuthMiddleware::require($request, $response);
    $ctrl = new SyncController();
    $ctrl->sync($request);
});

// ── NOTIFICATIONS ─────────────────────────────────────
$router->get('/notifications', function() use ($request, $response) {
    AuthMiddleware::require($request, $response);
    $ctrl = new NotificationController();
    $ctrl->index($request);
});

$router->post('/notifications/mark-all-read', function() use ($request, $response) {
    AuthMiddleware::require($request, $response);
    CsrfMiddleware::verify($request);
    $ctrl = new NotificationController();
    $ctrl->markAllRead($request);
});

$router->get('/api/notifications/unread-count', function() use ($request, $response) {
    AuthMiddleware::require($request, $response);
    $ctrl = new NotificationController();
    $ctrl->unreadCount($request);
});

// ── REPORTS (HR & Admin) ──────────────────────────────
$router->get('/reports', function() use ($request, $response) {
    AuthMiddleware::requireRoles($request, $response, [Role::HR_MANAGER, Role::ADMINISTRATOR]);
    $ctrl = new ReportController();
    $ctrl->index($request);
});

$router->get('/reports/search', function() use ($request, $response) {
    AuthMiddleware::requireRoles($request, $response, [Role::HR_MANAGER, Role::ADMINISTRATOR]);
    $ctrl = new ReportController();
    $ctrl->index($request);
});

$router->get('/reports/export-csv', function() use ($request, $response) {
    AuthMiddleware::requireRoles($request, $response, [Role::HR_MANAGER, Role::ADMINISTRATOR]);
    $ctrl = new ReportController();
    $ctrl->exportCsv($request);
});

// ── AUDIT LOGS (HR & Admin) ───────────────────────────
$router->get('/audit-logs', function() use ($request, $response) {
    AuthMiddleware::requireRoles($request, $response, [Role::HR_MANAGER, Role::ADMINISTRATOR]);
    $ctrl = new AuditLogController();
    $ctrl->index($request);
});

// ── HEALTH CHECK ──────────────────────────────────────
$router->get('/health', function() use ($request, $response) {
    AuthMiddleware::require($request, $response);
    $ctrl = new HealthController();
    $ctrl->index($request);
});

$router->get('/admin/diagnostics', function() use ($request, $response) {
    AuthMiddleware::requireRole($request, $response, Role::ADMINISTRATOR);
    $ctrl = new HealthController();
    $ctrl->check($request);
});

// ── DISPATCH ──────────────────────────────────────────
try {
    $router->dispatch(
        $request->method(),
        $request->path()
    );
} catch (\Mengo\IdApproval\Support\NotFoundException $e) {
    http_response_code(404);
    $code = 404;
    $title = 'Page Not Found';
    $message = 'The page you requested could not be found.';
    include APP_ROOT . '/src/Views/errors/error.php';
} catch (\Mengo\IdApproval\Support\ForbiddenException $e) {
    http_response_code(403);
    $code = 403;
    $title = 'Access Denied';
    $message = $e->getMessage() ?: 'You do not have permission to access this resource.';
    include APP_ROOT . '/src/Views/errors/error.php';
} catch (\Throwable $e) {
    error_log('[MENGO-ID-SYSTEM] Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (!Config::isProduction()) {
        echo '<pre style="font-size:12px;padding:20px;background:#111;color:#f87171;border-radius:6px;">';
        echo htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString());
        echo '</pre>';
    } else {
        $code = 500;
        $title = 'Internal Server Error';
        $message = 'Something went wrong on our end. The system administrator has been notified.';
        include APP_ROOT . '/src/Views/errors/error.php';
    }
}
