<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'reference_number',
        'warehouse_id',
        'product_id',
        'product_batch_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'unit_cost',
        'order_id',
        'destination_warehouse_id',
        'reason',
        'notes',
        'movement_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'stock_before' => 'decimal:2',
        'stock_after' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'movement_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement) {
            if (empty($movement->reference_number)) {
                $movement->reference_number = static::generateReferenceNumber($movement->type);
            }
        });

        static::created(function (StockMovement $movement) {
            $movement->updateWarehouseStock();
            
            // Jika transfer, update stok di gudang tujuan
            if ($movement->type === 'transfer' && $movement->destination_warehouse_id) {
                static::createTransferIn($movement);
            }
        });
    }

    public static function generateReferenceNumber(string $type): string
    {
        $prefixes = [
            'in' => 'STK-IN',
            'out' => 'STK-OUT',
            'adjustment' => 'STK-ADJ',
            'transfer' => 'STK-TRF',
        ];
        $prefix = ($prefixes[$type] ?? 'STK') . '-' . date('Ymd');
        
        $lastMovement = static::where('reference_number', 'like', $prefix . '%')
            ->orderBy('reference_number', 'desc')
            ->first();

        if ($lastMovement) {
            $lastNumber = (int) substr($lastMovement->reference_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    public function updateWarehouseStock(): void
    {
        $warehouseProduct = DB::table('warehouse_product')
            ->where('warehouse_id', $this->warehouse_id)
            ->where('product_id', $this->product_id)
            ->first();

        $currentStock = $warehouseProduct->stock ?? 0;
        $this->stock_before = $currentStock;

        $newStock = match($this->type) {
            'in' => $currentStock + $this->quantity,
            'out', 'transfer' => $currentStock - $this->quantity,
            'adjustment' => $this->quantity, // Adjustment sets absolute value
            default => $currentStock,
        };

        $this->stock_after = max(0, $newStock);

        DB::table('warehouse_product')
            ->updateOrInsert(
                ['warehouse_id' => $this->warehouse_id, 'product_id' => $this->product_id],
                ['stock' => $this->stock_after, 'updated_at' => now()]
            );

        $this->saveQuietly();

        // Update HPP rata-rata jika stok masuk
        if ($this->type === 'in' && $this->unit_cost) {
            $this->updateProductHpp();
        }
    }

    protected function updateProductHpp(): void
    {
        $product = $this->product;
        $totalStock = $product->total_stock;
        
        if ($totalStock > 0 && $this->unit_cost) {
            $oldValue = ($totalStock - $this->quantity) * $product->hpp;
            $newValue = $this->quantity * $this->unit_cost;
            $product->hpp = ($oldValue + $newValue) / $totalStock;
            $product->save();
        }
    }

    protected static function createTransferIn(StockMovement $sourceMovement): void
    {
        $destinationStock = DB::table('warehouse_product')
            ->where('warehouse_id', $sourceMovement->destination_warehouse_id)
            ->where('product_id', $sourceMovement->product_id)
            ->first();

        $currentDestinationStock = $destinationStock->stock ?? 0;
        $newDestinationStock = $currentDestinationStock + $sourceMovement->quantity;

        DB::table('warehouse_product')
            ->updateOrInsert(
                ['warehouse_id' => $sourceMovement->destination_warehouse_id, 'product_id' => $sourceMovement->product_id],
                ['stock' => $newDestinationStock, 'updated_at' => now()]
            );
    }

    public const REASONS = [
        'purchase' => 'Pembelian',
        'sale' => 'Penjualan',
        'broken' => 'Telur Pecah',
        'rotten' => 'Telur Busuk',
        'transfer' => 'Transfer Gudang',
        'correction' => 'Koreksi Stok',
        'return' => 'Retur',
        'other' => 'Lain-lain',
    ];
}
