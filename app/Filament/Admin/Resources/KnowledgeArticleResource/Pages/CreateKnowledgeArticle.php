<?php

namespace App\Filament\Admin\Resources\KnowledgeArticleResource\Pages;

use App\Filament\Admin\Resources\KnowledgeArticleResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateKnowledgeArticle extends CreateRecord
{
    protected static string $resource = KnowledgeArticleResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;
}
