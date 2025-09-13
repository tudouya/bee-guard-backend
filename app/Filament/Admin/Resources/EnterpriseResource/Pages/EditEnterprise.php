<?php

namespace App\Filament\Admin\Resources\EnterpriseResource\Pages;

use App\Filament\Admin\Resources\EnterpriseResource;
use Filament\Resources\Pages\EditRecord;

class EditEnterprise extends EditRecord
{
    protected static string $resource = EnterpriseResource::class;

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl();
    }
}

