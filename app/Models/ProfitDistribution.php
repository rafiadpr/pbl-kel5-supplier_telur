<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitDistribution extends Model
{
    protected $fillable = [
        'year',
        'month',
        'total_revenue',
        'total_hpp',
        'total_operational',
        'total_expenses',
        'gross_profit',
        'net_profit',
        'shared_profit_per_kg',
        'total_quantity_sold',
        'total_shared_profit',
        'partners', // PostgreSQL JSONB
        'status',
        'notes',
        'breakdown', // PostgreSQL JSONB
    ];

    protected $casts = [
        'total_revenue' => 'decimal:2',
        'total_hpp' => 'decimal:2',
        'total_operational' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'shared_profit_per_kg' => 'decimal:2',
        'total_quantity_sold' => 'decimal:2',
        'total_shared_profit' => 'decimal:2',
        'partners' => 'array', // PostgreSQL JSONB
        'breakdown' => 'array', // PostgreSQL JSONB
    ];

    // Default partners configuration
    public const DEFAULT_PARTNERS = [
        ['name' => 'Eki', 'percentage' => 50, 'amount' => 0],
        ['name' => 'Aldi', 'percentage' => 50, 'amount' => 0],
    ];

    protected static function booted(): void
    {
        static::creating(function (ProfitDistribution $distribution) {
            if (empty($distribution->partners)) {
                $distribution->partners = self::DEFAULT_PARTNERS;
            }
        });
    }

    public function getPeriodAttribute(): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        return $months[$this->month] . ' ' . $this->year;
    }

    public static function calculateForPeriod(int $year, int $month): array
    {
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        // Total Revenue dari orders yang sudah delivered
        $totalRevenue = Order::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->sum('total');

        // Total HPP
        $totalHpp = Order::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->sum('total_hpp');

        // Total Operational Cost
        $totalOperational = Order::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->sum('operational_cost');

        // Total Expenses (biaya lain-lain) - using FinanceLog instead of Transaction
        $totalExpenses = FinanceLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->where('type', 'expense')
            ->whereIn('category', ['salary', 'supplies', 'food', 'other'])
            ->sum('amount');

        $grossProfit = $totalRevenue - $totalHpp;
        $netProfit = $grossProfit - $totalOperational - $totalExpenses;

        return [
            'total_revenue' => $totalRevenue,
            'total_hpp' => $totalHpp,
            'total_operational' => $totalOperational,
            'total_expenses' => $totalExpenses,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
        ];
    }

    public function calculateShares(): void
    {
        $partners = $this->partners ?? self::DEFAULT_PARTNERS;
        $updatedPartners = [];

        foreach ($partners as $partner) {
            $partner['amount'] = $this->total_shared_profit * ($partner['percentage'] / 100);
            $updatedPartners[] = $partner;
        }

        $this->partners = $updatedPartners;
    }

    // Helper to get partner share by name
    public function getPartnerShare(string $name): ?array
    {
        $partners = $this->partners ?? [];
        foreach ($partners as $partner) {
            if (strtolower($partner['name']) === strtolower($name)) {
                return $partner;
            }
        }
        return null;
    }

    // Helper accessors for backward compatibility
    public function getShareAmountEkiAttribute(): float
    {
        return $this->getPartnerShare('Eki')['amount'] ?? 0;
    }

    public function getShareAmountAldiAttribute(): float
    {
        return $this->getPartnerShare('Aldi')['amount'] ?? 0;
    }
}
