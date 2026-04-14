<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Rekap Pembelian (.xlsx)')
                ->color('success')
                ->exports([
                    ExcelExport::make()
                        ->fromTable()
                        ->withFilename('Rekap-Pembelian-' . date('Y-m-d'))
                        ->withColumns([
                            Column::make('purchase_number')->heading('No. PO'),
                            Column::make('transaction_date')->heading('Tanggal'),
                            Column::make('supplier_name')->heading('Supplier'),
                            Column::make('warehouse.name')->heading('Gudang Tujuan'),
                            Column::make('financialAccount.name')->heading('Dibayar Dari'),
                            Column::make('total_amount')->heading('Total Belanja'),
                            Column::make('notes')->heading('Catatan'),
                        ]),
                ]),
            Actions\CreateAction::make(),
        ];
    }
}
