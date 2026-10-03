<p align="center">
  <a href="https://lodestonephp.com"><img src="docs/banner.png" alt="Lodestone. Server-driven UI for Laravel. Dashboards and portals, built from the backend. Beside it, a support panel drawn by Lodestone."></a>
</p>

You describe what you want on the backend, and Lodestone produces the pages and components you need on the front end, built with Inertia and shadcn. All of your logic stays in PHP.

- List and record pages in a sidebar, with groups and badges
- Tables with search, filters, sort, pagination and header, row and bulk buttons
- Forms in a modal, a slide-over or a section
- Stats with sparklines
- Bar, line and area charts
- Record modals with tabs, sections, fields, markdown and code
- Any React component of your own, registered by name

## When would you use it?

- You need a dashboard, some reporting or a portal, for your team or your customers.
- You'd rather not maintain a React front end for it. shadcn does it all.
- You want a package that isn't tied to Eloquent, stays flexible, and is built for AI to write.

## Install

Lodestone is in alpha, so expect breaking changes before 1.0. It needs PHP 8.2+, Laravel 12.8+ or 13, and Tailwind CSS 4.

```bash
composer require lodestone/lodestone:^0.1@alpha
php artisan lodestone:install
```

The install command adds an `AdminPanelProvider`, an example `UsersPage`, and the panel's frontend entry, then adds the React plugin to your Vite config and installs and builds the Node dependencies. Sign in, then visit `/admin`.

The panel uses the `auth` middleware, so your app needs a `login` route. A Laravel starter kit or Fortify gives you one, or change the middleware in `AdminPanelProvider`.

## Development

TODO
