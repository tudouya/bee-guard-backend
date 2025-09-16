<?php

namespace App\Filament\Admin\Resources\KnowledgeArticleResource\Pages;

use App\Filament\Admin\Resources\KnowledgeArticleResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditKnowledgeArticle extends EditRecord
{
    protected static string $resource = KnowledgeArticleResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;
}
