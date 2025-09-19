<?php

namespace App\Filament\Enterprise\Resources;

use App\Models\DetectionCode;
use App\Models\Enterprise;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DetectionCodeResource extends Resource
{
    protected static ?string $model = DetectionCode::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Detection Codes';
    protected static \UnitEnum|string|null $navigationGroup = 'Business';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->toggleable(),
                TextColumn::make('full_code')
                    ->label('Code')
                    ->getStateUsing(fn ($record) => (string)($record->prefix.$record->code))
                    ->searchable(query: function ($query, $search) {
                        $query->whereRaw("CONCAT(prefix, code) LIKE ?", ['%'.$search.'%']);
                    })
                    ->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('assignedUser.display_name')->label('Assigned User')->toggleable(),
                TextColumn::make('assigned_at')->dateTime()->sortable(),
                TextColumn::make('used_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'available' => 'Available',
                    'assigned' => 'Assigned',
                    'used' => 'Used',
                    'expired' => 'Expired',
                ]),
            ])
            ->actions([
                Action::make('assignToPhone')
                    ->label('分配检测码')
                    ->modalHeading('分配检测码')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        TextInput::make('phone')
                            ->label('手机号')
                            ->required()
                            ->rules(['regex:/^1[3-9]\d{9}$/'])
                            ->placeholder('请输入11位手机号'),
                    ])
                    ->action(function (DetectionCode $record, array $data) {
                        $user = auth()->user();
                        $isSuper = $user && (string) $user->role === 'super_admin';
                        if (! $isSuper) {
                            $enterpriseIds = Enterprise::query()->where('owner_user_id', $user->id)->pluck('id')->all();
                            if (empty($enterpriseIds) || !in_array((int) $record->enterprise_id, array_map('intval', $enterpriseIds), true)) {
                                throw ValidationException::withMessages(['phone' => ['无权分配该检测码']]);
                            }
                        }

                        $phone = (string) ($data['phone'] ?? '');
                        $target = User::query()->where('phone', $phone)->first();
                        if (!$target) {
                            throw ValidationException::withMessages(['phone' => ['用户不存在']]);
                        }

                        DB::transaction(function () use ($record, $target) {
                            $code = DetectionCode::query()->where('id', $record->id)->lockForUpdate()->first();
                            if (!$code) {
                                throw ValidationException::withMessages(['phone' => ['检测码不存在']]);
                            }
                            if (in_array($code->status, ['used','expired'], true)) {
                                throw ValidationException::withMessages(['phone' => ['检测码状态不可分配']]);
                            }
                            if ($code->status === 'assigned') {
                                if ((int) $code->assigned_user_id === (int) $target->id) {
                                    // 幂等：已分配给该用户
                                    return;
                                }
                                throw ValidationException::withMessages(['phone' => ['检测码已分配给其他用户']]);
                            }

                            // available → assigned
                            $updated = DetectionCode::query()
                                ->where('id', $code->id)
                                ->where('status', 'available')
                                ->update([
                                    'status' => 'assigned',
                                    'assigned_user_id' => $target->id,
                                    'assigned_at' => now(),
                                ]);
                            if ($updated < 1) {
                                throw ValidationException::withMessages(['phone' => ['检测码状态已变化，请刷新后重试']]);
                            }
                        });
                    })
                    ->successNotificationTitle('分配成功'),
            ])
            ->bulkActions([])
            ->headerActions([])
            ->modifyQueryUsing(function ($query) {
                $user = auth()->user();
                $isSuper = $user && (string) $user->role === 'super_admin';
                if ($isSuper) {
                    return; // 超管查看所有企业检测码
                }
                $enterpriseIds = Enterprise::query()->where('owner_user_id', $user->id)->pluck('id');
                $query->whereIn('enterprise_id', $enterpriseIds);
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Enterprise\Resources\DetectionCodeResource\Pages\ListDetectionCodes::route('/'),
        ];
    }
}
