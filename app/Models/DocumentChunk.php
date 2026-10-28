<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class DocumentChunk extends Model
{
    protected $fillable = [
        'source_name',
        'chunk_index',
        'content',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }
}
