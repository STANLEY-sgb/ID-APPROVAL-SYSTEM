<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Support;

class NotFoundException extends \RuntimeException {}
class ForbiddenException extends \RuntimeException {}

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method'  => $method,
            'path'    => '/' . ltrim($path, '/'),
            'handler' => $handler,
        ];
    }

    /**
     * Dispatch to the matching route handler.
     *
     * @param string $method   HTTP method
     * @param string $uri      Request URI path (without query string)
     * @throws NotFoundException
     */
    public function dispatch(string $method, string $uri): void
    {
        $uri = '/' . ltrim(parse_url($uri, PHP_URL_PATH) ?? '/', '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            // Convert {param} placeholders to named captures
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = "#^{$pattern}$#";

            if (preg_match($pattern, $uri, $matches)) {
                // Extract only named captures and cast numeric-looking ones
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                $args = [];
                foreach ($params as $k => $v) {
                    $args[] = is_numeric($v) ? (int) $v : $v;
                }

                call_user_func_array($route['handler'], $args);
                return;
            }
        }

        throw new NotFoundException("Route not found: {$method} {$uri}");
    }
}
