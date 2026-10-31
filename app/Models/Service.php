<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'provider_service_id', 'name', 'description',
        'cost_usd', 'cost_price', 'sell_price', 'sell_price_override',
        'min_qty', 'max_qty', 'speed', 'avg_start_time', 'is_active', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:6',
            'cost_price' => 'integer',
            'sell_price' => 'integer',
            'sell_price_override' => 'boolean',
            'min_qty' => 'integer',
            'max_qty' => 'integer',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
