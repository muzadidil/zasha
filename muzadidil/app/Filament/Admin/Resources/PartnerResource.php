<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PartnerResource\Pages;
use App\Models\PartnerProfile;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PartnerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'partners';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Mitra';

    protected static ?string $pluralModelLabel = 'Mitra';

    protected static ?string $modelLabel = 'Mitra';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('role', User::ROLE_PARTNER)
            ->with('partnerProfile');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('partnerProfile.is_verified')
                    ->label('Verified')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Verified' : 'Pending')
                    ->color(fn ($state) => $state ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('partnerProfile.ktp_number')
                    ->label('KTP')
                    ->formatStateUsing(fn ($state) => $state ? '****' . Str::substr($state, -4) : '—'),
                Tables\Columns\TextColumn::make('partnerProfile.bank_account')
                    ->label('Rekening')
                    ->formatStateUsing(fn ($state) => $state ? '****' . Str::substr($state, -4) : '—'),
                Tables\Columns\TextColumn::make('average_rating')->label('Rating')->numeric(2),
                Tables\Columns\IconColumn::make('blocked_at')
                    ->label('Blocked')
                    ->boolean()
                    ->trueIcon('heroicon-o-no-symbol')
                    ->falseIcon('heroicon-o-check-circle'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('verified')
                    ->placeholder('Semua')
                    ->trueLabel('Verified')
                    ->falseLabel('Pending')
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('partnerProfile', fn ($p) => $p->where('is_verified', true)),
                        false: fn (Builder $q) => $q->whereHas('partnerProfile', fn ($p) => $p->where('is_verified', false)),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (User $u) => $u->partnerProfile && ! $u->partnerProfile->is_verified)
                    ->requiresConfirmation()
                    ->action(function (User $u) {
                        $profile = $u->partnerProfile;
                        $profile->is_verified = true;
                        $profile->verified_at = now();
                        $profile->save();
                        Notification::make()->title('Mitra diverifikasi')->success()->send();
                    }),
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
                        Notification::make()->title('Mitra diblokir')->success()->send();
                    }),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Identitas')->schema([
                Infolists\Components\TextEntry::make('name'),
                Infolists\Components\TextEntry::make('phone'),
                Infolists\Components\TextEntry::make('email')->placeholder('—'),
                Infolists\Components\TextEntry::make('partnerProfile.is_verified')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state ? 'Verified' : 'Pending'),
            ])->columns(2),
            Infolists\Components\Section::make('Profil Mitra')->schema([
                Infolists\Components\TextEntry::make('partnerProfile.ktp_number')
                    ->label('KTP (masked)')
                    ->formatStateUsing(fn ($state) => $state ? '****' . Str::substr($state, -4) : '—'),
                Infolists\Components\TextEntry::make('partnerProfile.bank_name')->label('Bank'),
                Infolists\Components\TextEntry::make('partnerProfile.bank_account')
                    ->label('Rekening (masked)')
                    ->formatStateUsing(fn ($state) => $state ? '****' . Str::substr($state, -4) : '—'),
                Infolists\Components\TextEntry::make('partnerProfile.service_categories')
                    ->label('Kategori')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : '—'),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartners::route('/'),
            'view' => Pages\ViewPartner::route('/{record}'),
        ];
    }
}
