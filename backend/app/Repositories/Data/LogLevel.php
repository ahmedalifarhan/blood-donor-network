<?php

namespace App\Repositories\Data;

enum LogLevel: string
{
    case Warning = 'warning';
    case Error = 'error';
}
