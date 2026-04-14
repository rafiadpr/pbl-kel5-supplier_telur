<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinanceLogResource\Pages;
use App\Models\FinanceLog;
use App\Models\Order;
use Filament\Actions;
use Filament\Forms\Components as FormComponents;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class FinanceLogResource extends Resource
{
    protected static ?string $model = FinanceLog::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static string|\UnitEnum|null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Keuangan';
    protected static ?string $modelLabel = 'Log Keuangan';
    protected static ?string $pluralModelLabel = 'Log Keuangan';

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Informasi Transaksi')->schema([
                FormComponents\TextInput::make('reference_number')
                    ->label('No. Referensi')
                    ->disabled()
                    ->dehydrated(),
                FormComponents\Select::make('type')
                    ->label('Jenis')
                    ->options(FinanceLog::TYPES)
                    ->required()
                    ->live(),
                FormComponents\Select::make('category')
                    ->label('Kategori')
                    ->options(fn(Get $get) => match ($get('type')) {
                        'income' => [
                            'payment' => 'Pembayaran Order', 
                            'loan' => 'Pinjam dari Uang Sendiri', 
                            'return' => 'Kembalian dari Beli (Tambah stok, dll)', 
                            'consignment' => 'Uang titipan beli (Wortel, daun bawang, dll)', 
                            'balancing' => 'Balancing Uang di Web dan Rekening'
                        ],
                        'expense' => [
                            'operational' => 'Biaya Operasional',
                            'salary' => 'Gaji Karyawan',
                            'supplies' => 'Perlengkapan (Plastik, dll)',
                            'food' => 'Uang Makan',
                            'refund' => 'Refund',
                            'balancing' => 'Balancing Uang di Web dan Rekening',
                            'consignment' => 'Uang titipan beli (Wortel, daun bawang, dll)',
                            'profit' => 'Ambil Profit',
                            'other' => 'Lain-lain',
                        ],
                        default => FinanceLog::CATEGORIES,
                    })
                    ->required()
                    ->live(),
                FormComponents\Select::make('financial_account_id')
                    ->label('Sumber Dana / Akun')
                    ->relationship('financialAccount', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if (!$state) {
                            return;
                        }

                        $account = \App\Models\FinancialAccount::find($state);

                        if (!$account) {
                            return;
                        }

                        $name = $account->name;

                        // Cek apakah nama akun mengandung kata bank
                        $bankKeywords = ['bca', 'bri', 'bni', 'mandiri', 'bank', 'bsi', 'cimb', 'permata'];
                        foreach ($bankKeywords as $keyword) {
                            if (stripos($name, $keyword) !== false) {
                                $set('payment_method', 'transfer');
                                return;
                            }
                        }

                        // Cek apakah nama akun mengandung kata cash/tunai
                        if (stripos($name, 'cash') !== false || stripos($name, 'tunai') !== false) {
                            $set('payment_method', 'cash');
                            return;
                        }
                    })
                    ->helperText('Uang masuk/keluar dari akun mana?'),
                FormComponents\Select::make('order_id')
                    ->label('No. Invoice')
                    ->options(function () {
                        return Order::query()
                            ->whereIn('payment_status', ['unpaid', 'partial'])
                            ->pluck('invoice_number', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->visible(fn(Get $get) => $get('category') === 'payment')
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if ($state) {
                            $order = Order::find($state);
                            if ($order) {
                                $set('customer_id', $order->customer_id);
                                $set('amount', $order->remaining_balance);
                            }
                        } else {
                            $set('customer_id', null);
                            $set('amount', null);
                        }
                    }),
                FormComponents\Select::make('customer_id')
                    ->label('Pelanggan')
                    ->relationship('customer', 'name')
                    ->disabled()
                    ->dehydrated()
                    ->visible(fn(Get $get) => $get('category') === 'payment'),
                FormComponents\TextInput::make('amount')
                    ->label('Jumlah Pembayaran')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->helperText(fn(Get $get) => $get('category') === 'payment'
                        ? 'Ubah nominal jika pembayaran dicicil (Partial).'
                        : null),
                FormComponents\Select::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options(FinanceLog::PAYMENT_METHODS)
                    ->default('cash')
                    ->required(),
                FormComponents\DatePicker::make('transaction_date')
                    ->label('Tanggal Transaksi')
                    ->default(now())
                    ->required(),
                FormComponents\Textarea::make('description')
                    ->label('Keterangan')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('No. Referensi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Jenis')
                    ->colors([
                        'success' => 'income',
                        'danger' => 'expense',
                    ])
                    ->formatStateUsing(fn(string $state) => FinanceLog::TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->formatStateUsing(fn(string $state) => FinanceLog::CATEGORIES[$state] ?? $state),
                Tables\Columns\TextColumn::make('financialAccount.name')
                    ->label('Akun')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('order.invoice_number')
                    ->label('Invoice')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode')
                    ->formatStateUsing(fn(string $state) => FinanceLog::PAYMENT_METHODS[$state] ?? $state),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer')
                    ->label('Pelanggan')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipe Transaksi')
                    ->options([
                        'income' => 'Pemasukan',
                        'expense' => 'Pengeluaran',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(FinanceLog::CATEGORIES),
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
            ])
            ->defaultSort('transaction_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinanceLogs::route('/'),
            'create' => Pages\CreateFinanceLog::route('/create'),
            'edit' => Pages\EditFinanceLog::route('/{record}/edit'),
        ];
    }
}
