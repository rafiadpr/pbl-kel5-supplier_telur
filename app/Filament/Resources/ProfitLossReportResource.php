<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProfitLossReportResource\Pages;
use Filament\Resources\Resource;

class ProfitLossReportResource extends Resource
{
    protected static ?string $model = null;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static string|\UnitEnum|null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Laporan Laba Rugi';
    protected static ?string $modelLabel = 'Laporan Laba Rugi';
    protected static ?string $pluralModelLabel = 'Laporan Laba Rugi';
    protected static ?string $slug = 'profit-loss-reports';
    protected static ?int $navigationSort = 99;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProfitLossReports::route('/'),
        ];
    }
}
