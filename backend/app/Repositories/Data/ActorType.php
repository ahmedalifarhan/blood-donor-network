<?php

namespace App\Repositories\Data;

enum ActorType: string
{
    case System = 'System';
    case Scheduler = 'Scheduler';
    case Operator = 'Operator';
    case Anonymous = 'Anonymous';
}
