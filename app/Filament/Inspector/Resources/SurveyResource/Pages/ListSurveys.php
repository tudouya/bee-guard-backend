<?php

namespace App\Filament\Inspector\Resources\SurveyResource\Pages;

use App\Filament\Inspector\Resources\SurveyResource;
use App\Filament\Admin\Resources\SurveyResource\Pages\ListSurveys as BaseListSurveys;

class ListSurveys extends BaseListSurveys
{
    protected static string $resource = SurveyResource::class;
}
