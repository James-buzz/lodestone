# Lodestone

Server-driven UI for Laravel. Pages, tables, forms and buttons are PHP builders, serialised to JSON and drawn by React + shadcn/ui through Inertia. A click or a submit calls the developer's callback on the server.

## Layout

- `src/` — the package, namespace `Lodestone\`. `Components/` are page nodes: `toSchema()` returns a JSON node with a `type`. `Forms/` holds fields and form layouts, `Tables/` columns and filters, `Enums/` the fixed vocabularies (`Tone`, `Size`, `Overlay`), `Facades/Lodestone` the public entry point over `LodestoneManager`, `Http/` controllers and middleware, `Support/` internals the app never imports.
- `packages/react/src/` — the renderer. `protocol.ts` mirrors every PHP `toSchema()`, `registry.ts` maps a node `type` to a component, `ui/` is vendored shadcn/ui.
- `stubs/` — files `lodestone:install` copies into an app.
- `tests/` — PHPUnit, the event path only.

## House style

Write it the way the Laravel team writes `laravel/pulse` and `laravel/prompts`.

**Classes.** Open for extension: no `final`. Apps extend builders. Fluent `make()` constructors; setters return `static`. Native types on every signature; `readonly` where a value never changes. A fixed vocabulary is one shared enum (`Tone`, `Size`, `Overlay`) rather than a `const` list of strings.

**Docblocks.** Every method has one. It opens with a one-line description in the imperative — "Get the …", "Set the …", "Determine whether …", "Register the …", "Build the …" — and that line is often the whole docblock. Native types carry the signature; `@param` and `@return` appear only for array shapes and generics (`list<Button>`, `array<string, mixed>`). A typed property needs a docblock only for an array shape.

**Comments.** Full sentences with a full stop. A comment says why. The code says what, so a comment that narrates the next line is deleted.

**Naming.** Classes are nouns (`Table`, `TextColumn`, `EventMap`). Methods are verbs or adjectives: setters read as configuration (`query()`, `sortable()`, `required()`), conditions take `bool|Closure` (`visible()`, `disabled()`), events are single verbs taking a callback (`click()`, `submit()`). Getters are `getX()`; predicates are `isX()`, `hasX()`, `canX()`. JSON keys are `snake_case`.

**Formatting.** Pint's `laravel` preset and Prettier (`.prettierrc`). Run `composer lint` and `npm run format` after editing.

## Rules

- **Protocol changes go both sides.** A new or changed node means the PHP `toSchema()`, the type in `protocol.ts`, the React component and its registry entry. Bump `Lodestone::PROTOCOL` on breaking changes.
- **Everything is a builder; events are single-word verbs taking a callback.** Lodestone never wraps a callback in its own pipeline.
- **Closures run only on the server.** Lodestone finds them by rebuilding the page and matching a key, never by sending them to the browser.
- **Stateless and serialisable.** No component state on the server between requests. UI conditions are data the renderer evaluates. Server round trips are explicit event URLs.
- **Code over magic.** Nothing is inferred from a model: no implicit `Model::create($request->all())`. The developer writes the callback; helpers are opt-in.
- **Tests cover the event path only.** `tests/EventTest.php` covers `EventController`, `Callback`, `EventMap` and `Field::validationRules`, where the security claims live. Extend it when those change; tests elsewhere wait for the API to settle.

## Before finishing

`composer lint`, `composer test`, `npm run format` and `npm run typecheck`, all clean.
