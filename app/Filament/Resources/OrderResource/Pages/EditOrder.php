<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['operational_cost'] = ($data['distance_km'] ?? 0) * ($data['cost_per_km'] ?? 0);
        return $data;
    }

    protected function afterSave(): void
    {
        $order = $this->record;
        $order->refresh();
        
        // Recalculate totals from saved items
        $subtotal = 0;
        $totalHpp = 0;
        
        foreach ($order->items as $item) {
            $subtotal += ($item->quantity ?? 0) * ($item->unit_price ?? 0);
            $totalHpp += ($item->locked_hpp ?? 0) * ($item->quantity ?? 0);
        }
        
        $order->subtotal = $subtotal;
        $order->total_hpp = $totalHpp;
        $order->total = $subtotal - ($order->discount ?? 0);
        $order->net_profit = $order->total - $totalHpp - ($order->operational_cost ?? 0);
        $order->save();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
