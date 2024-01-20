# Laravel Wrapped-Facade
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
php artisan vendor:publish --tag=wrapped-facade-config
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

#### Example: Using Multiple Sideloaded Functionality

In this example, we're going to validate the results before we cache them. 

We'll update the class to include a new 'postIndexValidate' method and a 'sideloadedMethodOrder' property:

```
<?php
namespace App\Facades;

use App\Fruits;
use App\FruitValidator;
use SkyBlueSofa\WrappedFacade\Facades\WrappedFacade;

class SuperFruits extends WrappedFacade
{
    public static function getFacadeAccessor()
    {
        return Fruits::class;
    }

    protected static $sideloadedMethodOrder = [
        'postIndexValidate',
        'postIndex',
    ];

    protected static function postIndexValidate(array $args, mixed $results): mixed
    {
        // This is an example validator. How it works really doesn't matter.
        $fruitValidator = new FruitValidator($results);

        if (! $fruitValidator->validates()) {
            throw new \RuntimeException('Fruit does not validate!');
        }

        return $results;
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

We're going to assume that `FruitValidator::validates()` will return `false`. So now when you run the facade code, a `\RuntimeException` will be thrown.

```
\App\Classes\SuperFruitsSuperFruits::getAll();

// RuntimeException('Fruit does not validate!');
```

Please note that the `$sideloadedMethodOrder` array can be formatted in multiple ways.
All of these are equally valid:

```
// If you don't care what order things are run in:
// Don't add the $sideloadedMethodOrder property at all, or:
protected static $sideloadedMethodOrder = [];

// If you have only a few sideloaded methods, a simple array might be easiest.
// The array lists the specific sideloaded method names:
protected static $sideloadedMethodOrder = [
    'postIndexValidate',
    'postIndex',
];

// If you have quite a few sideloaded methods, a nested array might be clearer.
// The first level of the array is the sideloaded key (<pre|post> + <methodName>).
// The second level of the array lists the specific sideloaded method names:
protected static $sideloadedMethodOrder = [
    'postIndex' => [
        'postIndexValidate',
        'postIndex',
    ],
];

```
