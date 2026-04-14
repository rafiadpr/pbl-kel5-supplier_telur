<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Customers\Pages;
use App\Filament\Traits\InteractsWithCustomerAuth;
use App\Models\Customer;
use Filament\Actions;
use Filament\Forms\Components as FormComponents;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    use InteractsWithCustomerAuth;

    protected static ?string $model = Customer::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static string|\UnitEnum|null $navigationGroup = 'Sales';
    protected static ?string $navigationLabel = 'Pelanggan';
    protected static ?string $modelLabel = 'Pelanggan';
    protected static ?string $pluralModelLabel = 'Pelanggan';

    public static function canAccess(): bool
    {
        return static::isAdmin();
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Informasi Pelanggan')->schema([
                FormComponents\TextInput::make('code')
                    ->label('Kode Pelanggan')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                FormComponents\TextInput::make('name')
                    ->label('Nama Pelanggan')
                    ->required()
                    ->maxLength(255),
                FormComponents\TextInput::make('contact_person')
                    ->label('Kontak Person')
                    ->maxLength(255),
                FormComponents\TextInput::make('phone')
                    ->label('Telepon')
                    ->tel()
                    ->maxLength(20),
                FormComponents\Textarea::make('address')
                    ->label('Alamat')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Pengaturan')->schema([
                FormComponents\TextInput::make('distance_km')
                    ->label('Jarak Default (KM)')
                    ->numeric()
                    ->suffix('km')
                    ->default(0),
                FormComponents\TextInput::make('credit_limit')
                    ->label('Limit Kredit')
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
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon'),
                Tables\Columns\TextColumn::make('distance_km')
                    ->label('Jarak')
                    ->suffix(' km')
                    ->sortable(),
                Tables\Columns\TextColumn::make('outstanding_balance')
                    ->label('Piutang')
                    ->money('IDR')
                    ->getStateUsing(fn(Customer $record) => $record->outstanding_balance),
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
