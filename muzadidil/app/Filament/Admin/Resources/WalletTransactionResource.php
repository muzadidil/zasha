<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WalletTransactionResource\Pages;
use App\Models\WalletTransaction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Wallet Transactions';

    protected static ?string $pluralModelLabel = 'Wallet Transactions';

    protected static ?string $modelLabel = 'Wallet Transaction';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('wallet.user.name')->label('Mitra')->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'topup' => 'success',
                        'fee' => 'warning',
                        'refund' => 'info',
                        'adjustment' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->money('IDR')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('balance_after')->money('IDR'),
                Tables\Columns\TextColumn::make('reference_id')->label('Ref')->placeholder('—'),
                Tables\Columns\TextColumn::make('notes')->limit(40)->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'topup' => 'Top-up',
                        'fee' => 'Fee',
                        'refund' => 'Refund',
                        'adjustment' => 'Adjustment',
                    ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWalletTransactions::route('/'),
        ];
    }
}
