<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\InteractsWithCustomerAuth;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrders extends BaseWidget
{
    use InteractsWithCustomerAuth;

    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $query = Order::query()->latest()->limit(10);

        // Filter by customer if logged in as customer
        if (static::isCustomer()) {
            $query->where('customer_id', auth('customer')->id());
        }

        $columns = [
            Tables\Columns\TextColumn::make('invoice_number')
                ->label('No. Invoice')
                ->searchable(),
            Tables\Columns\TextColumn::make('order_date')
                ->label('Tanggal')
                ->date('d/m/Y'),
            Tables\Columns\TextColumn::make('total')
                ->label('Total')
                ->money('IDR'),
            Tables\Columns\BadgeColumn::make('payment_status')
                ->label('Pembayaran')
                ->colors([
                    'danger' => 'unpaid',
                    'warning' => 'partial',
                    'success' => 'paid',
                ])
                ->formatStateUsing(fn(string $state) => match($state) {
                    'unpaid' => 'Belum Bayar',
                    'partial' => 'Sebagian',
                    'paid' => 'Lunas',
                    default => $state
                }),
            Tables\Columns\BadgeColumn::make('status')
                ->label('Status')
                ->colors([
                    'secondary' => 'draft',
                    'primary' => 'confirmed',
                    'success' => 'delivered',
                    'danger' => 'cancelled',
                ]),
        ];

        // Show customer name column only for admins
        if (static::isAdmin()) {
            array_splice($columns, 1, 0, [
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Pelanggan'),
            ]);
        }

        return $table
            ->query($query)
            ->columns($columns)
            ->actions([
                Action::make('view')
                    ->label('Lihat')
                    ->url(fn (Order $record) => \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-o-eye')
                    ->visible(fn() => static::isAdmin()),
            ])
            ->heading('Pesanan Terbaru');
    }
}
