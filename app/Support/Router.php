<?php
namespace App\Support;

/**
 * Simple Router for Web Pages
 */
class Router
{
    private $routes = [];

    public function get($path, $callback)
    {
        $this->routes['GET'][$this->formatPath($path)] = $callback;
    }

    public function post($path, $callback)
    {
        $this->routes['POST'][$this->formatPath($path)] = $callback;
    }

    public function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->formatPath(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

        // Tìm route khớp (hỗ trợ cả static và dynamic đơn giản)
        foreach ($this->routes[$method] ?? [] as $routePath => $callback) {
            if ($routePath === $path) {
                return $this->execute($callback);
            }
        }

        // Nếu không khớp, trả về 404
        http_response_code(404);
        include D_ROOT . '/error.php';
    }

    private function formatPath($path)
    {
        return '/' . trim($path, '/');
    }

    private function execute($callback)
    {
        if (is_array($callback)) {
            $controller = new $callback[0]();
            $method = $callback[1];
            return $controller->$method();
        }
        return $callback();
    }
}

