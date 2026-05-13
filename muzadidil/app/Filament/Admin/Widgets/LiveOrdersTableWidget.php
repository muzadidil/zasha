<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LiveOrdersTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Order terbaru';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->with(['serviceCategory', 'customer', 'partner'])
                    ->orderByDesc('created_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->formatStateUsing(fn ($state) => '#' . substr((string) $state, -6)),
                Tables\Columns\TextColumn::make('serviceCategory.name')->label('Kategori'),
                Tables\Columns\TextColumn::make('customer.name')->label('Pelanggan'),
                Tables\Columns\TextColumn::make('current_price')->money('IDR'),
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
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->recordUrl(fn (Order $r) => OrderResource::getUrl('view', ['record' => $r]));
    }
}
