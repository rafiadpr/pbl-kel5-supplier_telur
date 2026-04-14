<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\InteractsWithCustomerAuth;
use App\Models\FinancialAccount;
use App\Models\FinanceLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountOverviewWidget extends BaseWidget
{
    use InteractsWithCustomerAuth;

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return static::isAdmin();
    }

    protected function getStats(): array
    {
        $stats = [];
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Loop semua akun aktif
        $accounts = FinancialAccount::where('is_active', true)->get();

        foreach ($accounts as $account) {
            $stats[] = Stat::make($account->name, 'Rp ' . number_format($account->balance, 0, ',', '.'))
                ->description($account->account_number ? "No. {$account->account_number}" : 'Saldo Saat Ini')
                ->descriptionIcon('heroicon-m-wallet')
                ->color($account->balance < 0 ? 'danger' : 'success');
        }

        // Tambahan: Total Masuk & Keluar Bulan Ini
        $totalIncome = FinanceLog::where('type', 'income')
            ->whereMonth('transaction_date', $currentMonth)
            ->whereYear('transaction_date', $currentYear)
            ->sum('amount');

        $totalExpense = FinanceLog::where('type', 'expense')
            ->whereMonth('transaction_date', $currentMonth)
            ->whereYear('transaction_date', $currentYear)
            ->sum('amount');

        $stats[] = Stat::make('Total Masuk Bulan Ini', 'Rp ' . number_format($totalIncome, 0, ',', '.'))
            ->description('Pemasukan')
            ->descriptionIcon('heroicon-m-arrow-down-tray')
            ->color('success');

        $stats[] = Stat::make('Total Keluar Bulan Ini', 'Rp ' . number_format($totalExpense, 0, ',', '.'))
            ->description('Pengeluaran')
            ->descriptionIcon('heroicon-m-arrow-up-tray')
            ->color('danger');

        return $stats;
    }
}
