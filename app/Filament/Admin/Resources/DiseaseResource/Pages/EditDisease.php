<?php

namespace App\Filament\Admin\Resources\DiseaseResource\Pages;

use App\Filament\Admin\Resources\DiseaseResource;
use Filament\Resources\Pages\EditRecord;

class EditDisease extends EditRecord
{
    protected static string $resource = DiseaseResource::class;

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl();
    }
}

