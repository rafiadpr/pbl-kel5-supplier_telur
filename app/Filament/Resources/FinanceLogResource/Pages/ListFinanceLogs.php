<?php

namespace App\Filament\Resources\FinanceLogResource\Pages;

use App\Filament\Resources\FinanceLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListFinanceLogs extends ListRecords
{
    protected static string $resource = FinanceLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Download Laporan (.xlsx)')
                ->color('success')
                ->exports([
                    ExcelExport::make()
                        ->fromTable()
                        ->withFilename(fn ($resource) => 'Laporan-Keuangan-' . date('Y-m-d'))
                        ->withColumns([
                            Column::make('transaction_date')->heading('Tanggal'),
                            Column::make('type')->heading('Tipe Transaksi'),
                            Column::make('category')->heading('Kategori'),
                            Column::make('financialAccount.name')->heading('Sumber Dana/Akun'),
                            Column::make('amount')->heading('Nominal'),
                            Column::make('description')->heading('Keterangan'),
                            Column::make('order.invoice_number')->heading('Reff Invoice'),
                        ]),
                ]),
            Actions\CreateAction::make(),
        ];
    }
}
