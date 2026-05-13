<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ServiceCategoryResource\Pages;
use App\Filament\Admin\Resources\ServiceCategoryResource\RelationManagers;
use App\Models\ServiceCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServiceCategoryResource extends Resource
{
    protected static ?string $model = ServiceCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(32),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(64),
                Forms\Components\TextInput::make('min_price')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('price_step')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('max_partners')
                    ->required()
                    ->numeric(),
                Forms\Components\Toggle::make('requires_geolocation')
                    ->required(),
                Forms\Components\TextInput::make('search_radius_km')
                    ->numeric()
                    ->helperText('Max radius (km). Null untuk WFH.')
                    ->default(null),
                Forms\Components\TagsInput::make('radius_steps')
                    ->placeholder('1, 2, 3, 4')
                    ->helperText('Tahapan radius (km) untuk V2 broadcast bertahap. Kosongkan untuk WFH.')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('step_duration_seconds')
                    ->required()
                    ->numeric()
                    ->helperText('Detik per step (default 15).')
                    ->default(15),
                Forms\Components\TextInput::make('search_timeout_minutes')
                    ->required()
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('commission_percent')
                    ->required()
                    ->numeric()
                    ->default(5.00),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('min_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_step')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_partners')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('requires_geolocation')
                    ->boolean(),
                Tables\Columns\TextColumn::make('search_radius_km')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('step_duration_seconds')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('search_timeout_minutes')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_percent')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListServiceCategories::route('/'),
            'create' => Pages\CreateServiceCategory::route('/create'),
            'edit' => Pages\EditServiceCategory::route('/{record}/edit'),
        ];
    }
}
