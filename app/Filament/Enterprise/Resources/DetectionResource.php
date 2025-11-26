<?php

namespace App\Filament\Enterprise\Resources;

use App\Filament\Enterprise\Resources\DetectionResource\Pages;
use App\Models\Detection;
use App\Models\Enterprise;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DetectionResource extends Resource
{
    protected static ?string $model = Detection::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        // 仅查看，不提供编辑表单
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        // 非导航资源，表格仅作为兜底展示
        return $table
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('detectionCode')
                    ->label('检测号')
                    ->getStateUsing(fn (Detection $record) => optional($record->detectionCode)?->prefix . optional($record->detectionCode)?->code)
                    ->searchable(),
                TextColumn::make('status')->label('状态')->badge(),
                TextColumn::make('sample_no')->label('样品编号')->sortable(),
                TextColumn::make('tested_at')->label('检测完成')->date('Y-m-d')->sortable(),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->columns(2)
                ->schema([
                    Text::make('检测号')->weight('medium'),
                    Text::make(fn (Detection $record) => optional($record->detectionCode)?->prefix . optional($record->detectionCode)?->code ?? '—'),

                    Text::make('状态')->weight('medium'),
                    Text::make(fn (Detection $record) => (string) ($record->status ?? '—'))
                        ->badge()
                        ->color(fn (Detection $record) => match ($record->status) {
                            'pending' => 'gray',
                            'received' => 'warning',
                            'processing' => 'info',
                            'completed' => 'success',
                            default => 'gray',
                        }),

                    Text::make('蜂农昵称')->weight('medium'),
                    Text::make(fn (Detection $record) => $record->user?->display_name ?? '—'),

                    Text::make('蜂农手机号')->weight('medium'),
                    Text::make(fn (Detection $record) => $record->user?->phone ?? '—'),

                    Text::make('样品编号')->weight('medium'),
                    Text::make(fn (Detection $record) => $record->sample_no ?? '—'),

                    Text::make('样品类型')->weight('medium'),
                    Text::make(function (Detection $record) {
                        $labels = $record->sample_type_labels ?? [];
                        return ! empty($labels) ? implode('、', $labels) : '—';
                    })->columnSpan(2),
                ]),

            Section::make('时间线')
                ->columns(2)
                ->schema([
                    Text::make('取样时间')->weight('medium'),
                    Text::make(fn (Detection $record) => optional($record->sampled_at)?->format('Y-m-d') ?? '—'),

                    Text::make('检测完成')->weight('medium'),
                    Text::make(fn (Detection $record) => optional($record->tested_at)?->format('Y-m-d') ?? '—'),

                    Text::make('报告时间')->weight('medium'),
                    Text::make(fn (Detection $record) => optional($record->reported_at)?->format('Y-m-d') ?? '—'),
                ]),

            Section::make('结果概要')
                ->schema([
                    View::make('filament.enterprise.detection-result-summary')
                        ->viewData([
                            'fields' => self::resultFieldDefinitions(),
                        ]),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'view' => Pages\ViewDetection::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['detectionCode.enterprise', 'user']);

        $user = auth()->user();
        $isSuper = $user && (string) $user->role === 'super_admin';
        if ($isSuper) {
            return $query;
        }

        $enterpriseIds = Enterprise::query()
            ->where('owner_user_id', $user?->id)
            ->pluck('id');

        return $query->whereHas('detectionCode', fn ($q) => $q->whereIn('enterprise_id', $enterpriseIds));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();
        if ($user && (string) $user->role === 'super_admin') {
            return true;
        }

        $enterpriseId = optional($record->detectionCode)->enterprise_id;
        if (! $enterpriseId || ! $user) {
            return false;
        }

        return Enterprise::query()
            ->where('owner_user_id', $user->id)
            ->where('id', $enterpriseId)
            ->exists();
    }

    /**
     * @return array<string,string>
     */
    public static function resultFieldDefinitions(): array
    {
        return [
            'rna_iapv_level' => '以色列急性麻痹病毒 (IAPV)',
            'rna_bqcv_level' => '黑蜂王台病毒 (BQCV)',
            'rna_sbv_level' => '囊状幼虫病毒 (SBV)',
            'rna_abpv_level' => '急性蜜蜂麻痹病毒 (ABPV)',
            'rna_cbpv_level' => '慢性蜜蜂麻痹病毒 (CBPV)',
            'rna_dwv_level' => '畸形翅病毒 (DWV)',
            'dna_afb_level' => '美洲幼虫腐臭病 (AFB)',
            'dna_efb_level' => '欧洲幼虫腐臭病 (EFB)',
            'dna_ncer_level' => '鼻孢虫 (NCER)',
            'dna_napi_level' => '微孢子虫 (NAPI)',
            'dna_cb_level' => '白垩病 (CB)',
        ];
    }

    public static function formatLevelLabel(?string $level): string
    {
        return match ($level) {
            'strong' => '强',
            'medium' => '中',
            'weak' => '弱',
            default => '未检出',
        };
    }

    public static function formatLevelColor(?string $level): string
    {
        return match ($level) {
            'strong' => 'danger',
            'medium' => 'warning',
            'weak' => 'info',
            default => 'gray',
        };
    }
}
