<?php

namespace App\Filament\Resources\FinancialAccountResource\Pages;

use App\Filament\Resources\FinancialAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinancialAccounts extends ListRecords
{
    protected static string $resource = FinancialAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
