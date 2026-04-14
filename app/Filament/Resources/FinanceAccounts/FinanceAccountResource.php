<?php

namespace App\Filament\Resources\FinanceAccounts;

use App\Filament\Resources\FinanceAccounts\Pages\CreateFinanceAccount;
use App\Filament\Resources\FinanceAccounts\Pages\EditFinanceAccount;
use App\Filament\Resources\FinanceAccounts\Pages\ListFinanceAccounts;
use App\Filament\Resources\FinanceAccounts\Schemas\FinanceAccountForm;
use App\Filament\Resources\FinanceAccounts\Tables\FinanceAccountsTable;
use App\Models\FinanceAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FinanceAccountResource extends Resource
{
    protected static ?string $model = FinanceAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FinanceAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinanceAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFinanceAccounts::route('/'),
            'create' => CreateFinanceAccount::route('/create'),
            'edit' => EditFinanceAccount::route('/{record}/edit'),
        ];
    }
}
