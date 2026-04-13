<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceLog extends Model
{
    protected $table = 'finance_logs';

    protected $fillable = [
        'reference_number',
        'type',
        'category',
        'financial_account_id',
        'order_id',
        'customer_id',
        'amount',
        'payment_method',
        'transaction_date',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'metadata' => 'array', // PostgreSQL JSONB
    ];

    protected static function booted(): void
    {
        static::creating(function (FinanceLog $log) {
            if (empty($log->reference_number)) {
                $log->reference_number = static::generateReferenceNumber($log->type);
            }
        });
    }

    public static function generateReferenceNumber(string $type): string
    {
        $prefix = $type === 'income' ? 'RCV-' : 'PAY-';
        $prefix .= date('Ymd');

        $lastLog = static::where('reference_number', 'like', $prefix . '%')
            ->orderBy('reference_number', 'desc')
            ->first();

        if ($lastLog) {
            $lastNumber = (int) substr($lastLog->reference_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public const TYPES = [
        'income' => 'Pemasukan',
        'expense' => 'Pengeluaran',
    ];

    public const CATEGORIES = [
        'payment' => 'Pembayaran Order',
        'loan' => 'Pinjam dari Uang Sendiri',
        'return' => 'Kembalian dari Beli (Tambah stok, dll)',
        'balancing' => 'Balancing Uang di Web dan Rekening',
        'consignment' => 'Uang titipan beli (Wortel, daun bawang, dll)',
        'profit' => 'Ambil Profit',
        'purchase' => 'Pembelian Stok',
        'operational' => 'Biaya Operasional',
        'salary' => 'Gaji Karyawan',
        'supplies' => 'Perlengkapan (Plastik, dll)',
        'food' => 'Uang Makan',
        'refund' => 'Refund',
        'other' => 'Lain-lain',
    ];

    public const PAYMENT_METHODS = [
        'cash' => 'Tunai',
        'transfer' => 'Transfer',
        'other' => 'Lainnya',
    ];
}
