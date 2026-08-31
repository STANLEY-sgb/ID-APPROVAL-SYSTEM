<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Support;

class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header('Location: ' . $url);
        exit;
    }

    public static function error(string $message, int $statusCode = 400): void
    {
        http_response_code($statusCode);
        View::render('errors/error', [
            'status_code' => $statusCode,
            'message' => $message
        ]);
        exit;
    }

    public static function forbidden(string $message = 'Access Denied. You do not have permission to perform this action.'): void
    {
        http_response_code(403);
        View::render('errors/403', [
            'message' => $message
        ]);
        exit;
    }

    public static function notFound(string $message = 'The requested page or resource was not found.'): void
    {
        http_response_code(404);
        View::render('errors/404', [
            'message' => $message
        ]);
        exit;
    }

    public static function streamFile(string $filePath, string $filename, string $mimeType = 'application/pdf', bool $inline = true): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            self::notFound("File not found or unreadable.");
        }

        $filesize = filesize($filePath);
        $disposition = $inline ? 'inline' : 'attachment';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($filename) . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('X-Content-Type-Options: nosniff');

        // Clear output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        readfile($filePath);
        exit;
    }
}
