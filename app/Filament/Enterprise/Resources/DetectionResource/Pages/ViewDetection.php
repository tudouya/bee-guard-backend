<?php

namespace App\Filament\Enterprise\Resources\DetectionResource\Pages;

use App\Filament\Enterprise\Resources\DetectionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDetection extends ViewRecord
{
    protected static string $resource = DetectionResource::class;

    protected function getActions(): array
    {
        return []; // 仅查看，移除编辑/删除
    }

    public function getBreadcrumbs(): array
    {
        // 避免引用不存在的 index 页，简化为仅当前记录
        return [
            $this->getTitle() => null,
        ];
    }
}
