<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Order';

    protected static ?string $pluralModelLabel = 'Order';

    protected static ?string $modelLabel = 'Order';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->formatStateUsing(fn ($state) => '#' . substr((string) $state, -6))
                    ->searchable(),
                Tables\Columns\TextColumn::make('serviceCategory.name')->label('Kategori'),
                Tables\Columns\TextColumn::make('customer.name')->label('Pelanggan')->searchable(),
                Tables\Columns\TextColumn::make('partner.name')->label('Mitra')->default('—'),
                Tables\Columns\TextColumn::make('current_price')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'searching' => 'warning',
                        'claimed', 'in_progress' => 'info',
                        'completed' => 'success',
                        'cancelled', 'expired' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('active_radius_km')
                    ->label('Radius')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} km" : '—'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Order::STATUS_SEARCHING => 'Searching',
                        Order::STATUS_CLAIMED => 'Claimed',
                        Order::STATUS_IN_PROGRESS => 'In Progress',
                        Order::STATUS_COMPLETED => 'Completed',
                        Order::STATUS_CANCELLED => 'Cancelled',
                        Order::STATUS_EXPIRED => 'Expired',
                    ]),
                Tables\Filters\SelectFilter::make('service_category_id')
                    ->label('Kategori')
                    ->relationship('serviceCategory', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Order')->schema([
                Infolists\Components\TextEntry::make('id')->label('ID'),
                Infolists\Components\TextEntry::make('serviceCategory.name')->label('Kategori'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('current_price')->money('IDR'),
                Infolists\Components\TextEntry::make('customer.name')->label('Pelanggan'),
                Infolists\Components\TextEntry::make('partner.name')->label('Mitra')->placeholder('—'),
                Infolists\Components\TextEntry::make('created_at')->dateTime(),
                Infolists\Components\TextEntry::make('claimed_at')->dateTime()->placeholder('—'),
                Infolists\Components\TextEntry::make('completed_at')->dateTime()->placeholder('—'),
            ])->columns(3),

            Infolists\Components\Section::make('Search Progress')
                ->visible(fn (Order $r) => $r->serviceCategory?->requires_geolocation)
                ->schema([
                    Infolists\Components\TextEntry::make('active_radius_km')
                        ->label('Radius aktif')
                        ->formatStateUsing(fn ($state) => $state ? "{$state} km" : '—'),
                    Infolists\Components\TextEntry::make('current_step_index')
                        ->label('Step index')
                        ->placeholder('—'),
                    Infolists\Components\RepeatableEntry::make('radiusExpansions')
                        ->label('Radius Expansion Timeline')
                        ->schema([
                            Infolists\Components\TextEntry::make('step_index')->label('Step'),
                            Infolists\Components\TextEntry::make('from_radius_km')
                                ->label('Dari')
                                ->formatStateUsing(fn ($state) => $state ? "{$state} km" : '—'),
                            Infolists\Components\TextEntry::make('to_radius_km')
                                ->label('Ke')
                                ->formatStateUsing(fn ($state) => "{$state} km"),
                            Infolists\Components\TextEntry::make('partners_notified')->label('Mitra dapat broadcast'),
                            Infolists\Components\TextEntry::make('expanded_at')->label('Waktu')->dateTime(),
                        ])->columns(5),
                ]),

            Infolists\Components\Section::make('Status Logs')->schema([
                Infolists\Components\RepeatableEntry::make('statusLogs')
                    ->label('')
                    ->schema([
                        Infolists\Components\TextEntry::make('from_status')->label('Dari')->placeholder('—'),
                        Infolists\Components\TextEntry::make('to_status')->label('Ke'),
                        Infolists\Components\TextEntry::make('changed_by')->label('Oleh')->placeholder('system'),
                        Infolists\Components\TextEntry::make('reason')->placeholder('—'),
                        Infolists\Components\TextEntry::make('created_at')->label('Waktu')->dateTime(),
                    ])->columns(5),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
