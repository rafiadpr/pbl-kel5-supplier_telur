<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProfitDistributionResource\Pages;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProfitDistribution;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components as FormComponents;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProfitDistributionResource extends Resource
{
    protected static ?string $model = ProfitDistribution::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';
    protected static string|\UnitEnum|null $navigationGroup = 'Reporting';
    protected static ?string $navigationLabel = 'Bagi Hasil';
    protected static ?string $modelLabel = 'Bagi Hasil';
    protected static ?string $pluralModelLabel = 'Bagi Hasil';

    public static function form(Schema $form): Schema
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return $form->schema([
            Section::make('Periode & Klien')->schema([
                FormComponents\Select::make('year')
                    ->label('Tahun')
                    ->options(array_combine(
                        range(date('Y') - 2, date('Y') + 1),
                        range(date('Y') - 2, date('Y') + 1)
                    ))
                    ->default(date('Y'))
                    ->required()
                    ->live(),
                FormComponents\Select::make('month')
                    ->label('Bulan')
                    ->options($months)
                    ->default(date('n'))
                    ->required()
                    ->live(),
                FormComponents\Select::make('selected_customers')
                    ->label('Pilih Klien untuk Bagi Hasil')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn() => Customer::where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->helperText('Pilih satu atau lebih klien yang akan dihitung bagi hasilnya')
                    ->live()
                    ->columnSpanFull(),
                FormComponents\TextInput::make('shared_profit_per_kg')
                    ->label('Nominal Bagi Hasil per Kg (Upeti)')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(500)
                    ->required()
                    ->helperText('Nominal tetap yang dibagikan untuk setiap kg telur terjual')
                    ->live()
                    ->columnSpanFull(),
                SchemaActions::make([
                    Action::make('calculate')
                        ->label('Hitung Otomatis')
                        ->icon('heroicon-o-calculator')
                        ->action(function (Set $set, Get $get) {
                            $year = $get('year');
                            $month = $get('month');
                            $selectedCustomers = $get('selected_customers') ?? [];
                            $sharedProfitPerKg = (float) ($get('shared_profit_per_kg') ?? 0);

                            if ($year && $month) {
                                // Calculate revenue/HPP for selected customers (info pendukung)
                                $data = self::calculateForSelectedCustomers(
                                    $year,
                                    $month,
                                    $selectedCustomers
                                );

                                $set('total_revenue', $data['total_revenue']);
                                $set('total_hpp', $data['total_hpp']);
                                $set('total_operational', $data['total_operational']);
                                $set('gross_profit', $data['gross_profit']);
                                $set('net_profit', $data['net_profit']);

                                // Hitung total kg terjual dari OrderItem
                                $totalQuantitySold = $data['total_quantity_sold'];
                                $totalSharedProfit = $totalQuantitySold * $sharedProfitPerKg;

                                $set('total_quantity_sold', $totalQuantitySold);
                                $set('total_shared_profit', $totalSharedProfit);
                                $set('shared_profit_per_kg', $sharedProfitPerKg);

                                // Store customer IDs for reference
                                $set('customer_ids', $selectedCustomers);

                                // Store breakdown for audit trail
                                $set('breakdown', $data['breakdown']);

                                // Calculate shares using total_shared_profit
                                $partners = $get('partners') ?? ProfitDistribution::DEFAULT_PARTNERS;
                                $updatedPartners = [];
                                foreach ($partners as $partner) {
                                    $partner['amount'] = $totalSharedProfit * (($partner['percentage'] ?? 0) / 100);
                                    $updatedPartners[] = $partner;
                                }
                                $set('partners', $updatedPartners);
                            }
                        }),
                ]),
            ])->columns(2),

            Section::make('Rekapitulasi Keuangan (Klien Terpilih)')->schema([
                FormComponents\TextInput::make('total_quantity_sold')
                    ->label('Total Kg Terjual')
                    ->numeric()
                    ->disabled()
                    ->dehydrated()
                    ->suffix('kg'),
                FormComponents\TextInput::make('total_shared_profit')
                    ->label('Total Profit yang Dibagikan')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated()
                    ->helperText('= Total Kg Terjual × Nominal per Kg'),
                FormComponents\TextInput::make('total_revenue')
                    ->label('Total Penjualan')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated(),
                FormComponents\TextInput::make('total_hpp')
                    ->label('Total HPP')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated(),
                FormComponents\TextInput::make('gross_profit')
                    ->label('Laba Kotor')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated(),
                FormComponents\TextInput::make('total_operational')
                    ->label('Biaya Operasional')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated(),
                FormComponents\TextInput::make('net_profit')
                    ->label('Laba Bersih (Info Pendukung)')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated(),
                FormComponents\Hidden::make('customer_ids'),
                FormComponents\Hidden::make('breakdown'),
            ])->columns(3),

            Section::make('Pembagian Keuntungan')->schema([
                FormComponents\Repeater::make('partners')
                    ->label('Partner')
                    ->schema([
                        FormComponents\TextInput::make('name')
                            ->label('Nama')
                            ->required(),
                        FormComponents\TextInput::make('percentage')
                            ->label('Persentase')
                            ->numeric()
                            ->suffix('%')
                            ->required()
                            ->live(onBlur: true),
                        FormComponents\TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(3)
                    ->default(ProfitDistribution::DEFAULT_PARTNERS)
                    ->addActionLabel('Tambah Partner')
                    ->reorderable(false),
            ]),

            Section::make('Status')->schema([
                FormComponents\Select::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'finalized' => 'Final',
                    ])
                    ->default('draft')
                    ->required(),
                FormComponents\Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(1),
        ]);
    }

    /**
     * Calculate revenue and HPP for selected customers only
     */
    protected static function calculateForSelectedCustomers(
        int $year,
        int $month,
        array $customerIds
    ): array {
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        // Base query for selected customers
        $ordersQuery = Order::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'delivered');

        // Filter by selected customers if any
        if (!empty($customerIds)) {
            $ordersQuery->whereIn('customer_id', $customerIds);
        }

        // Total Revenue from orders
        $totalRevenue = (clone $ordersQuery)->sum('total');

        // Total HPP
        $totalHpp = (clone $ordersQuery)->sum('total_hpp');

        // Total Operational Cost
        $totalOperational = (clone $ordersQuery)->sum('operational_cost');

        // Total Kg Terjual (sum quantity dari OrderItem, exclude cancelled & whitewash)
        $orderIds = (clone $ordersQuery)->pluck('id');
        $totalQuantitySold = OrderItem::whereIn('order_id', $orderIds)->sum('quantity');

        // Gross Profit
        $grossProfit = $totalRevenue - $totalHpp;

        // Net Profit (for selected customers, we don't include general expenses)
        // Only deduct operational costs related to these orders
        $netProfit = $grossProfit - $totalOperational;

        // Build breakdown for audit trail
        $breakdown = [];
        if (!empty($customerIds)) {
            $customers = Customer::whereIn('id', $customerIds)->get();
            foreach ($customers as $customer) {
                $customerOrders = Order::whereBetween('order_date', [$startDate, $endDate])
                    ->where('status', 'delivered')
                    ->where('customer_id', $customer->id);

                $customerOrderIds = (clone $customerOrders)->pluck('id');

                $breakdown[] = [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'revenue' => (clone $customerOrders)->sum('total'),
                    'hpp' => (clone $customerOrders)->sum('total_hpp'),
                    'operational' => (clone $customerOrders)->sum('operational_cost'),
                    'quantity_sold' => OrderItem::whereIn('order_id', $customerOrderIds)->sum('quantity'),
                ];
            }
        }

        return [
            'total_revenue' => $totalRevenue,
            'total_hpp' => $totalHpp,
            'total_operational' => $totalOperational,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'total_quantity_sold' => $totalQuantitySold,
            'breakdown' => $breakdown,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period')
                    ->label('Periode')
                    ->sortable(['year', 'month']),
                Tables\Columns\TextColumn::make('customer_count')
                    ->label('Jumlah Klien')
                    ->getStateUsing(function (ProfitDistribution $record): string {
                        $customerIds = $record->customer_ids ?? [];
                        if (empty($customerIds)) {
                            return 'Semua';
                        }
                        return count($customerIds) . ' klien';
                    }),
                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Total Penjualan')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('net_profit')
                    ->label('Laba Bersih')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('share_amount_eki')
                    ->label('Bagian Eki')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('share_amount_aldi')
                    ->label('Bagian Aldi')
                    ->money('IDR'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'finalized',
                    ])
                    ->formatStateUsing(fn(string $state) => $state === 'draft' ? 'Draft' : 'Final'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'finalized' => 'Final',
                    ]),
                Tables\Filters\SelectFilter::make('year')
                    ->label('Tahun')
                    ->options(array_combine(
                        range(date('Y') - 2, date('Y')),
                        range(date('Y') - 2, date('Y'))
                    )),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('year', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProfitDistributions::route('/'),
            'create' => Pages\CreateProfitDistribution::route('/create'),
            'edit' => Pages\EditProfitDistribution::route('/{record}/edit'),
        ];
    }
}
