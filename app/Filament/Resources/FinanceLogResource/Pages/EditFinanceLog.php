<?php

namespace App\Filament\Resources\FinanceLogResource\Pages;

use App\Filament\Resources\FinanceLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFinanceLog extends EditRecord
{
    protected static string $resource = FinanceLogResource::class;

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
