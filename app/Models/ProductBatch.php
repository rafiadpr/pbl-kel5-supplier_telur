<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ProductBatch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity_initial' => 'integer',
        'quantity_remaining' => 'integer',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'received_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('quantity_remaining', '>', 0)
                     ->where('is_active', true)
                     ->orderBy('received_at', 'asc');
    }

    public function getBatchLabelAttribute(): string
    {
        return $this->received_at->format('d M') .
               " - Sisa: {$this->quantity_remaining} {$this->product->unit} " .
               "(Rp " . number_format($this->selling_price, 0, ',', '.') . ")";
    }
}
