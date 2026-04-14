<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Traits\InteractsWithCustomerAuth;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Warehouse;
use Filament\Actions;
use Filament\Forms\Components as FormComponents;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\TextInput;

class OrderResource extends Resource
{
    use InteractsWithCustomerAuth;

    protected static ?string $model = Order::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static string|\UnitEnum|null $navigationGroup = 'Sales';
    protected static ?string $navigationLabel = 'Pesanan';
    protected static ?string $modelLabel = 'Pesanan';
    protected static ?string $pluralModelLabel = 'Pesanan';

    /**
     * Scope queries so customers can only see their own orders.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        if (static::isCustomer()) {
            $query->where('customer_id', auth('customer')->id());
        }

        return $query;
    }
    

    public static function form(Schema $form): Schema
    {
        $vehiclePrices = [
            'kaki' => 0,
            'motor' => 230,
            'jazz' => 900,
            'innova' => 1250,
        ];
        return $form->schema([
            Section::make('Informasi Pesanan')->schema([
                FormComponents\TextInput::make('invoice_number')
                    ->label('No. Invoice')
                    ->disabled()
                    ->dehydrated()
                    ->default(fn() => Order::generateInvoiceNumber()),
                FormComponents\Select::make('customer_id')
                    ->label('Pelanggan')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if ($state) {
                            $customer = Customer::find($state);
                            if ($customer) {
                                $set('distance_km', $customer->distance_km);
                            }
                        }
                    }),
                FormComponents\Select::make('warehouse_id')
                    ->label('Gudang Asal')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                FormComponents\DatePicker::make('order_date')
                    ->label('Tanggal Pesanan')
                    ->default(now())
                    ->required(),
                FormComponents\DatePicker::make('due_date')
                    ->label('Jatuh Tempo'),
                FormComponents\Select::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'confirmed' => 'Dikonfirmasi',
                        'on_delivery' => 'Dalam Pengiriman',
                        'delivered' => 'Terkirim',
                        'cancelled' => 'Dibatalkan',
                    ])
                    ->default('draft')
                    ->required(),
            ])->columns(3),

            Section::make('Biaya Operasional')->schema([
                // 1. PILIH KENDARAAN
                FormComponents\Select::make('vehicle_type')
                    ->label('Jenis Pengantaran')
                    ->options([
                        'kaki' => 'Jalan Kaki/Diambil',
                        'motor' => 'Motor',
                        'jazz' => 'Mobil Jazz',
                        'innova' => 'Mobil Innova',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state) use ($vehiclePrices) {
                        // Logic: Ambil harga berdasarkan pilihan, set ke cost_per_km
                        $price = $vehiclePrices[$state] ?? 0;
                        $set('cost_per_km', $price);
                        
                        // Hitung ulang total langsung
                        self::calculateOperationalCost($set, $get);
                    }),

                // 2. JARAK TEMPUH
                FormComponents\TextInput::make('distance_km')
                    ->label('Jarak Tempuh')
                    ->numeric()
                    ->suffix('km')
                    ->default(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(Set $set, Get $get) => self::calculateOperationalCost($set, $get)),

                // 3. BIAYA PER KM (Otomatis Terisi)
                FormComponents\TextInput::make('cost_per_km')
                    ->label('Biaya per KM')
                    ->numeric()
                    ->prefix('Rp')
                    ->readOnly() // Dikunci agar tidak diedit manual (opsional)
                    ->dehydrated() // Wajib ada agar tersimpan ke DB meski readOnly
                    ->live(),

                // 4. TOTAL BIAYA
                FormComponents\TextInput::make('operational_cost')
                    ->label('Total Biaya')
                    ->numeric()
                    ->prefix('Rp')
                    ->readOnly()
                    ->dehydrated(),
            ])->columns(2),

            Section::make('Item Pesanan')->schema([
                FormComponents\Repeater::make('items')
                    ->relationship()
                    ->schema([
                        // --- BARIS 1 ---

                        // 1. Produk (Kolom 1)
                        FormComponents\Select::make('product_id')
                            ->label('Produk')
                            ->options(Product::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                $set('product_batch_id', null);
                                if ($state) {
                                    $product = Product::find($state);
                                    if ($product) {
                                        $set('unit', $product->unit);
                                    }
                                }
                            })
                            ->columnSpan(1),

                        // 2. Pilih Stok/Batch (Kolom 2)
                        FormComponents\Select::make('product_batch_id')
                            ->label('Pilih Stok (Batch)')
                            ->options(function (Get $get) {
                                $productId = $get('product_id');
                                if (!$productId)
                                    return [];

                                return ProductBatch::where('product_id', $productId)
                                    ->available()
                                    ->get()
                                    ->mapWithKeys(fn($batch) => [$batch->id => $batch->batch_label]);
                            })
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if ($state) {
                                    $batch = ProductBatch::find($state);
                                    if ($batch) {
                                        $set('unit_price', $batch->selling_price);
                                        $set('locked_hpp', $batch->purchase_price);
                                    }
                                }
                            })
                            ->columnSpan(1),

                        // 3. Satuan (Kolom 3)
                        FormComponents\TextInput::make('unit')
                            ->label('Satuan')
                            ->default('kg')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(1),

                        // --- BARIS 2 ---

                        // 4. Jumlah (Kolom 1)
                        FormComponents\TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->maxValue(function (Get $get) {
                                $batchId = $get('product_batch_id');
                                if ($batchId) {
                                    $batch = ProductBatch::find($batchId);
                                    return $batch?->quantity_remaining ?? 0;
                                }
                                return 99999;
                            })
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Set $set, Get $get) => self::calculateItemSubtotal($set, $get))
                            ->columnSpan(1),

                        // 5. Harga Satuan (Kolom 2)
                        FormComponents\TextInput::make('unit_price')
                            ->label('Harga Satuan')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Set $set, Get $get) => self::calculateItemSubtotal($set, $get))
                            ->columnSpan(1),

                        // 6. HPP (Kolom 3)
                        FormComponents\TextInput::make('locked_hpp')
                            ->label('HPP')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(1),

                        // --- BARIS 3 ---

                        // 7. Subtotal (Kolom 1)
                        FormComponents\TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(1),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah Item')
                    ->reorderable(false)
                    ->live()
                    ->afterStateUpdated(fn(Set $set, Get $get) => self::recalculateSummaryFromRoot($set, $get)),
            ]),

            Section::make('Ringkasan')->schema([
                FormComponents\TextInput::make('subtotal')
                    ->label('Subtotal')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated()
                    ->columnSpan(1),
                FormComponents\TextInput::make('discount')
                    ->label('Diskon')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(Set $set, Get $get) => self::recalculateSummaryFromRoot($set, $get))
                    ->columnSpan(1),
                FormComponents\TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated()
                    ->columnSpan(1),
                FormComponents\TextInput::make('total_hpp')
                    ->label('Total HPP')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated()
                    ->columnSpan(1),
                FormComponents\TextInput::make('net_profit')
                    ->label('Laba Bersih')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated()
                    ->columnSpan(1),
                FormComponents\Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    protected static function calculateOperationalCost(Set $set, Get $get): void
    {
        $distance = (float) ($get('distance_km') ?? 0);
        $costPerKm = (float) ($get('cost_per_km') ?? 0);
        $set('operational_cost', $distance * $costPerKm);

        self::recalculateSummaryFromRoot($set, $get);
    }

    protected static function calculateItemSubtotal(Set $set, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $set('subtotal', $quantity * $unitPrice);

        self::calculateSummary($set, $get);
    }

    protected static function calculateSummary(Set $set, Get $get): void
    {
        $items = $get('../../items') ?? [];

        $subtotal = 0;
        $totalHpp = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $hpp = (float) ($item['locked_hpp'] ?? 0);

            $subtotal += $qty * $price;
            $totalHpp += $qty * $hpp;
        }

        $discount = (float) ($get('../../discount') ?? 0);
        $operationalCost = (float) ($get('../../operational_cost') ?? 0);
        $total = $subtotal - $discount;
        $netProfit = $total - $totalHpp - $operationalCost;

        $set('../../subtotal', $subtotal);
        $set('../../total', $total);
        $set('../../total_hpp', $totalHpp);
        $set('../../net_profit', $netProfit);
    }

    protected static function recalculateSummaryFromRoot(Set $set, Get $get): void
    {
        $items = $get('items') ?? [];

        $subtotal = 0;
        $totalHpp = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $hpp = (float) ($item['locked_hpp'] ?? 0);

            $subtotal += $qty * $price;
            $totalHpp += $qty * $hpp;
        }

        $discount = (float) ($get('discount') ?? 0);
        $operationalCost = (float) ($get('operational_cost') ?? 0);
        $total = $subtotal - $discount;
        $netProfit = $total - $totalHpp - $operationalCost;

        $set('subtotal', $subtotal);
        $set('total', $total);
        $set('total_hpp', $totalHpp);
        $set('net_profit', $netProfit);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('No. Invoice')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('net_profit')
                    ->label('Laba')
                    ->money('IDR')
                    ->sortable()
                    ->visible(fn () => auth('web')->check()),
                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Pembayaran')
                    ->colors([
                        'danger' => 'unpaid',
                        'warning' => 'partial',
                        'success' => 'paid',
                        'gray' => 'whitewash',
                    ])
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'unpaid' => 'Belum Bayar',
                        'partial' => 'Sebagian',
                        'paid' => 'Lunas',
                        'whitewash' => 'Pemutihan (Write-off)',
                        default => $state
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'secondary' => 'draft',
                        'primary' => 'confirmed',
                        'warning' => 'on_delivery',
                        'success' => 'delivered',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'draft' => 'Draft',
                        'confirmed' => 'Dikonfirmasi',
                        'on_delivery' => 'Dalam Pengiriman',
                        'delivered' => 'Terkirim',
                        'cancelled' => 'Dibatalkan',
                        default => $state
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'confirmed' => 'Dikonfirmasi',
                        'on_delivery' => 'Dalam Pengiriman',
                        'delivered' => 'Terkirim',
                        'cancelled' => 'Dibatalkan',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Status Pembayaran')
                    ->options([
                        'unpaid' => 'Belum Bayar',
                        'partial' => 'Sebagian',
                        'paid' => 'Lunas',
                        'whitewash' => 'Pemutihan (Write-off)',
                    ]),
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name'),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Pelanggan')
                    ->relationship('customer', 'name') // Asumsi relasi di model Order bernama 'customer' dan kolom nama di tabel customer adalah 'name'
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('total_range')
                    ->label('Rentang Total Harga')
                    ->form([
                        TextInput::make('min_total') // Pastikan TextInput sudah di-import
                            ->label('Minimum (Rp)')
                            ->numeric()
                            ->placeholder('10000'),
                        TextInput::make('max_total')
                            ->label('Maksimum (Rp)')
                            ->numeric()
                            ->placeholder('500000'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_total'],
                                fn(Builder $query, $amount) => $query->where('total', '>=', $amount)
                            )
                            ->when(
                                $data['max_total'],
                                fn(Builder $query, $amount) => $query->where('total', '<=', $amount)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['min_total'] ?? null) {
                            $indicators[] = 'Min: Rp ' . number_format($data['min_total'], 0, ',', '.');
                        }
                        if ($data['max_total'] ?? null) {
                            $indicators[] = 'Max: Rp ' . number_format($data['max_total'], 0, ',', '.');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn () => auth('web')->check()),
                Actions\Action::make('mark_whitewash')
                    ->label('Pemutihan')
                    ->icon('heroicon-o-archive-box-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pemutihan')
                    ->modalDescription('Apakah Anda yakin ingin memutihkan pesanan ini? Status akan berubah menjadi "Pemutihan (Write-off)" dan pesanan tidak akan dihitung sebagai pendapatan.')
                    ->modalSubmitActionLabel('Ya, Putihkan')
                    ->visible(fn(Order $record) => auth('web')->check() && !in_array($record->payment_status, ['whitewash', 'paid']))
                    ->action(function (Order $record) {
                        $record->updateQuietly([
                            'payment_status' => 'whitewash',
                        ]);

                        Notification::make()
                            ->title('Pesanan telah diputihkan')
                            ->body("Invoice {$record->invoice_number} ditandai sebagai Pemutihan (Write-off).")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make()
                        ->exports([
                            \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                                ->fromModel()
                                ->withFilename(function ($recordIds) {
                                    $names = Order::whereIn('id', $recordIds)
                                        ->with('customer')
                                        ->get()
                                        ->pluck('customer.name')
                                        ->filter()
                                        ->unique()
                                        ->implode('_');

                                    return ($names ?: 'Export') . '-' . date('Y-m-d');
                                })
                                ->only([
                                    'invoice_number',
                                    'order_date',
                                    'customer.name',
                                    'total',
                                    'payment_status',
                                ])
                                ->withColumns([
                                    \pxlrbt\FilamentExcel\Columns\Column::make('invoice_number')->heading('No Invoice'),
                                    \pxlrbt\FilamentExcel\Columns\Column::make('order_date')->heading('Tanggal'),
                                    \pxlrbt\FilamentExcel\Columns\Column::make('customer.name')->heading('Pelanggan'),
                                    \pxlrbt\FilamentExcel\Columns\Column::make('total')->heading('Total'),
                                    \pxlrbt\FilamentExcel\Columns\Column::make('payment_status')
                                        ->heading('Status')
                                        ->formatStateUsing(fn ($state) => match ($state) {
                                            'unpaid' => 'Belum Bayar',
                                            'partial' => 'Sebagian',
                                            'paid' => 'Lunas',
                                            'whitewash' => 'Pemutihan',
                                            default => $state,
                                        }),
                                ]),
                        ]),
                    Actions\DeleteBulkAction::make(),
                    Actions\BulkAction::make('bulk_mark_whitewash')
                        ->label('Pemutihan Massal')
                        ->icon('heroicon-o-archive-box-x-mark')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Pemutihan Massal')
                        ->modalDescription('Apakah Anda yakin ingin memutihkan semua pesanan yang dipilih? Pesanan yang sudah Lunas atau sudah diputihkan akan dilewati.')
                        ->modalSubmitActionLabel('Ya, Putihkan Semua')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (in_array($record->payment_status, ['whitewash', 'paid'])) {
                                    continue;
                                }
                                $record->updateQuietly([
                                    'payment_status' => 'whitewash',
                                ]);
                                $count++;
                            }

                            Notification::make()
                                ->title('Pemutihan massal selesai')
                                ->body("{$count} pesanan berhasil diputihkan.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
