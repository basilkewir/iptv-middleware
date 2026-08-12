<?php

namespace App\Enums\Stream;

enum StreamStatus: string
{
    case STARTING = 'starting';
    case ACTIVE = 'active';
    case STOPPING = 'stopping';
    case STOPPED = 'stopped';
    case FAILED = 'failed';
}