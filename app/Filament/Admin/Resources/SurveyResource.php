<?php

namespace App\Filament\Admin\Resources;

use App\Models\Survey;
use App\Support\AdminNavigation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = '问卷资料';
    protected static \UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_DETECTION_OPERATIONS;
    protected static ?int $navigationSort = AdminNavigation::ORDER_SURVEYS;

    public static function form(Schema $schema): Schema
    {
        // 仅查看，不提供表单
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // 点击行跳转到详情页
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('submitted_at')->label('提交时间')->dateTime()->sortable(),
                BadgeColumn::make('status')
                    ->label('状态')
                    ->colors([
                        'gray' => '草稿',
                        'success' => '已提交',
                    ])
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'draft' => '草稿',
                        'submitted' => '已提交',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('owner_name')->label('蜂农')->searchable(),
                TextColumn::make('phone')->label('联系电话')->searchable(),
                TextColumn::make('bee_count')->label('蜂群数量')->sortable()->toggleable(),
                BadgeColumn::make('raise_method')->label('养殖方式')->colors([
                    'info' => '定地',
                    'warning' => '省内小转地',
                    'danger' => '跨省大转地',
                ])->toggleable(),
                TextColumn::make('bee_species')->label('蜂种')->toggleable(),

                BadgeColumn::make('is_production_now')->label('当前采蜜期')
                    ->colors(['success' => '是', 'gray' => '否'])
                    ->toggleable(),
                TextColumn::make('product_type')->label('主要产品')->toggleable(),
                TextColumn::make('next_month')->label('下月计划')->toggleable(),
                BadgeColumn::make('has_abnormal')->label('异常情况')
                    ->colors(['danger' => '是', 'gray' => '否'])
                    ->toggleable(),

                TextColumn::make('detectionCodeFull')
                    ->label('关联检测号')
                    ->getStateUsing(fn ($record) => optional($record->detectionCode)->prefix . optional($record->detectionCode)->code)
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('detectionCode', function ($q) use ($search) {
                            $q->whereRaw("CONCAT(prefix, code) LIKE ?", ['%' . $search . '%']);
                        });
                    })
                    ->toggleable(),
                BadgeColumn::make('detectionCode.source_type')->label('来源类型')
                    ->colors(['primary' => 'self_paid', 'warning' => 'gift'])
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'self_paid' => '自费',
                        'gift' => '企业赠送',
                        default => $state,
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('detectionCode.enterprise.name')->label('所属企业')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('is_production_now')->options(['是' => '是', '否' => '否'])->label('当前采蜜期'),
                SelectFilter::make('has_abnormal')->options(['是' => '是', '否' => '否'])->label('异常情况'),
                SelectFilter::make('source_type')->label('来源类型')->options([
                    'self_paid' => '自费',
                    'gift' => '企业赠送',
                ])->query(function ($query, $data) {
                    if (!empty($data['value'])) {
                        $query->whereHas('detectionCode', fn ($q) => $q->where('source_type', $data['value']));
                    }
                }),
                SelectFilter::make('enterprise_id')->label('所属企业')
                    ->relationship('detectionCode.enterprise', 'name'),
            ])
            // 行为：行已可点击进入详情，省略额外“查看”按钮以避免类兼容问题
            ->actions([])
            ->bulkActions([])
            ->modifyQueryUsing(fn ($query) => $query->with(['detectionCode.enterprise', 'user']));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->icon('heroicon-o-clipboard-document-check')
                ->collapsible()
                ->columns(4)
                ->schema([
                    Text::make('提交时间')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => optional($record->submitted_at)?->format('Y-m-d H:i') ?: '—')->columnSpan(1),

                    Text::make('状态')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => $record->status)
                        ->badge()
                        ->color(fn (Survey $record) => match ($record->status) {
                            'submitted' => 'success',
                            'draft' => 'gray',
                            default => 'gray',
                        })
                        ->columnSpan(1),

                    Text::make('用户ID')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) $record->user_id)->columnSpan(1),

                    Text::make('检测号')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(function (Survey $record) {
                        $code = $record->detectionCode;
                        return $code ? ($code->prefix . $code->code) : '—';
                    })->columnSpan(1),

                    Text::make('来源')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => $record->detectionCode?->source_type ?? '—')
                        ->badge()
                        ->color(fn (Survey $record) => match ($record->detectionCode?->source_type) {
                            'self_paid' => 'primary',
                            'gift' => 'warning',
                            default => 'gray',
                        })
                        ->columnSpan(1),

                    Text::make('企业')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => $record->detectionCode?->enterprise?->name ?? '—')->columnSpan(3),
                ]),

            Section::make('填写时间')
                ->icon('heroicon-o-clock')
                ->collapsible()
                ->columns(4)
                ->schema([
                    Text::make('填写日期')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => optional($record->fill_date)?->format('Y-m-d') ?: '—')->columnSpan(1),

                    Text::make('填写时间')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => optional($record->fill_time)?->format('H:i') ?: '—')->columnSpan(1),
                ]),

            Section::make('场地与联系方式')
                ->icon('heroicon-o-home')
                ->collapsible()
                ->columns(4)
                ->schema([
                    Text::make('场主')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) $record->owner_name)->columnSpan(1),

                    Text::make('地址')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => $record->location_name ?: '—')->columnSpan(3),

                    Text::make('手机号')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) $record->phone)->columnSpan(1),

                    Text::make('蜂群数量')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) $record->bee_count)->columnSpan(1),

                    Text::make('饲养方式')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) $record->raise_method)
                        ->badge()
                        ->color(fn (Survey $record) => match ($record->raise_method) {
                            '定地' => 'info',
                            '省内小转地' => 'warning',
                            '跨省大转地' => 'danger',
                            default => 'gray',
                        })
                        ->columnSpan(1),

                    Text::make('蜂种')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) $record->bee_species)->columnSpan(1),

                    Text::make('纬度')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => filled($record->location_latitude) ? (string) $record->location_latitude : '—')->columnSpan(1),

                    Text::make('经度')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => filled($record->location_longitude) ? (string) $record->location_longitude : '—')->columnSpan(1),
                ]),

            Section::make('生产情况')
                ->icon('heroicon-o-cog')
                ->collapsible()
                ->columns(4)
                ->schema([
                    Text::make('当前是否生产期')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) $record->is_production_now)
                        ->badge()
                        ->color(fn (Survey $record) => $record->is_production_now === '是' ? 'success' : 'gray')
                        ->columnSpan(1),

                    Text::make('主要产品')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => $record->product_type ?: '—')->columnSpan(1),

                    Text::make('蜂蜜种类')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => $record->product_type === '蜂蜜' ? ($record->honey_type ?: '—') : '—')
                        ->columnSpan(1),

                    Text::make('花粉种类')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => $record->product_type === '花粉' ? ($record->pollen_type ?: '—') : '—')
                        ->columnSpan(1),

                    Text::make('下个生产期开始时间')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) $record->next_month)->columnSpan(3),
                ]),

            Section::make('转地 / 蜜粉源')
                ->icon('heroicon-o-map')
                ->collapsible()
                ->columns(4)
                ->schema([
                    Text::make('是否转地')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) ($record->need_move ?: '—'))->columnSpan(1),

                    Text::make('目的地')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(function (Survey $record) {
                        if ($record->need_move !== '是') {
                            return '—';
                        }
                        return trim(($record->move_province ?? '') . ' ' . ($record->move_city ?? '') . ' ' . ($record->move_district ?? '')) ?: '—';
                    })->columnSpan(3),

                    Text::make('主要蜜粉源')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => $record->need_move === '否' ? ($record->next_floral ?: '—') : '—')
                        ->columnSpan(3),
                ]),

            Section::make('收入来源排序（1-4）')
                ->icon('heroicon-o-bars-3-bottom-left')
                ->collapsible()
                ->columns(4)
                ->schema([
                    Text::make('蜂蜜')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) data_get($record->income_ranks, 'honey', '—'))->columnSpan(1),

                    Text::make('蜂王浆')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) data_get($record->income_ranks, 'royalJelly', '—'))->columnSpan(1),

                    Text::make('授粉')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) data_get($record->income_ranks, 'pollination', '—'))->columnSpan(1),

                    Text::make('卖蜂')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) data_get($record->income_ranks, 'sellBee', '—'))->columnSpan(1),
                ]),

            Section::make('异常情况')
                ->icon('heroicon-o-exclamation-triangle')
                ->collapsible()
                ->columns(4)
                ->schema([
                    Text::make('近一月是否异常')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => (string) ($record->has_abnormal ?: '—'))
                        ->badge()
                        ->color(fn (Survey $record) => $record->has_abnormal === '是' ? 'danger' : 'gray')
                        ->columnSpan(1),

                    Text::make('发病虫龄')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => is_array($record->sick_ages) && count($record->sick_ages) ? implode('、', $record->sick_ages) : '—')
                        ->columnSpan(3),

                    Text::make('发病蜂群数')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => $record->sick_count ?: '—')->columnSpan(1),

                    Text::make('主要症状')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(function (Survey $record) {
                        $syms = is_array($record->symptoms) ? $record->symptoms : [];
                        if (! $syms) {
                            return '—';
                        }
                        $text = implode('、', $syms);
                        if (in_array('其他', $syms) && $record->symptom_other) {
                            $text .= '（其他：' . $record->symptom_other . '）';
                        }
                        return $text;
                    })->columnSpan(3),

                    Text::make('近一月用药')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => is_array($record->medications) && count($record->medications) ? implode('、', $record->medications) : '—')->columnSpan(3),

                    Text::make('发生规律')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => $record->occur_rule ?: '—')->columnSpan(3),

                    Text::make('可能原因')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => $record->possible_reason ?: '—')->columnSpan(3),
                ]),

            Section::make('往年集中发病月份')
                ->icon('heroicon-o-calendar')
                ->collapsible()
                ->columns(4)
                ->schema([
                    Text::make('月份')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (Survey $record) => is_array($record->past_months) && count($record->past_months) ? implode('、', $record->past_months) : '—')
                        ->columnSpan(3),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\SurveyResource\Pages\ListSurveys::route('/'),
            'view' => \App\Filament\Admin\Resources\SurveyResource\Pages\ViewSurvey::route('/{record}'),
        ];
    }
}
