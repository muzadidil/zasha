<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TopupRequestResource\Pages;
use App\Models\TopupRequest;
use App\Services\Wallet\WalletService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TopupRequestResource extends Resource
{
    protected static ?string $model = TopupRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Top-up';

    protected static ?string $pluralModelLabel = 'Top-up Request';

    protected static ?string $modelLabel = 'Top-up';

    public static function getNavigationBadge(): ?string
    {
        return (string) TopupRequest::where('status', TopupRequest::STATUS_PENDING)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('wallet.user.name')->label('Mitra')->searchable(),
                Tables\Columns\TextColumn::make('wallet.user.phone')->label('HP')->searchable(),
                Tables\Columns\TextColumn::make('amount')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('proof_url')
                    ->label('Bukti')
                    ->url(fn ($state) => $state)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn () => 'Lihat'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('processed_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (TopupRequest $r) => $r->status === TopupRequest::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (TopupRequest $r) {
                        try {
                            app(WalletService::class)->approveTopup($r->id, auth()->user());
                            Notification::make()->title('Top-up disetujui')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal: ' . $e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (TopupRequest $r) => $r->status === TopupRequest::STATUS_PENDING)
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Alasan')->required()->maxLength(500),
                    ])
                    ->action(function (TopupRequest $r, array $data) {
                        try {
                            app(WalletService::class)->rejectTopup($r->id, auth()->user(), $data['reason']);
                            Notification::make()->title('Top-up ditolak')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal: ' . $e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTopupRequests::route('/'),
        ];
    }
}
