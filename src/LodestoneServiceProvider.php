<?php

namespace Lodestone;

use Illuminate\Support\ServiceProvider;

class LodestoneServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LodestoneManager::class);

        // One Ui per request, and per job under Octane, so callbacks and the code they call share it.
        $this->app->scoped(Ui::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'lodestone');

        if ($this->app->runningInConsole()) {
            $this->commands([Console\InstallCommand::class]);
        }

        // Panels are registered in the app's own providers, which may boot after this one.
        $this->app->booted(function () {
            if (! $this->app->routesAreCached()) {
                $manager = $this->app->make(LodestoneManager::class);

                array_map($manager->routes(...), $manager->panels());
            }
        });
    }
}
