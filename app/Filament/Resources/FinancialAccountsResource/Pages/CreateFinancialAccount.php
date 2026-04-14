<?php

namespace App\Filament\Resources\FinancialAccountResource\Pages;

use App\Filament\Resources\FinancialAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinancialAccount extends CreateRecord
{
    protected static string $resource = FinancialAccountResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
