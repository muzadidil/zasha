<?php

namespace App\Filament\Admin\Resources\TopupRequestResource\Pages;

use App\Filament\Admin\Resources\TopupRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTopupRequests extends ListRecords
{
    protected static string $resource = TopupRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
