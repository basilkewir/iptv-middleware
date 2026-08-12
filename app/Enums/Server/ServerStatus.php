<?php

namespace App\Enums\Server;

enum ServerStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case MAINTENANCE = 'maintenance';
    case OFFLINE = 'offline';
}