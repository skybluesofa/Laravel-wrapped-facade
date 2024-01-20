<?php

namespace Tests\ExampleClasses;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SkyBlueSofa\WrappedFacade\Facades\WrappedFacade;

class WrappedApiRepository extends WrappedFacade
{
    public static function getFacadeAccessor()
    {
        return ApiRepository::class;
    }

    protected static $apiCachedResults = null;

    protected static $sideloadedMethodOrder = [
        'preIndex' => [
            'preIndexValidate',
            'preIndex',
        ],
        'postIndex',
    ];

    protected static function preIndexValidate(array $args, mixed $results = null): ?callable
    {
        return function () use ($results) {
            return $results;
        };
    }

    /**
     * Runs before we actually hit the ApiRepository::index() method
     */
    protected static function preIndex(array $args, mixed $results = null): ?callable
    {
        // Check if we have the results cached
        $apiCachedResults = Cache::get('ApiRepository.cachedResults');

        // If we can't find results in the cache, return NULL
        if (is_null($apiCachedResults)) {
            Log::info('ApiRepository cache has expired');

            return null;
        }

        // If we found the results, then we'll store it here until we're all done
        Log::info('Returning cached ApiRepository data');
        static::$apiCachedResults = $apiCachedResults;

        // Return a callable with modified/unmodified results, as your case suggests
        return function () use ($apiCachedResults) {
            return $apiCachedResults;
        };
    }

    /**
     * Runs after we have hit the ApiRepository::index() method
     */
    protected static function postIndex(array $args, mixed $results): mixed
    {
        // Back in the 'beforeIndex' method, we stored this if the cache wasn't warm
        if (! static::$apiCachedResults) {
            Log::info('Caching ApiRepository data');

            // Cache the $results for next time
            Cache::set('ApiRepository.cachedResults', $results);

            // Reset the value
            static::$apiCachedResults = null;
        } else {
            Log::info('ApiRepository data was already in the cache');
        }

        // Return the $results
        return $results;
    }
}
