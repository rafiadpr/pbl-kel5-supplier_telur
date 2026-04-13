<?php

namespace App\Filament\Resources\ProfitDistributionResource\Pages;

use App\Filament\Resources\ProfitDistributionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProfitDistribution extends EditRecord
{
    protected static string $resource = ProfitDistributionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
