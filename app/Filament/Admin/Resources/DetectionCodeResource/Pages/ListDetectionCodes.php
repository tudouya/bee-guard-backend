<?php

namespace App\Filament\Admin\Resources\DetectionCodeResource\Pages;

use App\Filament\Admin\Resources\DetectionCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDetectionCodes extends ListRecords
{
    protected static string $resource = DetectionCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

