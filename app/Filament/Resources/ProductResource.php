<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductBatchResource\Pages;
use App\Filament\Traits\InteractsWithCustomerAuth;
use App\Models\Product;
use App\Models\ProductBatch;
use Filament\Actions;
use Filament\Forms\Components as FormComponents;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductBatchResource extends Resource
{
    use InteractsWithCustomerAuth;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $model = ProductBatch::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-arrow-down';
    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Stok Masuk (Batch)';
    protected static ?string $modelLabel = 'Batch Stok';
    protected static ?string $pluralModelLabel = 'Batch Stok';

    public static function canAccess(): bool
    {
        return static::isAdmin();
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Informasi Batch')->schema([
                FormComponents\Select::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if ($state) {
                            $product = Product::find($state);
                            if ($product) {
                                $set('purchase_price', $product->hpp);
                                $set('selling_price', $product->selling_price);
                            }
                        }
                    }),
                FormComponents\TextInput::make('batch_code')
                    ->label('Kode Batch')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->default(fn () => 'BATCH-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4)))
                    ->maxLength(100),
                FormComponents\DatePicker::make('received_at')
                    ->label('Tanggal Masuk')
                    ->default(now())
                    ->required(),
            ])->columns(3),

            Section::make('Jumlah Stok')->schema([
                FormComponents\TextInput::make('quantity_initial')
                    ->label('Jumlah Masuk')
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                        if (!$get('id')) {
                            $set('quantity_remaining', $state);
                        }
                    }),
                FormComponents\TextInput::make('quantity_remaining')
                    ->label('Sisa Stok')
                    ->numeric()
                    ->required()
                    ->disabled(fn (Get $get) => !$get('id'))
                    ->dehydrated(),
            ])->columns(2),

            Section::make('Harga')->schema([
                FormComponents\TextInput::make('purchase_price')
                    ->label('Harga Beli (HPP)')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
                FormComponents\TextInput::make('selling_price')
                    ->label('Harga Jual')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
                FormComponents\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('batch_code')
                    ->label('Kode Batch')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('received_at')
                    ->label('Tanggal Masuk')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity_initial')
                    ->label('Qty Awal')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('quantity_remaining')
                    ->label('Sisa')
                    ->alignCenter()
                    ->color(fn (int $state): string => $state <= 10 ? 'danger' : 'success')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('purchase_price')
                    ->label('Harga Beli')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Harga Jual')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make(),
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductBatches::route('/'),
            'create' => Pages\CreateProductBatch::route('/create'),
            'edit' => Pages\EditProductBatch::route('/{record}/edit'),
        ];
    }
}
