<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'Pending';
    case Starting = 'Starting';
    case Processing = 'Processing';
    case Succeeded = 'Succeeded';
    case Failed = 'Failed';
    case Cancelled = 'Cancelled';
}
