<?php

namespace SkyBlueSofa\WrappedFacade\Facades;

use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            $results = static::callSideloadedMethods('pre', $method, [$args]) ?? null;

            // If the results are NULL, we'll run the Facade method
            // If the results are a callable, we'll run it and get the results
            if (! is_callable($results)) {
                static::logSideloadedMethodCalled($method, $args, false);
                $results = parent::__callStatic($method, $args);
            } else {
                static::logSideloadedMethodCalled($method, $args);
                $results = $results($method, $args);
            }

            $results = static::callSideloadedMethods('post', $method, $args, $results) ?? $results;
        } catch (Throwable $e) {
            Log::error(static::class.'::'.$method.' call threw exception');
            throw $e;
        }

        return $results;
    }

    protected static function callSideloadedMethods(string $prefix, $method, $args, $results = null): mixed
    {
        $prefix = static::buildSideloadedPrefixes()[$prefix];

        $baseSideloadedMethodName = static::getBaseSideloadedMethodName($prefix, $method);
        $sideloadedMethods = static::sortSideloadedPrefixMethodsNames($baseSideloadedMethodName, array_filter(
            get_class_methods(static::class),
            function ($value) use ($baseSideloadedMethodName) {
                return Str::startsWith($value, $baseSideloadedMethodName);
            }
        ));

        foreach ($sideloadedMethods as $sideloadedMethodName) {
            static::logSideloadedMethodCalled($sideloadedMethodName, $args);
            $results = call_user_func_array(
                [static::class, $sideloadedMethodName],
                [$args, $results]
            );
        }

        return $results;
    }

    protected static function buildSideloadedPrefixes(): array
    {
        if (! $prefixes = Cache::get('wrapped-facade.prefixes')) {
            $prefixes = Config::get('wrapped-facade.prefixes');
            $facadePrefixes = (property_exists(static::class, 'sideloadedPrefixes')) ?
                get_class_vars(static::class)['sideloadedPrefixes'] :
                [];

            $prefixes['pre'] = $prefixes['pre'] ?? ($facadePrefixes['pre'] ?? 'pre');
            $prefixes['post'] = $prefixes['post'] ?? ($facadePrefixes['post'] ?? 'post');

            Cache::set('wrapped-facade.prefixes', $prefixes);
        }

        return $prefixes;
    }

    protected static function sortSideloadedPrefixMethodsNames(string $prefix, array $sideloadedMethods): array
    {
        if (! property_exists(static::class, 'sideloadedMethodOrder')) {
            return $sideloadedMethods;
        }

        $sideloadedMethodOrder = get_class_vars(static::class)['sideloadedMethodOrder'];
        if (! is_array($sideloadedMethodOrder)) {
            throw new DomainException(static::class.'::sideloadedMethodOrder expects an array.');
        }

        if (isset($sideloadedMethodOrder[$prefix]) && is_array($sideloadedMethodOrder[$prefix])) {
            $sideloadedMethodOrder = $sideloadedMethodOrder[$prefix];
        }

        $sideloadedMethodOrder = array_filter($sideloadedMethodOrder, function ($value) use ($prefix) {
            if (is_array($value)) {
                return false;
            }

            return Str::startsWith($value, $prefix);
        });

        $sideloadedMethods = array_merge(
            array_intersect($sideloadedMethodOrder, $sideloadedMethods),
            array_diff($sideloadedMethods, $sideloadedMethodOrder),
        );

        return $sideloadedMethods;
    }

    protected static function getBaseSideloadedMethodName(string $prefix, string $methodName): string
    {
        return $prefix.ucfirst($methodName);
    }

    protected static function logSideloadedMethodCalled(
        string $sideloadedMethodName,
        array $args,
        bool $isToBeRun = true
    ): void {
        if (static::shouldLogSideloadedMethodCalls()) {
            Log::info(static::class.'::'.$sideloadedMethodName.' '.($isToBeRun ? 'called' : 'skipped'));
        }
    }

    protected static function shouldLogSideloadedMethodCalls(): bool
    {
        if (is_null($shouldLog = Cache::get('wrapped-facade.shouldLog'))) {
            $loggedEnvironments = Config::get('wrapped-facade.log_in_environment');
            $currentEnvironment = Config::get('app.env');

            $shouldLog = (
                ($loggedEnvironments === '*') ||
                ($loggedEnvironments === $currentEnvironment) ||
                (is_array($loggedEnvironments) && in_array($currentEnvironment, $loggedEnvironments))
            ) ? true : false;

            Cache::set('wrapped-facade.shouldLog', $shouldLog);
        }

        return $shouldLog;
    }
}
