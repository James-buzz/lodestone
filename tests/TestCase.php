<?php

namespace Lodestone\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Lodestone\Facades\Lodestone;
use Lodestone\LodestoneServiceProvider;
use Lodestone\Panel;
use Lodestone\Tests\Fixtures\ItemsPage;
use Orchestra\Testbench\TestCase as Testbench;

abstract class TestCase extends Testbench
{
    /**
     * Get the package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [InertiaServiceProvider::class, LodestoneServiceProvider::class];
    }

    /**
     * Define the environment, registering the panel before boot as an app's provider would.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('session.driver', 'array');

        Lodestone::panel(Panel::make('admin')->pages([ItemsPage::class]));
    }

    /**
     * Set up the test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('status');
        });

        ItemsPage::$calls = [];
    }
}
