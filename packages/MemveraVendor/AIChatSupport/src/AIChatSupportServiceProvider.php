<?php

namespace MemveraVendor\AIChatSupport;

use Illuminate\Support\ServiceProvider;

class AIChatSupportServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish configuration files
        $this->publishes([
            __DIR__ . '/config/aichatsupport.php' => config_path('aichatsupport.php'),
        ]);
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/aichatsupport.php', 'aichatsupport'
        );
    }
}
