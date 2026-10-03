<?php

namespace Lodestone\Enums;

enum Tone: string
{
    case Success = 'success';
    case Danger = 'danger';
    case Warning = 'warning';
    case Info = 'info';
    case Gray = 'gray';
}
