<?php

namespace App\Filament\Resources\ProductBatchResource\Pages;

use App\Filament\Resources\ProductBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListProductBatches extends ListRecords
{
    protected static string $resource = ProductBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Laporan Stok (.xlsx)')
                ->color('success')
                ->exports([
                    ExcelExport::make()
                        ->fromTable()
                        ->withFilename('Stok-Opname-' . date('Y-m-d'))
                        ->withColumns([
                            Column::make('batch_code')->heading('Kode Batch'),
                            Column::make('product.name')->heading('Nama Produk'),
                            Column::make('warehouse.name')->heading('Lokasi Gudang'),
                            Column::make('received_at')->heading('Tgl Masuk'),
                            Column::make('quantity_initial')->heading('Stok Awal'),
                            Column::make('quantity_remaining')->heading('Sisa Stok'),
                            Column::make('purchase_price')->heading('HPP (@)'),
                            Column::make('selling_price')->heading('Harga Jual (@)'),
                            Column::make('asset_value')
                                ->heading('Nilai Aset')
                                ->formatStateUsing(fn ($record) => $record->quantity_remaining * $record->purchase_price),
                        ]),
                ]),
            Actions\CreateAction::make(),
        ];
    }
}
