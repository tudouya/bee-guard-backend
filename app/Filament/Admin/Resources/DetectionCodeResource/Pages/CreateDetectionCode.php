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
        $self = strtoupper((string) env('DETCODE_PREFIX_SELF', 'ZF'));
        $gift = strtoupper((string) env('DETCODE_PREFIX_ENTERPRISE', 'QY'));
        $data['prefix'] = $source === 'gift' ? $gift : $self;
        $data['code'] = $this->generateUniqueCode();
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
