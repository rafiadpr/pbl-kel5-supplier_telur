<?php

namespace App\Filament\Resources\ProfitDistributionResource\Pages;

use App\Filament\Resources\ProfitDistributionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProfitDistributions extends ListRecords
{
    protected static string $resource = ProfitDistributionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Laporan Bagi Hasil'),
        ];
    }
}
