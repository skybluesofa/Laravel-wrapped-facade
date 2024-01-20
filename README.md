# laravel Wrapped-Facade
Add before- and after- method calls to Facades.

## Purpose

This library creates the ability to add pre- and post- methods when calling Facade methods. You can find an example in the `tests` folder.

It is most useful purpose that I have found is to cache information instead of running a request.

## Laravel Version Compatibility

This package supports Laravel `v9` and `v10`

## Installation

```
composer require skybluesofa/laravel-wrapped-facade
```

## Setup

### Install the Config File

#### Publish the Config File

Run the artisan command to publish the package's config file:

```
php artisan vendor:publish --tag=wrapped-facade
```

_or_

#### Copy the Config File
Copy the `wrapped-facade.php` file from the `vendor/skybluesofa/laravel-wrapped-facade/config` folder to your application's `config` folder.

## Using Wrapped Facades

Wrapped Facades work, out-of-the-box, exactly the same as standard (Laravel Facades)[https://laravel.com/docs/10.x/facades]. 

### Basic Facade Functionality

Lets start with the base `Fruits` class:

```
<?php
namespace App\Classes;

class Fruits
{
    protected $data = [
        1 => 'Apple',
        2 => 'Banana',
        3 => 'Carrot',
        4 => 'Durifruit',
        5 => 'Eggplant',
    ];

    public function getAll(): array
    {
        return $this->data;
    }
}

```

And now we'll create a `SuperFruits` facade, though it could be named `Fruits`, if you wanted:

```
<?php
namespace App\Facades;

use App\Fruits;
use SkyBlueSofa\WrappedFacade\Facades\WrappedFacade;

class SuperFruits extends WrappedFacade
{
    public static function getFacadeAccessor()
    {
        return Fruits::class;
    }
}
```

When you run the facade code, you will get a returned array of fruits and vegetables.

```
\App\Classes\SuperFruitsSuperFruits::getAll();

// Returns an array of fruits, just as we'd expect
```

### Sideloading Functionality into the Facade

The difference is when you wish to add some new functionality to the method, before and/or after it gets run.

#### Example: Caching Results 

We'll add a new attribute and a couple of methods:

```
<?php
namespace App\Facades;

use App\Fruits;
use SkyBlueSofa\WrappedFacade\Facades\WrappedFacade;

class SuperFruits extends WrappedFacade
{
    public static function getFacadeAccessor()
    {
        return Fruits::class;
    }

    protected static $cachedFruits = null;

    protected static function preIndex(array $args): ?callable
    {
        $cachedFruits = Cache::get('cachedFruits');
        if (is_null($cachedFruits)) {
            Log::info('Cache has expired');
            return null;
        }

        // If we found the results, then we'll store it here until we're all done
        Log::info('Returning cached results');
        static::$cachedFruits = $cachedFruits;
        return function () use ($cachedFruits) {
            return $cachedFruits;
        };
    }

    protected static function postIndex(array $args, mixed $results): mixed
    {
        if (! static::$cachedFruits) {
            Log::info('Caching fruit data');
            Cache::set('cachedFruits', $results);
            static::$cachedFruits = null;
        } else {
            Log::info('Fruits were already in the cache');
        }
        return $results;
    }
}
```

Now when you run the facade code, you will still get a returned array of fruits and vegetables,
but the next time it's accessed, it will be returned from the cache.

```
\App\Classes\SuperFruitsSuperFruits::getAll();

// Returns an array of fruits, just as we'd expect
```

#### Example: Modifying Results 

In this example, we're not going to cache the results. Instead we're going to filter the results
based on the preferences of the logged in user.

We'll change the class to only have a 'postIndex' method:

```
<?php
namespace App\Facades;

use App\Fruits;
use SkyBlueSofa\WrappedFacade\Facades\WrappedFacade;

class SuperFruits extends WrappedFacade
{
    public static function getFacadeAccessor()
    {
        return Fruits::class;
    }

    protected static function postIndex(array $args, mixed $results): mixed
    {
        // In this example , the user hates 'Banana'
        $hatedFruit = Auth::user()->mostHatedFruit; 

        return array_diff(
            $results,
            [$hatedFruit]
        );
    }
}
```

Now when you run the facade code, you will still get a returned array of fruits and vegetables,
but it 'Banana' has been removed from the array.

```
\App\Classes\SuperFruitsSuperFruits::getAll();

// Returns an array of fruits, sans Banana
[
    1 => 'Apple',
    2 => 'Carrot',
    3 => 'Durifruit',
    4 => 'Eggplant',
]
```
