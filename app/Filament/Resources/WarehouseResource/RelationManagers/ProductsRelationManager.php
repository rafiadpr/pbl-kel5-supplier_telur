<?php

namespace App\Filament\Resources\WarehouseResource\RelationManagers;

use Filament\Actions;
use Filament\Forms\Components as FormComponents;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';
    protected static ?string $title = 'Stok Produk';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                FormComponents\TextInput::make('stock')
                    ->label('Jumlah Stok')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit')
                    ->label('Satuan')
                    ->badge(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stok Tersedia')
                    ->weight('bold')
                    ->color(fn (string $state): string => $state <= 10 ? 'danger' : 'success')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->label('Tambah Stok Produk')
                    ->form(fn (Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        FormComponents\TextInput::make('stock')
                            ->label('Jumlah Awal')
                            ->numeric()
                            ->required()
                            ->default(0),
                    ]),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->label('Update Stok'),
                Actions\DetachAction::make()
                    ->label('Hapus'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}