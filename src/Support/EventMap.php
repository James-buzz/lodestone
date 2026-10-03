<?php

namespace Lodestone\Support;

use Lodestone\Components\Button;
use Lodestone\Components\Component;
use Lodestone\Forms\Form;
use Lodestone\Tables\Table;
use LogicException;

/**
 * Every button and form on a built page, by key. A closure can't travel to the browser, so when
 * a click or submit arrives, Lodestone builds the page again and looks the key up here:
 *
 *   buttons.{name}                     a page header button
 *   {table}.header|row|bulk.{name}     a table's buttons
 *   form.{form key}                    a form in a section
 *
 * Nodes name their own targets through Component::targets(). A button's form shares the button's
 * key. Two builders with one key would be ambiguous, so building the map throws instead.
 *
 * @internal
 */
class EventMap
{
    /** @var array<string, array{slot: 'page'|'header'|'row'|'bulk'|'section', button: ?Button, form: ?Form, table: ?Table}> */
    private array $targets = [];

    /**
     * Create a new map from the page's schema and header buttons.
     *
     * @param  array<int, Component|null>  $schema
     * @param  array<int, Button|null>  $buttons
     */
    public function __construct(array $schema, array $buttons)
    {
        foreach (array_filter($buttons) as $button) {
            $this->add("buttons.{$button->getName()}", 'page', $button);
        }

        $this->walk($schema);
    }

    /**
     * Find the target with the given key.
     *
     * @return array{slot: 'page'|'header'|'row'|'bulk'|'section', button: ?Button, form: ?Form, table: ?Table}|null
     */
    public function find(string $key): ?array
    {
        return $this->targets[$key] ?? null;
    }

    /**
     * Add the targets of the given components and their children.
     *
     * @param  array<int, Component|null>  $components
     */
    private function walk(array $components): void
    {
        foreach (array_filter($components) as $component) {
            $targets = $component->targets();
            $children = $component->children();

            // A leaf with nothing to click is skipped before isVisible(), whose closure may cost a query.
            // Hidden components aren't drawn, so nothing in them can be clicked.
            if (($targets === [] && $children === []) || ! $component->isVisible()) {
                continue;
            }

            foreach ($targets as $key => $target) {
                $this->add($key, $target['slot'], $target['button'] ?? null, $target['table'] ?? null, $target['form'] ?? null);
            }

            $this->walk($children);
        }
    }

    /**
     * Add a target, refusing a key the map already has.
     */
    private function add(string $key, string $slot, ?Button $button = null, ?Table $table = null, ?Form $form = null): void
    {
        if (isset($this->targets[$key])) {
            throw new LogicException("Two buttons or forms on this page have the key [{$key}], so a click couldn't tell them apart. Rename one, or give one of the tables another key().");
        }

        $this->targets[$key] = ['slot' => $slot, 'button' => $button, 'form' => $form ?? $button?->getForm(), 'table' => $table];
    }
}
