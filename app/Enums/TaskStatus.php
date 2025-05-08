<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Starting = 'Starting';
    case Processing = 'Processing';
    case Succeeded = 'Succeeded';
    case Failed = 'Failed';
    case Cancelled = 'Cancelled';
}