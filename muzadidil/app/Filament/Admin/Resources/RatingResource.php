<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RatingResource\Pages;
use App\Models\Rating;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RatingResource extends Resource
{
    protected static ?string $model = Rating::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Rating';

    protected static ?string $pluralModelLabel = 'Ratings';

    protected static ?string $modelLabel = 'Rating';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Order')
                    ->formatStateUsing(fn ($state) => '#' . substr((string) $state, -6))
                    ->searchable(),
                Tables\Columns\TextColumn::make('rater.name')->label('Pemberi')->searchable(),
                Tables\Columns\TextColumn::make('rater_role')
                    ->badge()
                    ->color(fn (string $state) => $state === 'customer' ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('ratee.name')->label('Penerima')->searchable(),
                Tables\Columns\TextColumn::make('stars')->label('⭐')->numeric(),
                Tables\Columns\TextColumn::make('comment')->limit(50)->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rater_role')
                    ->options(['customer' => 'Customer', 'partner' => 'Partner']),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRatings::route('/'),
        ];
    }
}
