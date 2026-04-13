<?php

namespace App\Filament\Resources\ProfitDistributionResource\Pages;

use App\Filament\Resources\ProfitDistributionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProfitDistribution extends CreateRecord
{
    protected static string $resource = ProfitDistributionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
