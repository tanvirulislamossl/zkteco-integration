<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['method', 'path', 'query', 'headers', 'body', 'ip', 'user_agent'])]
class ZktecoRequest extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'query' => 'array',
            'headers' => 'array',
        ];
    }
}
