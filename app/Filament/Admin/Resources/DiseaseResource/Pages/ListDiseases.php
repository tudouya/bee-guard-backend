<?php

namespace App\Filament\Admin\Resources\DiseaseResource\Pages;

use App\Filament\Admin\Resources\DiseaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDiseases extends ListRecords
{
    protected static string $resource = DiseaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

