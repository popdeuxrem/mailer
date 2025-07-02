<?php

declare(strict_types=1);

namespace EmailPlatform\Core;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionParameter;

/**
 * Advanced Dependency Injection Container
 * 
 * Manages service registration and resolution with support
 * for singleton, factory patterns, and auto-wiring.
 */
class Container
{
    private array $bindings = [];
    private array $instances = [];
    private array $aliases = [];

    /**
     * Bind a service to the container
     */
    public function bind(string $abstract, $concrete = null, bool $singleton = false): void
    {
        $this->bindings[$abstract] = compact('concrete', 'singleton');
    }

    /**
     * Bind a singleton service
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance
     */
    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Resolve a service from the container
     */
    public function get(string $abstract)
    {
        // Check for alias
        $abstract = $this->aliases[$abstract] ?? $abstract;

        // Return existing instance if available
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Check if it's a registered binding
        if (!isset($this->bindings[$abstract])) {
            return $this->resolve($abstract);
        }

        $binding = $this->bindings[$abstract];
        $concrete = $binding['concrete'];

        // Resolve the concrete implementation
        $object = $this->resolve($concrete ?? $abstract);

        // Store as singleton if required
        if ($binding['singleton']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Resolve a concrete implementation
     */
    private function resolve($concrete)
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        if (is_string($concrete)) {
            return $this->build($concrete);
        }

        return $concrete;
    }

    /**
     * Build a class with dependency injection
     */
    private function build(string $concrete)
    {
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (Exception $e) {
            throw new Exception("Target class [$concrete] does not exist.");
        }

        if (!$reflector->isInstantiable()) {
            throw new Exception("Target [$concrete] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $constructor->getParameters();
        $instances = $this->resolveDependencies($dependencies);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * Resolve method dependencies
     */
    private function resolveDependencies(array $dependencies): array
    {
        $results = [];
        foreach ($dependencies as $dependency) {
            $results[] = $this->resolveDependency($dependency);
        }
        return $results;
    }

    /**
     * Resolve a single dependency
     */
    private function resolveDependency(ReflectionParameter $parameter)
    {
        $type = $parameter->getType();
        
        if ($type && !$type->isBuiltin()) {
            $name = $type->getName();
            return $this->get($name);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new Exception("Cannot resolve dependency [{$parameter->getName()}]");
    }

    /**
     * Check if service is bound
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Get all registered services
     */
    public function getBindings(): array
    {
        return array_merge(
            array_keys($this->bindings),
            array_keys($this->instances)
        );
    }

    /**
     * Clear all bindings and instances
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
    }
}