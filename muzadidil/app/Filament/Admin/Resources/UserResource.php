<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Pelanggan';

    protected static ?string $pluralModelLabel = 'Pelanggan';

    protected static ?string $modelLabel = 'Pelanggan';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', User::ROLE_CUSTOMER);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('phone')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->maxLength(255),
            Forms\Components\DateTimePicker::make('phone_verified_at')->disabled(),
            Forms\Components\DateTimePicker::make('blocked_at')->disabled(),
            Forms\Components\Textarea::make('blocked_reason')->disabled()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('average_rating')->label('Rating')->numeric(2),
                Tables\Columns\IconColumn::make('blocked_at')
                    ->label('Blocked')
                    ->boolean()
                    ->trueIcon('heroicon-o-no-symbol')
                    ->falseIcon('heroicon-o-check-circle'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('blocked_at')
                    ->label('Status blokir')
                    ->placeholder('Semua')
                    ->trueLabel('Hanya yang diblokir')
                    ->falseLabel('Hanya yang aktif')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('blocked_at'),
                        false: fn (Builder $q) => $q->whereNull('blocked_at'),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('block')
                    ->label('Blokir')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (User $u) => $u->blocked_at === null)
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Alasan')->required()->maxLength(500),
                    ])
                    ->action(function (User $u, array $data) {
                        $u->blocked_at = now();
                        $u->blocked_reason = $data['reason'];
                        $u->save();
                        Notification::make()->title('Pelanggan diblokir')->success()->send();
                    }),
                Tables\Actions\Action::make('unblock')
                    ->label('Buka blokir')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $u) => $u->blocked_at !== null)
                    ->requiresConfirmation()
                    ->action(function (User $u) {
                        $u->blocked_at = null;
                        $u->blocked_reason = null;
                        $u->save();
                        Notification::make()->title('Blokir dibuka')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
