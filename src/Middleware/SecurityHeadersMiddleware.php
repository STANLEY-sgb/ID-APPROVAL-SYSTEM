<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Middleware;

use Mengo\IdApproval\Support\Request;

class SecurityHeadersMiddleware
{
    public static function handle(Request $request): void
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
        
        // Content Security Policy allowing local assets, PDF preview embeds, inline scripts/styles for UI interactivity
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; img-src 'self' data: blob:; frame-src 'self' blob:; object-src 'self' blob:;");
    }

    public static function apply(): void
    {
        self::handle(new Request());
    }
}
