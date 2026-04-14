<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Filament\Traits\InteractsWithCustomerAuth;
use App\Models\Product;
use App\Models\StockMovement;
use Filament\Forms\Components as FormComponents;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    use InteractsWithCustomerAuth;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $model = StockMovement::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Pergerakan Stok';
    protected static ?string $modelLabel = 'Pergerakan Stok';
    protected static ?string $pluralModelLabel = 'Pergerakan Stok';

    public static function canAccess(): bool
    {
        return static::isAdmin();
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Informasi Pergerakan Stok')->schema([
                FormComponents\Select::make('type')
                    ->label('Jenis')
                    ->options([
                        'in' => 'Stok Masuk',
                        'out' => 'Stok Keluar',
                        'adjustment' => 'Penyesuaian',
                        'transfer' => 'Transfer Gudang',
                    ])
                    ->required()
                    ->live(),
                FormComponents\Select::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                FormComponents\Select::make('destination_warehouse_id')
                    ->label('Gudang Tujuan')
                    ->relationship('destinationWarehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn(Get $get) => $get('type') === 'transfer')
                    ->required(fn(Get $get) => $get('type') === 'transfer'),
                FormComponents\Select::make('product_id')
                    ->label('Produk')
                    ->options(Product::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                FormComponents\TextInput::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->required(),
                FormComponents\TextInput::make('unit_cost')
                    ->label('Harga Beli (per unit)')
                    ->numeric()
                    ->prefix('Rp')
                    ->visible(fn(Get $get) => $get('type') === 'in')
                    ->helperText('Digunakan untuk menghitung HPP rata-rata'),
                FormComponents\Select::make('reason')
                    ->label('Alasan')
                    ->options(StockMovement::REASONS)
                    ->required(),
                FormComponents\DatePicker::make('movement_date')
                    ->label('Tanggal')
                    ->default(now())
                    ->required(),
                FormComponents\Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('productBatch.batch_code')
                    ->label('Batch')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Jenis')
                    ->colors([
                        'success' => 'in',
                        'danger' => 'out',
                        'warning' => 'adjustment',
                        'primary' => 'transfer',
                    ])
                    ->formatStateUsing(fn(string $state) => match($state) {
                        'in' => 'Masuk',
                        'out' => 'Keluar',
                        'adjustment' => 'Penyesuaian',
                        'transfer' => 'Transfer',
                        default => $state
                    }),
                Tables\Columns\TextColumn::make('stock_before')
                    ->label('Awal')
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jml')
                    ->weight('bold')
                    ->formatStateUsing(fn($state, StockMovement $record) => ($record->type === 'in' ? '+' : '-') . number_format($state, 2)),
                Tables\Columns\TextColumn::make('stock_after')
                    ->label('Akhir')
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Keterangan')
                    ->limit(30),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis')
                    ->options([
                        'in' => 'Stok Masuk',
                        'out' => 'Stok Keluar',
                        'adjustment' => 'Penyesuaian',
                        'transfer' => 'Transfer',
                    ]),
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name'),
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name'),
            ])
            ->actions([
                // View action removed - use list view
            ])
            ->bulkActions([])
            ->defaultSort('movement_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'create' => Pages\CreateStockMovement::route('/create'),
        ];
    }
}
