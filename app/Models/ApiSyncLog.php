<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiSyncLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'action', 'order_id', 'request_summary', 'response_summary', 'success', 'created_at',
    ];

    protected function casts(): array
    {
        return ['success' => 'boolean', 'created_at' => 'datetime'];
    }
}
