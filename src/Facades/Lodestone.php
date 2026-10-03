<?php

namespace Lodestone\Facades;

use Illuminate\Support\Facades\Facade;
use Lodestone\LodestoneManager;

/**
 * @method static \Lodestone\Panel panel(\Lodestone\Panel $panel)
 * @method static void routes(\Lodestone\Panel $panel)
 * @method static array<string, \Lodestone\Panel> panels()
 * @method static \Lodestone\Panel get(string $id)
 * @method static int protocol()
 * @method static list<array{id: string, title: string, description: ?string, icon: ?string, url: string, current: bool}> switcher(?\Illuminate\Contracts\Auth\Authenticatable $user, ?\Lodestone\Panel $current)
 * @method static \Lodestone\Panel|null current()
 * @method static string url(string $page, \Illuminate\Database\Eloquent\Model|int|string|null $record = null)
 * @method static string page()
 * @method static \Illuminate\Database\Eloquent\Model|null record()
 * @method static string eventUrl(string $key)
 *
 * @see LodestoneManager
 */
class Lodestone extends Facade
{
    /**
     * The version of the JSON protocol shared with the React renderer.
     */
    public const PROTOCOL = LodestoneManager::PROTOCOL;

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return LodestoneManager::class;
    }
}
