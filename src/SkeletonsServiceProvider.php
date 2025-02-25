<?php

namespace KovacsLaci\LaravelSkeletons;

use Illuminate\Support\ServiceProvider;
use Laci\Skeletons\Console\Commands\SkeletonsGenerator;

class SkeletonsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the command without singleton
        $this->commands([
            SkeletonsGenerator::class,
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Load translations from package's lang directory
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'skeletons');

        // Publish language files for customization
        $this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/skeletons'),
        ], 'skeletons-lang');
    }
}
