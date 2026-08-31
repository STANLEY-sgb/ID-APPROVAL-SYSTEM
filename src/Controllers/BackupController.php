<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Controllers;

use Mengo\IdApproval\Security\SessionManager;
use Mengo\IdApproval\Services\BackupService;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;
use Mengo\IdApproval\Support\View;

class BackupController
{
    private BackupService $backupService;

    public function __construct()
    {
        $this->backupService = new BackupService();
    }

    public function index(Request $request): void
    {
        $backups = $this->backupService->listBackups();

        View::render('hr/backups', [
            'pageTitle' => 'Database Backup & Disaster Recovery — Mengo Hospital ID System',
            'backups' => $backups
        ]);
    }

    public function create(Request $request): void
    {
        $this->createBackup($request);
    }

    public function createBackup(Request $request): void
    {
        try {
            $result = $this->backupService->createBackup();
            SessionManager::flash('success', "Database backup created successfully: {$result['filename']} (Size: " . round($result['size'] / 1024, 1) . " KB)");
        } catch (\Throwable $e) {
            SessionManager::flash('error', "Backup failed: " . $e->getMessage());
        }

        Response::redirect('/backups');
    }

    public function download(Request $request): void
    {
        $filename = basename((string)$request->get('file', ''));
        $backupDir = dirname(__DIR__, 2) . '/storage/backups';
        $filePath = $backupDir . '/' . $filename;

        if (empty($filename) || !file_exists($filePath)) {
            Response::notFound("Backup file '{$filename}' not found.");
        }

        Response::streamFile($filePath, $filename, 'application/octet-stream', false);
    }
}
