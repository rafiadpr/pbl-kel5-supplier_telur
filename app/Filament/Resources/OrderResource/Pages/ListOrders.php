<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Traits\InteractsWithCustomerAuth;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListOrders extends ListRecords
{
    use InteractsWithCustomerAuth;

    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        // Customers cannot create or export orders
        if (static::isCustomer()) {
            return [];
        }

        return [
            ExportAction::make()
                ->label('Rekap Penjualan (.xlsx)')
                ->color('success')
                ->exports([
                    ExcelExport::make()
                        ->fromModel()
                        ->withFilename('Rekap-Penjualan-' . date('Y-m-d'))
                        ->withColumns([
                            Column::make('invoice_number')->heading('No Invoice'),
                            Column::make('order_date')->heading('Tanggal'),
                            Column::make('customer.name')->heading('Pelanggan'),
                            Column::make('warehouse.name')->heading('Gudang'),
                            Column::make('total')->heading('Total Tagihan'),
                            Column::make('paid_amount')->heading('Terbayar'),
                            Column::make('payment_status')
                                ->heading('Status Bayar')
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'unpaid' => 'Belum Bayar',
                                    'partial' => 'Sebagian',
                                    'paid' => 'Lunas',
                                    'whitewash' => 'Pemutihan (Write-off)',
                                    default => $state,
                                }),
                            Column::make('status')
                                ->heading('Status Order')
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'draft' => 'Draft',
                                    'confirmed' => 'Dikonfirmasi',
                                    'delivered' => 'Terkirim',
                                    'cancelled' => 'Dibatalkan',
                                    default => $state,
                                }),
                            Column::make('net_profit')->heading('Laba Bersih'),
                            Column::make('created_at')->heading('Waktu Input'),
                        ]),
                ]),
            Actions\CreateAction::make(),
        ];
    }
}
