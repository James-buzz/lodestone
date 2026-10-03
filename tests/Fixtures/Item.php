<?php

namespace Lodestone\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class Item extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
