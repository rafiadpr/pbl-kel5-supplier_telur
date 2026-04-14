<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Filament\Resources\WarehouseResource\RelationManagers;
use App\Filament\Traits\InteractsWithCustomerAuth;
use App\Models\Warehouse;
use Filament\Actions;
use Filament\Forms\Components as FormComponents;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseResource extends Resource
{
    use InteractsWithCustomerAuth;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $model = Warehouse::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Gudang';
    protected static ?string $modelLabel = 'Gudang';
    protected static ?string $pluralModelLabel = 'Gudang';

    public static function canAccess(): bool
    {
        return static::isAdmin();
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Informasi Gudang')->schema([
                FormComponents\TextInput::make('code')
                    ->label('Kode Gudang')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                FormComponents\TextInput::make('name')
                    ->label('Nama Gudang')
                    ->required()
                    ->maxLength(255),
                FormComponents\TextInput::make('owner')
                    ->label('Pemilik')
                    ->maxLength(255),
                FormComponents\TextInput::make('phone')
                    ->label('Telepon')
                    ->tel()
                    ->maxLength(20),
                FormComponents\Textarea::make('address')
                    ->label('Alamat')
                    ->rows(2)
                    ->columnSpanFull(),
                FormComponents\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ])->columns(2),
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
                    ->label('Nama Gudang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('owner')
                    ->label('Pemilik')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon'),
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
