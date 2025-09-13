<?php

namespace App\Filament\Admin\Resources\DetectionCodeResource\Pages;

use App\Filament\Admin\Resources\DetectionCodeResource;
use Filament\Resources\Pages\EditRecord;

class EditDetectionCode extends EditRecord
{
    protected static string $resource = DetectionCodeResource::class;

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl();
    }
}

