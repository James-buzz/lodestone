<p align="center">
  <a href="https://lodestonephp.com"><img src="docs/banner.png" alt="Lodestone. Server-driven UI for Laravel. Dashboards and portals, built from the backend. Beside it, a support panel drawn by Lodestone."></a>
</p>

<p align="center">
  <a href="https://github.com/James-buzz/lodestone/actions/workflows/tests.yml"><img src="https://github.com/James-buzz/lodestone/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://packagist.org/packages/lodestone/lodestone"><img src="https://img.shields.io/packagist/v/lodestone/lodestone?include_prereleases&label=version" alt="Latest version"></a>
  <a href="https://packagist.org/packages/lodestone/lodestone"><img src="https://img.shields.io/packagist/dependency-v/lodestone/lodestone/php" alt="PHP version"></a>
  <a href="https://packagist.org/packages/lodestone/lodestone"><img src="https://img.shields.io/packagist/dependency-v/lodestone/lodestone/laravel/framework?label=laravel" alt="Laravel version"></a>
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

Lodestone is in alpha, so expect breaking changes before 1.0.

```bash
composer require lodestone/lodestone:^0.1@alpha
php artisan lodestone:install
```
