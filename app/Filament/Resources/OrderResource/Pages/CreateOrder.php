<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['operational_cost'] = ($data['distance_km'] ?? 0) * ($data['cost_per_km'] ?? 0);
        
        // Set default values to avoid NOT NULL constraint
        $data['subtotal'] = 0;
        $data['total_hpp'] = 0;
        $data['total'] = 0;
        $data['net_profit'] = 0;
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $order = $this->record;
        $order->refresh();
        
        // Calculate totals from saved items
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
