<?php

namespace App\Models;

use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Model;

class Chunk extends Model
{
    protected $guarded = [];

    protected $casts = [
        'page_numbers' => 'array',
    ];

    public static function boot()
    {
        parent::boot();
    }

    /*
    public static function create(array $attributes)
    {
        $value = DB::statement("INSERT INTO `chunks` (`file_id`, `type`, `text`, `chunk_number`, `page_number`, `embedding`) VALUES (?, ?, ?, ?, ?, Vec_FromText(?))", [
            $attributes['file_id'] ?? null,
            $attributes['type'] ?? null,
            $attributes['text'] ?? null,
            $attributes['chunk_number'] ?? null,
            $attributes['page_number'] ?? null,
            $attributes['created_at'] = Carbon::now(),
            $attributes['updated_at'] = Carbon::now(),
            json_encode($attributes['embedding']),
        ]);        

        return static::find(DB::getPdo()->lastInsertId());
    }
    */

}