<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Support;

use Mengo\IdApproval\Security\CsrfToken;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Security\SessionManager;
use RuntimeException;

class View
{
    private static string $viewsDir = '';

    public static function setViewsDir(string $dir): void
    {
        self::$viewsDir = $dir;
    }

    public static function render(string $viewName, array $data = [], string $layout = 'layouts/main'): void
    {
        if (empty(self::$viewsDir)) {
            self::$viewsDir = dirname(__DIR__) . '/Views';
        }

        $viewFile = self::$viewsDir . '/' . ltrim($viewName, '/\\') . '.php';
        if (!file_exists($viewFile)) {
            throw new RuntimeException("View file not found: {$viewFile}");
        }

        // Global view data
        $currentUser = SessionManager::getUser();
        $flashes = SessionManager::getFlashes();
        $csrfField = CsrfToken::field();
        $csrfToken = CsrfToken::get();

        $mergedData = array_merge([
            'currentUser' => $currentUser,
            'flashes' => $flashes,
            'csrfField' => $csrfField,
            'csrfToken' => $csrfToken,
            'pageTitle' => 'Mengo Hospital ID Approval System',
        ], $data);

        extract($mergedData, EXTR_SKIP);

        // Capture view content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === '' || $layout === null) {
            echo $content;
            return;
        }

        $layoutFile = self::$viewsDir . '/' . ltrim($layout, '/\\') . '.php';
        if (!file_exists($layoutFile)) {
            throw new RuntimeException("Layout file not found: {$layoutFile}");
        }

        require $layoutFile;
    }

    public static function e(?string $string): string
    {
        return Sanitizer::escape($string);
    }
}
