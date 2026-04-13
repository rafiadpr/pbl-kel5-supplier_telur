<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'purchase_number',
        'transaction_date',
        'supplier_name',
        'financial_account_id',
        'warehouse_id',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Purchase $purchase) {
            if (empty($purchase->purchase_number)) {
                $purchase->purchase_number = static::generatePurchaseNumber();
            }
        });
    }

    public static function generatePurchaseNumber(): string
    {
        $prefix = 'PO-' . date('Ymd');
        $lastPurchase = static::where('purchase_number', 'like', $prefix . '%')
            ->orderBy('purchase_number', 'desc')
            ->first();

        if ($lastPurchase) {
            $lastNumber = (int) substr($lastPurchase->purchase_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }
}
