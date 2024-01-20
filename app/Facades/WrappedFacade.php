<?php

namespace SkyBlueSofa\WrappedFacade\Facades;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class WrappedFacade extends Facade
{
    /**
     * Wrap standard facade calls with before/after functionality
     *
     * @return mixed
     *
     * @throws Throwable
     */
    public static function __callStatic($method, $args)
    {
        try {
            $results = static::callFlowMethod('pre', $method, [$args]) ?? null;

            // If the results are NULL, we'll run the Facade method
            // If the results are a callable, we'll run it and get the results
            if (! is_callable($results)) {
                static::logFlow('on', $method, $args);
                $results = parent::__callStatic($method, $args);
            } else {
                static::logFlow('skip', $method, $args);
                $results = $results($method, $args);
            }

            $results = static::callFlowMethod('post', $method, [$args, $results]) ?? $results;
        } catch (Throwable $e) {
            Log::error(static::class.'::'.$method.' call threw exception');
            throw $e;
        }

        return $results;
    }

    protected static function callFlowMethod(string $prefix, $method, $args): mixed
    {
        if (! static::hasFlowMethod($prefix, $method)) {
            return null;
        }

        static::logFlow(static::buildFlowPrefixes()[$prefix], $method, $args);

        return call_user_func_array(
            [static::class, static::getFlowMethodName($prefix, $method)],
            $args
        );
    }

    protected static function hasFlowMethod(string $prefix, $method): bool
    {
        return method_exists(static::class, static::getFlowMethodName($prefix, $method));
    }

    protected static function buildFlowPrefixes(): array
    {
        $prefixes = Config::get('wrapped-facade.prefixes');
        $facadePrefixes = (property_exists(static::class, 'flowPrefixes')) ?
            static::$flowPrefixes :
            [];

        $prefixes['pre'] = $prefixes['pre'] ?? ($facadePrefixes['pre'] ?? 'pre');
        $prefixes['post'] = $prefixes['post'] ?? ($facadePrefixes['post'] ?? 'post');

        return $prefixes;
    }

    protected static function getFlowMethodName(string $prefix, string $methodName): string
    {
        return $prefix.ucfirst($methodName);
    }

    protected static function flowShouldLog(): bool
    {
        $loggedEnvironments = Config::get('wrapped-facade.log_in_environment');
        $currentEnvironment = Config::get('app.env');

        return (
            ($loggedEnvironments === '*') ||
            ($loggedEnvironments === $currentEnvironment) ||
            (is_array($loggedEnvironments) && in_array($currentEnvironment, $loggedEnvironments))
        ) ? true : false;
    }

    protected static function logFlow(string $step, string $methodName, array $args): void
    {
        if (static::flowShouldLog()) {
            Log::info(static::class.'::'.static::getFlowMethodName($step, $methodName).' called');
        }
    }
}
