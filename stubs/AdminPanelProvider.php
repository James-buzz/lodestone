<?php

namespace App\Providers;

use App\Admin\Pages\UsersPage;
use Illuminate\Support\ServiceProvider;
use Lodestone\Facades\Lodestone;
use Lodestone\Panel;

class AdminPanelProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The first page is the panel's home.
        Lodestone::panel(
            Panel::make('admin')
                ->title('Admin')
                ->middleware(['auth'])
                ->pages([
                    UsersPage::class,
                ]),
        );
    }
}
