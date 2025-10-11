<?php

namespace App\Filament\Inspector\Resources\DetectionResource\Pages;

use App\Filament\Inspector\Resources\DetectionResource;
use App\Filament\Admin\Resources\DetectionResource\Pages\ListDetections as BaseListDetections;

class ListDetections extends BaseListDetections
{
    protected static string $resource = DetectionResource::class;
}
