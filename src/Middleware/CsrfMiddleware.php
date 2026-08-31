<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Middleware;

use Mengo\IdApproval\Security\CsrfToken;
use Mengo\IdApproval\Support\Request;
use Mengo\IdApproval\Support\Response;

class CsrfMiddleware
{
    public static function handle(Request $request): void
    {
        if ($request->isPost()) {
            $token = $request->post('_csrf_token')
                ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

            if (!CsrfToken::validate($token)) {
                if ($request->isAjax()) {
                    Response::json(['success' => false, 'message' => 'CSRF validation failed or token expired.'], 419);
                }
                Response::error('Invalid or expired security token. Please go back, refresh the page, and try again.', 419);
            }
        }
    }

    /** Alias for handle() — used in router dispatch pattern */
    public static function verify(Request $request): void
    {
        self::handle($request);
    }
}
