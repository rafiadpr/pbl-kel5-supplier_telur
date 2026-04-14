<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\InteractsWithCustomerAuth;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    use InteractsWithCustomerAuth;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return static::isAdmin();
    }

    protected function getStats(): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Total pendapatan bulan ini
        $monthlyRevenue = Order::whereMonth('order_date', $currentMonth)
            ->whereYear('order_date', $currentYear)
            ->where('status', 'delivered')
            ->sum('total');

        // Total laba bersih bulan ini
        $monthlyProfit = Order::whereMonth('order_date', $currentMonth)
            ->whereYear('order_date', $currentYear)
            ->where('status', 'delivered')
            ->sum('net_profit');

        // Total piutang
        $totalReceivables = Order::whereIn('payment_status', ['unpaid', 'partial'])
            ->sum(\DB::raw('total - paid_amount'));

        // Jumlah pesanan hari ini
        $todayOrders = Order::whereDate('order_date', today())->count();

        return [
            Stat::make('Pendapatan Bulan Ini', 'Rp ' . number_format($monthlyRevenue, 0, ',', '.'))
                ->description('Total penjualan')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Laba Bersih Bulan Ini', 'Rp ' . number_format($monthlyProfit, 0, ',', '.'))
                ->description('Setelah HPP & operasional')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),
            Stat::make('Total Piutang', 'Rp ' . number_format($totalReceivables, 0, ',', '.'))
                ->description('Invoice belum lunas')
                ->descriptionIcon('heroicon-m-clock')
                ->color($totalReceivables > 0 ? 'warning' : 'success'),
            Stat::make('Pesanan Hari Ini', $todayOrders)
                ->description('Jumlah pesanan')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('info'),
        ];
    }
}
