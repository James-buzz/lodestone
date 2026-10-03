<?php

namespace Lodestone\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    private const ENTRY = 'resources/js/lodestone.tsx';

    /**
     * The renderer ships inside the Composer package until it is published to npm.
     */
    private const RENDERER = 'file:vendor/lodestone/lodestone/packages/react';

    protected $signature = 'lodestone:install';

    protected $description = 'Add an admin panel, its frontend entry and its Node dependencies to this app';

    /**
     * Execute the console command.
     */
    public function handle(Filesystem $files): int
    {
        $stubs = __DIR__.'/../../stubs';

        foreach ([
            'lodestone.tsx' => base_path(self::ENTRY),
            'lodestone.css' => resource_path('css/lodestone.css'),
            'AdminPanelProvider.php' => app_path('Providers/AdminPanelProvider.php'),
            'UsersPage.php' => app_path('Admin/Pages/UsersPage.php'),
        ] as $stub => $target) {
            $relative = str_replace(base_path().'/', '', $target);

            if ($files->exists($target)) {
                $this->components->warn("Skipped {$relative}: it already exists.");

                continue;
            }

            $files->ensureDirectoryExists(dirname($target));
            $files->copy("{$stubs}/{$stub}", $target);
            $this->components->info("Created {$relative}.");
        }

        ServiceProvider::addProviderToBootstrapFile('App\Providers\AdminPanelProvider');

        $this->updateViteConfig($files);

        if (confirm('Install and build the Node dependencies?', default: true) && ! $this->installNodeDependencies()) {
            return self::FAILURE;
        }

        if (! Route::has('login')) {
            $this->components->warn('The panel uses the `auth` middleware, but this app has no `login` route. Add a starter kit or Fortify, or change the middleware in AdminPanelProvider.');
        }

        $this->components->info('Lodestone is installed. Sign in, then visit /admin.');

        return self::SUCCESS;
    }

    /**
     * Add the panel entry and the React plugin to the Vite config, or say what to add when it can't be patched.
     */
    private function updateViteConfig(Filesystem $files): void
    {
        $path = collect(['vite.config.js', 'vite.config.ts', 'vite.config.mjs'])
            ->map(fn (string $name) => base_path($name))
            ->first(fn (string $path) => $files->exists($path));

        $config = $path ? $files->get($path) : '';

        if (str_contains($config, self::ENTRY)) {
            return;
        }

        $config = preg_replace('/\binput:\s*\[/', "$0'".self::ENTRY."', ", $config, 1, $inputs);
        $plugins = 1;

        if (! str_contains($config, '@vitejs/plugin-react')) {
            $config = preg_replace('/\bplugins:\s*\[/', "$0\n        react(),", "import react from '@vitejs/plugin-react';\n".$config, 1, $plugins);
        }

        if (! $path || ! $inputs || ! $plugins) {
            $this->components->warn("Couldn't update your Vite config. Add the React plugin and the panel entry yourself:");
            $this->line("    import react from '@vitejs/plugin-react'");
            $this->line("    laravel({ input: [/* your entries */, '".self::ENTRY."'] }), react(),");

            return;
        }

        $files->put($path, $config);
        $this->components->info('Added the panel entry and the React plugin to '.basename($path).'.');
    }

    /**
     * Install the renderer and its peers with the app's package manager, then build.
     */
    private function installNodeDependencies(): bool
    {
        [$add, $run] = match (true) {
            file_exists(base_path('pnpm-lock.yaml')) => ['pnpm add', 'pnpm run'],
            file_exists(base_path('yarn.lock')) => ['yarn add', 'yarn run'],
            file_exists(base_path('bun.lock')), file_exists(base_path('bun.lockb')) => ['bun add', 'bun run'],
            default => ['npm install', 'npm run'],
        };

        foreach ([
            "{$add} ".self::RENDERER.' @inertiajs/react react react-dom',
            "{$add} -D @vitejs/plugin-react",
            "{$run} build",
        ] as $command) {
            $this->components->info($command);

            $result = Process::path(base_path())->forever()->run($command, fn (string $type, string $output) => $this->output->write($output));

            if ($result->failed()) {
                $this->components->error("`{$command}` failed. Fix the error above, then run it again.");

                return false;
            }
        }

        return true;
    }
}
