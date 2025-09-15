<?php

namespace App\Filament\Admin\Resources\DetectionResource\Pages;

use App\Filament\Admin\Resources\DetectionResource;
use App\Models\DetectionCode;
use Illuminate\Validation\ValidationException;
use Filament\Resources\Pages\CreateRecord;

class CreateDetection extends CreateRecord
{
    protected static string $resource = DetectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $code = DetectionCode::query()->find($data['detection_code_id'] ?? null);
        if (!$code) {
            throw ValidationException::withMessages([
                'detection_code_id' => '检测码不存在',
            ]);
        }
        if (!$code->assigned_user_id) {
            throw ValidationException::withMessages([
                'detection_code_id' => '该检测码尚未绑定用户，无法录入报告',
            ]);
        }
        // 回填 user_id（如果被清空或未设置）
        $data['user_id'] = $data['user_id'] ?? $code->assigned_user_id;
        if (!$data['user_id']) {
            throw ValidationException::withMessages([
                'user_id' => '未能确定用户，请检查检测码的绑定情况',
            ]);
        }
        return $data;
    }
}
