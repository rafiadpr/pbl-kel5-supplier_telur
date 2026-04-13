<?php

namespace App\Filament\Resources\ProfitLossReportResource\Pages;

use App\Filament\Resources\ProfitLossReportResource;
use App\Models\FinanceLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Purchase;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ListProfitLossReports extends Page
{
    protected static string $resource = ProfitLossReportResource::class;
    protected static ?string $title = 'Analisa Keuntungan';

    // Filter properties
    public ?string $startDate = null;
    public ?string $endDate = null;

    public function getView(): string
    {
        return 'filament.resources.profit-loss-report-resource.pages.list-profit-loss-reports';
    }

    public function mount(): void
    {
        // Default: Bulan Ini
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Filter Periode')->schema([
                DatePicker::make('startDate')
                    ->label('Dari Tanggal')
                    ->required(),
                DatePicker::make('endDate')
                    ->label('Sampai Tanggal')
                    ->required(),
            ])->columns(2)
        ]);
    }

    public function submitFilter(): void
    {
        // Trigger re-render with new dates
        $this->dispatch('filter-updated');
    }

    /**
     * Get report data based on filter dates
     */
    public function getReportData(): array
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        // 1. TOTAL PENJUALAN (Omzet) - Hanya yang statusnya bukan cancelled/whitewash
        $sales = Order::whereBetween('order_date', [$start, $end])
            ->whereNotIn('payment_status', ['cancelled', 'whitewash'])
            ->sum('total');

        // 2. TOTAL HPP (Modal Barang Terjual)
        // Hitung (qty * locked_hpp) dari item pesanan di periode ini
        $cogs = OrderItem::whereHas('order', function ($q) use ($start, $end) {
            $q->whereBetween('order_date', [$start, $end])
                ->whereNotIn('payment_status', ['cancelled', 'whitewash']);
        })
            ->get()
            ->sum(fn($item) => $item->quantity * $item->locked_hpp);

        // 3. LABA KOTOR (Gross Profit)
        $grossProfit = $sales - $cogs;

        // 4. BIAYA OPERASIONAL (Expenses)
        // PENTING: Jangan masukkan 'purchase' (Restock) dan kategori mutasi
        // karena Restock sudah dihitung lewat HPP (Cogs) saat barang laku.
        $expenses = FinanceLog::whereBetween('transaction_date', [$start, $end])
            ->where('type', 'expense')
            ->whereNotIn('category', ['purchase', 'Restock Barang', 'Mutasi Keluar', 'refund', 'consignment', 'profit'])
            ->sum('amount');

        // 5. LABA BERSIH (Net Profit)
        $netProfit = $grossProfit - $expenses;

        // 6. TOTAL BELANJA STOK (Arus Kas - Capital Spent)
        // Total uang yang dibayarkan ke supplier untuk beli telur di periode ini.
        // Ini BUKAN pengurang laba, melainkan informasi arus kas keluar.
        $totalCapitalSpent = Purchase::whereBetween('transaction_date', [$start, $end])
            ->sum('total_amount');

        return [
            'sales' => $sales,
            'cogs' => $cogs,
            'grossProfit' => $grossProfit,
            'expenses' => $expenses,
            'netProfit' => $netProfit,
            'totalCapitalSpent' => $totalCapitalSpent,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ];
    }

    protected function getViewData(): array
    {
        return [
            'reportData' => $this->getReportData(),
        ];
    }
}
