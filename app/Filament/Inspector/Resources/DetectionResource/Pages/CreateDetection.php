<?php

namespace App\Filament\Inspector\Resources\DetectionResource\Pages;

use App\Filament\Inspector\Resources\DetectionResource;
use App\Filament\Admin\Resources\DetectionResource\Pages\CreateDetection as BaseCreateDetection;

class CreateDetection extends BaseCreateDetection
{
    protected static string $resource = DetectionResource::class;
}
