<?php

namespace App\Enums;

enum ChunkingMethod: string
{
    case Semantic = 'semantic';
    case Recursive = 'recursive';
}
