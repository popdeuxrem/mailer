<?php

declare(strict_types=1);

namespace EmailPlatform\Core;

use EmailPlatform\Core\Container;
use Exception;

/**
 * Advanced Router with Middleware Support
 * 
 * Handles HTTP routing with parameter extraction,
 * middleware pipeline, and RESTful conventions.
 */
class Router
{
    private Container $container;
    private array $routes = [];
    private array $middleware = [];
    private array $parameters = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register a GET route
     */
    public function get(string $uri, $action): void
    {
        $this->addRoute('GET', $uri, $action);
    }

    /**
     * Register a POST route
     */
    public function post(string $uri, $action): void
    {
        $this->addRoute('POST', $uri, $action);
    }

    /**
     * Register a PUT route
     */
    public function put(string $uri, $action): void
    {
        $this->addRoute('PUT', $uri, $action);
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $uri, $action): void
    {
        $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * Register a PATCH route
     */
    public function patch(string $uri, $action): void
    {
        $this->addRoute('PATCH', $uri, $action);
    }

    /**
     * Add a route to the collection
     */
    private function addRoute(string $method, string $uri, $action): void
    {
        $this->routes[$method][$uri] = [
            'action' => $action,
            'middleware' => [],
            'parameters' => $this->extractParameters($uri)
        ];
    }

    /**
     * Extract parameters from URI pattern
     */
    private function extractParameters(string $uri): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Add middleware to routes
     */
    public function middleware(array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * Dispatch incoming request
     */
    public function dispatch(string $method, string $uri)
    {
        // Find matching route
        $route = $this->findRoute($method, $uri);
        
        if (!$route) {
            http_response_code(404);
            return ['error' => 'Route not found'];
        }

        // Execute middleware pipeline
        $response = $this->executeMiddleware($route);
        if ($response !== null) {
            return $response;
        }

        // Execute route action
        return $this->executeAction($route);
    }

    /**
     * Find matching route
     */
    private function findRoute(string $method, string $uri): ?array
    {
        if (!isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $pattern => $route) {
            $regex = $this->compileRoutePattern($pattern);
            
            if (preg_match($regex, $uri, $matches)) {
                // Extract parameter values
                array_shift($matches); // Remove full match
                $parameters = array_combine($route['parameters'], $matches);
                
                return array_merge($route, ['parameters' => $parameters]);
            }
        }

        return null;
    }

    /**
     * Compile route pattern to regex
     */
    private function compileRoutePattern(string $pattern): string
    {
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        $pattern = str_replace('/', '\/', $pattern);
        return '/^' . $pattern . '$/';
    }

    /**
     * Execute middleware pipeline
     */
    private function executeMiddleware(array $route)
    {
        $middleware = array_merge($this->middleware, $route['middleware']);
        
        foreach ($middleware as $middlewareClass) {
            $instance = $this->container->get($middlewareClass);
            $response = $instance->handle();
            
            if ($response !== null) {
                return $response;
            }
        }

        return null;
    }

    /**
     * Execute route action
     */
    private function executeAction(array $route)
    {
        $action = $route['action'];
        $parameters = $route['parameters'] ?? [];

        if (is_string($action) && strpos($action, '@') !== false) {
            [$controller, $method] = explode('@', $action);
            
            $controllerInstance = $this->container->get($controller);
            
            if (!method_exists($controllerInstance, $method)) {
                throw new Exception("Method {$method} not found in {$controller}");
            }

            return $this->callControllerMethod($controllerInstance, $method, $parameters);
        }

        if (is_callable($action)) {
            return call_user_func_array($action, array_values($parameters));
        }

        throw new Exception('Invalid route action');
    }

    /**
     * Call controller method with dependency injection
     */
    private function callControllerMethod($controller, string $method, array $parameters)
    {
        $reflection = new \ReflectionMethod($controller, $method);
        $dependencies = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            
            // Check if parameter exists in route parameters
            if (isset($parameters[$name])) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            // Try to resolve from container
            $type = $parameter->getType();
            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->container->get($type->getName());
                continue;
            }

            // Use default value if available
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            throw new Exception("Cannot resolve parameter {$name}");
        }

        return $reflection->invokeArgs($controller, $dependencies);
    }

    /**
     * Get current route parameters
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Generate URL for named route
     */
    public function url(string $name, array $parameters = []): string
    {
        // Implementation for named routes would go here
        return '';
    }
}