<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\InteractsWithCustomerAuth;
use App\Models\Product;
use App\Models\ProductBatch;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerEggPriceWidget extends BaseWidget
{
    use InteractsWithCustomerAuth;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Harga Telur Sekarang';

    public static function canView(): bool
    {
        return static::isCustomer();
    }

    protected function getStats(): array
    {
        $stats = [];

        $products = Product::where('is_active', true)->get();

        foreach ($products as $product) {
            // Ambil harga dari batch terbaru yang masih aktif
            $latestBatch = ProductBatch::where('product_id', $product->id)
                ->where('is_active', true)
                ->orderByDesc('received_at')
                ->first();

            $price = $latestBatch?->selling_price ?? $product->selling_price;
            $batchDate = $latestBatch?->received_at?->format('d M Y') ?? '-';

            $stats[] = Stat::make($product->name, 'Rp ' . number_format($price, 0, ',', '.'))
                ->description("per {$product->unit} · Update: {$batchDate}")
                ->descriptionIcon('heroicon-m-tag')
                ->color('warning');
        }

        if ($stats === []) {
            $stats[] = Stat::make('Harga Telur', 'Belum tersedia')
                ->description('Tidak ada produk aktif')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('gray');
        }

        return $stats;
    }
}
