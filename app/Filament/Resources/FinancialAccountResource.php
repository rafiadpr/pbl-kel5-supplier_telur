<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinancialAccountResource\Pages;
use App\Models\FinancialAccount;
use Filament\Actions;
use Filament\Forms\Components as FormComponents;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class FinancialAccountResource extends Resource
{
    protected static ?string $model = FinancialAccount::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wallet';
    protected static string|\UnitEnum|null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Akun Keuangan';
    protected static ?string $modelLabel = 'Akun Keuangan';
    protected static ?string $pluralModelLabel = 'Akun Keuangan';

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Informasi Akun')->schema([
                FormComponents\TextInput::make('name')
                    ->label('Nama Akun')
                    ->required()
                    ->placeholder('Contoh: Kas Kecil, BCA Utama')
                    ->maxLength(255),
                FormComponents\TextInput::make('account_number')
                    ->label('No. Rekening')
                    ->placeholder('Opsional untuk akun bank')
                    ->maxLength(100),
                FormComponents\TextInput::make('balance')
                    ->label('Saldo Awal')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->disabled(fn ($record) => $record !== null)
                    ->dehydrated(fn ($record) => $record === null)
                    ->helperText('Saldo hanya bisa diatur saat pembuatan. Setelahnya akan terupdate otomatis dari transaksi.'),
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Akun')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->label('No. Rekening')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Saldo')
                    ->money('IDR')
                    ->sortable()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->weight('bold'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Update')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                Actions\EditAction::make(),
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
            'index' => Pages\ListFinancialAccounts::route('/'),
            'create' => Pages\CreateFinancialAccount::route('/create'),
            'edit' => Pages\EditFinancialAccount::route('/{record}/edit'),
        ];
    }
}
