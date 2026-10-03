<?php

namespace App\Admin\Pages;

use App\Models\User;
use Lodestone\Components\Button;
use Lodestone\Components\Stat;
use Lodestone\Page;
use Lodestone\Tables\Columns\TextColumn;
use Lodestone\Tables\Table;
use Lodestone\Ui;

class UsersPage extends Page
{
    protected static string $icon = 'users';

    /**
     * Get the stats shown above the table.
     */
    public function stats(): array
    {
        return [
            Stat::make('Users', fn () => User::count()),
            Stat::make('New this week', fn () => User::where('created_at', '>=', now()->subWeek())->count())->info(),
        ];
    }

    /**
     * Get the users table.
     */
    public static function table(): Table
    {
        return Table::make('users')
            ->query(User::query()->latest())
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('created_at')->relative()->sortable(),
            ])
            ->rowButtons([
                Button::make('verify')->label('Mark verified')->icon('circle-check')
                    ->visible(fn (User $user) => $user->email_verified_at === null)
                    ->click(function (User $user, Ui $ui) {
                        $user->forceFill(['email_verified_at' => now()])->save();
                        $ui->toast("Marked {$user->name} as verified.");
                    }),
            ]);
    }
}
