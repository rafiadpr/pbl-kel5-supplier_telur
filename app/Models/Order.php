<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /**
     * Valid payment statuses for orders.
     */
    public const PAYMENT_STATUSES = [
        'unpaid'    => 'Belum Bayar',
        'partial'   => 'Sebagian',
        'paid'      => 'Lunas',
        'whitewash' => 'Pemutihan (Write-off)',
    ];

    /**
     * Get the human-readable label for payment_status.
     */
    public function getPaymentStatusLabelAttribute(): string
    {
        return static::PAYMENT_STATUSES[$this->payment_status] ?? $this->payment_status;
    }

    /**
     * Scope: exclude whitewash orders from revenue/reporting queries.
     */
    public function scopeExcludeWhitewash(Builder $query): Builder
    {
        return $query->where('payment_status', '!=', 'whitewash');
    }

    /**
     * Check if this order has been whitewashed.
     */
    public function isWhitewash(): bool
    {
        return $this->payment_status === 'whitewash';
    }

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'warehouse_id',
        'delivery_trip_id',
        'order_date',
        'due_date',
        'distance_km',
        'cost_per_km',
        'operational_cost',
        'subtotal',
        'discount',
        'total',
        'total_hpp',
        'net_profit',
        'payment_status',
        'paid_amount',
        'status',
        'notes',
        'metadata', // PostgreSQL JSONB
    ];

    protected $casts = [
        'order_date' => 'date',
        'due_date' => 'date',
        'distance_km' => 'decimal:2',
        'cost_per_km' => 'decimal:2',
        'operational_cost' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'total_hpp' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'metadata' => 'array', // PostgreSQL JSONB
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->invoice_number)) {
                $order->invoice_number = static::generateInvoiceNumber();
            }
        });
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Ymd');
        $lastOrder = static::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->invoice_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function deliveryTrip(): BelongsTo
    {
        return $this->belongsTo(DeliveryTrip::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function financeLogs(): HasMany
    {
        return $this->hasMany(FinanceLog::class);
    }

    // Alias for backward compatibility
    public function transactions(): HasMany
    {
        return $this->financeLogs();
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('subtotal');
        $this->total_hpp = $this->items->sum(fn($item) => $item->locked_hpp * $item->quantity);
        $this->total = $this->subtotal - $this->discount;
        $this->operational_cost = $this->distance_km * $this->cost_per_km;
        $this->net_profit = $this->total - $this->total_hpp - $this->operational_cost;
    }

    public function updatePaymentStatus(): void
    {
        $totalPaid = $this->transactions()
            ->where('type', 'income')
            ->where('category', 'payment')
            ->sum('amount');

        $this->paid_amount = $totalPaid;

        if ($totalPaid >= $this->total) {
            $this->payment_status = 'paid';
        } elseif ($totalPaid > 0) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'unpaid';
        }

        $this->save();
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0, $this->total - $this->paid_amount);
    }
}
