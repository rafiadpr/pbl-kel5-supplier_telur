<?php

namespace App\Filament\Resources\FinanceLogResource\Pages;

use App\Filament\Resources\FinanceLogResource;
use App\Models\FinanceLog;
use Filament\Resources\Pages\CreateRecord;

class CreateFinanceLog extends CreateRecord
{
    protected static string $resource = FinanceLogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_number'] = FinanceLog::generateReferenceNumber($data['type']);
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
