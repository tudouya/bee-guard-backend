<?php

namespace App\Filament\Admin\Resources\DetectionCodeResource\Pages;

use App\Filament\Admin\Resources\DetectionCodeResource;
use App\Models\DetectionCode;
use App\Models\Enterprise;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ListDetectionCodes extends ListRecords
{
    protected static string $resource = DetectionCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            // 批量生成检测码（管理员）
            Actions\Action::make('bulkGenerate')
                ->label('批量生成')
                ->icon('heroicon-o-sparkles')
                ->modalHeading('批量生成检测码')
                ->modalSubmitActionLabel('开始生成')
                ->form([
                    Select::make('source_type')
                        ->label('来源')
                        ->options([
                            'gift' => 'Gift（企业）',
                            'self_paid' => 'Self Paid（自费）',
                        ])
                        ->required()
                        ->native(false)
                        ->live(),

                    Select::make('enterprise_id')
                        ->label('企业')
                        ->options(fn () => Enterprise::query()
                            ->orderBy('name')
                            ->select(['id','name','code_prefix'])
                            ->get()
                            ->mapWithKeys(fn ($e) => [
                                $e->id => $e->code_prefix ? ($e->name.' · '.$e->code_prefix) : $e->name,
                            ])->all())
                        ->preload()
                        ->searchable()
                        ->visible(fn (callable $get) => $get('source_type') === 'gift')
                        ->required(fn (callable $get) => $get('source_type') === 'gift'),

                    TextInput::make('count')
                        ->label('数量')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(10000)
                        ->default(100)
                        ->required(),

                    TextInput::make('note')
                        ->label('备注（可选）')
                        ->maxLength(191),
                ])
                ->action(function (array $data) {
                    $source = (string) ($data['source_type'] ?? 'self_paid');
                    $count = (int) ($data['count'] ?? 0);
                    if ($count < 1 || $count > 10000) {
                        throw ValidationException::withMessages(['count' => ['数量必须在 1 到 10000 之间']]);
                    }

                    $enterpriseId = null;
                    $prefix = DetectionCode::DEFAULT_PREFIX_SELF;
                    if ($source === 'gift') {
                        $enterpriseId = (int) ($data['enterprise_id'] ?? 0);
                        if ($enterpriseId <= 0) {
                            throw ValidationException::withMessages(['enterprise_id' => ['请选择企业']]);
                        }
                        $enterprise = Enterprise::query()->find($enterpriseId);
                        if (!$enterprise) {
                            throw ValidationException::withMessages(['enterprise_id' => ['企业不存在']]);
                        }
                        $prefix = $enterprise->code_prefix ?: DetectionCode::DEFAULT_PREFIX_GIFT;
                    } else {
                        $prefix = DetectionCode::DEFAULT_PREFIX_SELF;
                    }

                    $batchId = (string) Str::uuid();
                    $note = trim((string) ($data['note'] ?? ''));
                    $inserted = 0;
                    $attempts = 0;
                    $maxAttempts = 30; // 防止极端情况下的无限循环

                    while ($inserted < $count && $attempts < $maxAttempts) {
                        $attempts++;
                        $now = now();
                        $remaining = $count - $inserted;
                        $chunk = min(1000, $remaining);

                        // 在内存中去重，避免同批次候选冲突
                        $codes = [];
                        $set = [];
                        while (count($codes) < $chunk) {
                            $candidate = self::randomCode();
                            if (!isset($set[$candidate])) {
                                $set[$candidate] = true;
                                $codes[] = $candidate;
                            }
                        }

                        $rows = [];
                        foreach ($codes as $code) {
                            $meta = ['batch_id' => $batchId];
                            if ($note !== '') {
                                $meta['note'] = $note;
                            }
                            $rows[] = [
                                'code' => $code,
                                'source_type' => $source,
                                'prefix' => $prefix,
                                'status' => 'available',
                                'enterprise_id' => $enterpriseId ?: null,
                                'assigned_user_id' => null,
                                'assigned_at' => null,
                                'used_at' => null,
                                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }

                        // 使用 insertOrIgnore 以唯一约束兜底；返回成功插入的数量
                        $affected = DB::table('detection_codes')->insertOrIgnore($rows);
                        $inserted += (int) $affected;
                    }

                    Notification::make()
                        ->title('生成成功')
                        ->body('本次共生成 '.$inserted.' 枚检测码（批次：'.$batchId.'）')
                        ->success()
                        ->send();
                })
                ->successRedirectUrl(static::getResource()::getUrl('index')), // 完成后仍留在列表页
        ];
    }
    // 生成 10 位大写字母数字编码（至少包含一个字母，剔除易混字符 IO01）
    protected static function randomCode(): string
    {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $len = strlen($chars);
        do {
            $out = '';
            for ($i = 0; $i < 10; $i++) {
                $out .= $chars[random_int(0, $len - 1)];
            }
        } while (!preg_match('/[A-Z]/', $out));
        return $out;
    }
}
