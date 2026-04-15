<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Filament\Traits\InteractsWithCustomerAuth;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Warehouse;
use Filament\Actions;
use Filament\Forms\Components as FormComponents;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseResource extends Resource
{

    use InteractsWithCustomerAuth;

    protected static ?string $model = Purchase::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Pembelian Stok';
    protected static ?string $modelLabel = 'Pembelian';
    protected static ?string $pluralModelLabel = 'Pembelian';

    public static function canAccess(): bool
    {
        return static::isAdmin();
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Informasi Pembelian')->schema([
                FormComponents\TextInput::make('purchase_number')
                    ->label('No. Pembelian')
                    ->default(fn () => Purchase::generatePurchaseNumber())
                    ->disabled()
                    ->dehydrated(),
                FormComponents\DatePicker::make('transaction_date')
                    ->label('Tanggal Beli')
                    ->default(now())
                    ->required(),
                FormComponents\Select::make('financial_account_id')
                    ->label('Bayar Dari Akun')
                    ->options(FinancialAccount::where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->helperText('Saldo akun ini akan otomatis berkurang.'),
                FormComponents\Select::make('warehouse_id')
                    ->label('Masuk ke Gudang')
                    ->options(Warehouse::where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->helperText('Stok akan masuk ke gudang ini.'),
                FormComponents\TextInput::make('supplier_name')
                    ->label('Nama Supplier')
                    ->placeholder('Opsional'),
                FormComponents\Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Item Belanja')->schema([
                FormComponents\Repeater::make('batches')
                    ->relationship()
                    ->schema([
                        FormComponents\Select::make('product_id')
                            ->label('Produk')
                            ->options(Product::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if ($state) {
                                    $product = Product::find($state);
                                    if ($product) {
                                        $set('purchase_price', $product->hpp);
                                        $set('selling_price', $product->selling_price);
                                    }
                                }
                            })
                            ->columnSpan(1),
                        FormComponents\Hidden::make('batch_code')
                            ->default(fn () => 'BATCH-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4))),
                        FormComponents\Hidden::make('received_at')
                            ->default(fn () => now()->format('Y-m-d')),
                        FormComponents\Hidden::make('is_active')
                            ->default(true),
                        FormComponents\TextInput::make('quantity_initial')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                $set('quantity_remaining', $state);
                                self::calculateItemSubtotal($set, $get);
                            })
                            ->columnSpan(1),
                        FormComponents\Hidden::make('quantity_remaining')
                            ->dehydrated(),
                        FormComponents\TextInput::make('purchase_price')
                            ->label('Harga Beli (@)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateItemSubtotal($set, $get))
                            ->columnSpan(1),
                        FormComponents\TextInput::make('selling_price')
                            ->label('Harga Jual (@)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->columnSpan(1),
                        FormComponents\TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(1),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah Item')
                    ->reorderable(false)
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateTotal($set, $get)),
            ]),

            Section::make('Total')->schema([
                FormComponents\TextInput::make('total_amount')
                    ->label('Total Harus Dibayar')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated()
                    ->required(),
            ]),
        ]);
    }

    protected static function calculateItemSubtotal(Set $set, Get $get): void
    {
        $qty = (float) ($get('quantity_initial') ?? 0);
        $price = (float) ($get('purchase_price') ?? 0);
        $set('subtotal', $qty * $price);
    }

    protected static function calculateTotal(Set $set, Get $get): void
    {
        $batches = $get('batches') ?? [];
        $total = 0;

        foreach ($batches as $item) {
            $qty = (float) ($item['quantity_initial'] ?? 0);
            $price = (float) ($item['purchase_price'] ?? 0);
            $total += ($qty * $price);
        }

        $set('total_amount', $total);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('purchase_number')
                    ->label('No. Pembelian')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier_name')
                    ->label('Supplier')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('financialAccount.name')
                    ->label('Dibayar Dari'),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang Tujuan'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('batches_count')
                    ->label('Jumlah Item')
                    ->counts('batches')
                    ->alignCenter(),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('financial_account_id')
                    ->label('Akun Pembayaran')
                    ->relationship('financialAccount', 'name'),
            ])
            ->actions([
                Actions\ViewAction::make(),
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
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'view' => Pages\ViewPurchase::route('/{record}'),
        ];
    }
}
