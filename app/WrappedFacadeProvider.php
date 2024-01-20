<?php

namespace SkyBlueSofa\WrappedFacade;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class WrappedFacadeProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-wrapped-facade')
            ->hasConfigFile();
    }
}
