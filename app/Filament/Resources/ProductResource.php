<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Traits\InteractsWithCustomerAuth;
use App\Models\Product;
use Filament\Actions;
use Filament\Forms\Components as FormComponents;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    use InteractsWithCustomerAuth;

    protected static ?string $model = Product::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';
    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Produk';
    protected static ?string $modelLabel = 'Produk';
    protected static ?string $pluralModelLabel = 'Produk';

    public static function canAccess(): bool
    {
        return static::isAdmin();
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Informasi Produk')->schema([
                FormComponents\TextInput::make('code')
                    ->label('Kode Produk')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                FormComponents\TextInput::make('name')
                    ->label('Nama Produk')
                    ->required()
                    ->maxLength(255),
                FormComponents\Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(3),
                FormComponents\TextInput::make('unit')
                    ->label('Satuan')
                    ->default('kg')
                    ->required(),
            ])->columns(2),

            Section::make('Harga')->schema([
                FormComponents\TextInput::make('hpp')
                    ->label('HPP (Harga Pokok)')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
                FormComponents\TextInput::make('selling_price')
                    ->label('Harga Jual')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
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
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit')
                    ->label('Satuan'),
                Tables\Columns\TextColumn::make('hpp')
                    ->label('HPP')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Harga Jual')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Total Stok')
                    ->getStateUsing(fn(Product $record) => number_format($record->total_stock, 2) . ' ' . $record->unit),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
