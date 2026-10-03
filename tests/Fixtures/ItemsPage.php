<?php

namespace Lodestone\Tests\Fixtures;

use Illuminate\Support\Collection;
use Lodestone\Components\Button;
use Lodestone\Components\Section;
use Lodestone\Forms\Components\Select;
use Lodestone\Forms\Components\Textarea;
use Lodestone\Forms\Form;
use Lodestone\Page;
use Lodestone\Tables\Columns\TextColumn;
use Lodestone\Tables\Table;
use Lodestone\Ui;

/**
 * One page with every kind of event target the tests need. Callbacks record what ran in $calls.
 */
final class ItemsPage extends Page
{
    /** @var list<mixed> */
    public static array $calls = [];

    /**
     * Get the buttons in the page header: one hidden, one disabled, one that visits a page.
     */
    public function buttons(): array
    {
        return [
            Button::make('hidden')->visible(false)->click(fn () => self::$calls[] = 'hidden'),
            Button::make('locked')->disabled(true, 'Locked')->click(fn () => self::$calls[] = 'locked'),
            Button::make('goto')->click(fn (Ui $ui) => $ui->visit(self::class)),
        ];
    }

    /**
     * Get the page's components: the table, a section form with a condition, and a custom node.
     */
    public function schema(): array
    {
        return [
            self::table(),
            Section::make('Note')->form(
                Form::make('note', [
                    Select::make('reason')->options(['weather' => 'Weather', 'other' => 'Other'])->required(),
                    Textarea::make('details')->visibleWhen('reason', 'other'),
                ])->submit(fn (array $data) => self::$calls[] = ['note', $data]),
            ),
            new CardNode(Button::make('ping')->click(fn () => self::$calls[] = 'ping')),
        ];
    }

    /**
     * Get the items table, whose query excludes archived items.
     */
    public static function table(): Table
    {
        return Table::make('items')
            ->query(Item::where('status', '!=', 'archived'))
            ->columns([TextColumn::make('status')])
            ->rowButtons([
                Button::make('touch')
                    ->visible(fn (Item $item) => $item->status === 'open')
                    ->click(fn (Item $item) => self::$calls[] = ['touch', $item->id]),
                Button::make('many')->click(fn (Collection $items) => self::$calls[] = 'many'),
            ]);
    }
}
