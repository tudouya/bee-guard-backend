<?php

namespace App\Filament\Admin\Resources\DetectionCodeResource\Pages;

use App\Filament\Admin\Resources\DetectionCodeResource;
use App\Models\DetectionCode;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDetectionCode extends CreateRecord
{
    protected static string $resource = DetectionCodeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl();
    }

    /**
     * 确保服务端最终生成 prefix 与 code，避免前端态被篡改或冲突。
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $source = (string) ($data['source_type'] ?? 'self_paid');
        if ($source === 'gift') {
            $enterprisePrefix = null;
            $enterpriseId = (int) ($data['enterprise_id'] ?? 0);
            if ($enterpriseId > 0) {
                $enterprise = \App\Models\Enterprise::query()->find($enterpriseId);
                $enterprisePrefix = $enterprise?->code_prefix ?: null;
            }
            $data['prefix'] = $enterprisePrefix ?: DetectionCode::DEFAULT_PREFIX_GIFT;
        } else {
            $data['prefix'] = DetectionCode::DEFAULT_PREFIX_SELF;
        }
        $data['code'] = $this->generateUniqueCode();

        // Enforce invariant at creation time:
        // - If an assignee is provided, force status=assigned and stamp assigned_at
        // - If status is available, clear any assignee fields
        if (!empty($data['assigned_user_id'])) {
            $data['status'] = 'assigned';
            if (empty($data['assigned_at'])) {
                $data['assigned_at'] = now();
            }
        } elseif (($data['status'] ?? 'available') === 'available') {
            $data['assigned_user_id'] = null;
            $data['assigned_at'] = null;
        }
        return $data;
    }

    private function generateUniqueCode(): string
    {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $len = strlen($chars);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $out = '';
            for ($i = 0; $i < 10; $i++) {
                $out .= $chars[random_int(0, $len - 1)];
            }
            if (!preg_match('/[A-Z]/', $out)) {
                continue; // 至少包含一个字母
            }
            $exists = DetectionCode::query()->where('code', $out)->exists();
            if (! $exists) {
                return $out;
            }
        }

        // 理论碰撞极低，保险起见最后再返回一次随机（由 DB 唯一约束兜底）
        return strtoupper(bin2hex(random_bytes(5))); // 10 hex chars
    }
}
