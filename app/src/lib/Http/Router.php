<?php


namespace App\lib\Http;

use App\lib\Controllers\AbstractController;

class Router
{

    private const string ROUTE_CONFIG_PATH = __DIR__ . '/../../../config/routes.json';
    private const string CONTROLLER_NAMESPACE = 'App\\Controllers\\';

    public function main(Request $request)
    {
        $routes = $this->getRoutes();
        $uriFoundFlag = false;
        $methodFoundFlag = false;

        $response = new Response();

        foreach ($routes as $route) {
            $this->validateConfig($route);

            $routeParams = $this->matchUri($request->getUri(), $route['uri']);
            if ($routeParams === null) {
                continue;
            }
            $uriFoundFlag = true;

            if ($this->checkMethod($request, $route['method']) === false) {
                continue;
            }
            $methodFoundFlag = true;

            try {
                $request->setRouteParams($routeParams);
                $controllerInstance = $this->getValidControllerInstance($route['controller']);

                return $controllerInstance->process($request);
            } catch (\Exception $e) {
                continue;
            }
        }

        if ($uriFoundFlag === true && $methodFoundFlag === false) {
            $response->statusCode = 405;
            $response->headers = ['Content-Type' => 'application/json'];
            $response->content = json_encode(['error' => 'Method not allowed'], JSON_THROW_ON_ERROR);

            return $response;
        }

        $response->statusCode = 404;
        $response->content = "route note found";

        return $response;
    }

    private function getRoutes(): array
    {
        if (file_exists(self::ROUTE_CONFIG_PATH) === false) {
            echo "Le fichier de config des routes n'a pas été trouvé";
            exit();
        }

        $routesConfigContent = file_get_contents(self::ROUTE_CONFIG_PATH);

        return json_decode($routesConfigContent, true, 512, JSON_THROW_ON_ERROR);
    }

    private function validateConfig(array $route): void
    {
        if (array_key_exists('uri', $route) === false || array_key_exists('method', $route) === false) {
            throw new \Exception(500, 'Le fichier de config est mal écrit');
        }
    }

    private function matchUri(string $requestUri, string $routeUri): ?array
    {
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $routeUri, $matches, PREG_OFFSET_CAPTURE);

        $pattern = '';
        $offset = 0;
        foreach ($matches[0] as $index => [$placeholder, $placeholderOffset]) {
            $pattern .= preg_quote(substr($routeUri, $offset, $placeholderOffset - $offset), '#');
            $name = $matches[1][$index][0];
            $pattern .= '(?P<' . $name . '>[^/]+)';
            $offset = $placeholderOffset + strlen($placeholder);
        }
        $pattern .= preg_quote(substr($routeUri, $offset), '#');

        if (preg_match('#^' . $pattern . '$#', $requestUri, $values) !== 1) {
            return null;
        }

        $params = [];
        foreach ($matches[1] as [$name]) {
            $params[$name] = rawurldecode($values[$name]);
        }

        return $params;
    }

    private function checkMethod(Request $request, string $method): bool
    {
        return $request->getMethod() === $method;
    }

    private function getValidControllerInstance(string $routeController): AbstractController
    {

        $controllerName = self::CONTROLLER_NAMESPACE . $routeController;

        if (class_exists($controllerName) === false) {
            throw new \Exception('Controller ' . $controllerName . ' not found.');
        }

        $controllerInstance = new $controllerName();

        if (is_subclass_of($controllerInstance, AbstractController::class) === false) {
            throw new \Exception('Controller ' . $controllerName . ' must extends App\Controllers\AbstractController.');
        }

        return $controllerInstance;
    }
}
