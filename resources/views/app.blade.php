<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script>
            // Set the theme before paint so dark mode doesn't flash. A block, so `theme` stays out of the global scope.
            {
                let theme = null;
                try { theme = localStorage.getItem('theme'); } catch (e) {} // Blocked storage follows the system.
                document.documentElement.classList.toggle('dark', theme === 'dark' || (theme === null && matchMedia('(prefers-color-scheme: dark)').matches));
            }
        </script>
        @viteReactRefresh
        @vite($viteEntry)
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
