<?php

namespace SkyBlueSofa\WrappedFacade;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class WrappedFacadeProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/wrapped-facade.php' => config_path('wrapped-facade.php'),
        ], 'wrapped-facade');
    }
}
