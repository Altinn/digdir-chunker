<?php

namespace App\Enums;

enum ChunkType: string
{
    case Paragraph = 'paragraph';
    case Heading = 'heading';
    case Table = 'table';
    case Image = 'image';
    case Text = 'text';
}