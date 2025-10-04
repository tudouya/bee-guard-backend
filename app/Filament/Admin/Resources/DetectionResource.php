<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DetectionResource\Pages;
use App\Models\Detection;
use App\Models\DetectionCode;
use App\Models\User;
use App\Support\AdminNavigation;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class DetectionResource extends Resource
{
    protected static ?string $model = Detection::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = '检测记录';

    protected static \UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_DETECTION_OPERATIONS;

    protected static ?int $navigationSort = AdminNavigation::ORDER_DETECTIONS;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('绑定信息')
                ->schema([
                    Select::make('detection_code_id')
                        ->label('检测码')
                        ->relationship('detectionCode', 'code', fn ($query) => $query->whereIn('status', ['assigned', 'used']))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($set, $state) {
                            // 根据检测码带出用户
                            if (!$state) return;
                            $code = DetectionCode::query()->find($state);
                            if ($code) {
                                $set('user_id', $code->assigned_user_id);
                            }
                        })
                        ->helperText('请输入或选择检测码（仅显示已分配/已使用），将自动带出用户'),

                    Select::make('user_id')
                        ->label('用户')
                        ->relationship('user', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => (string) ($record->display_name ?? $record->name ?? $record->email))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('由检测码自动带出（如需修改，请联系管理员）'),
                ])->columns(2),
            Section::make('样品信息')
                ->schema([
                    TextInput::make('sample_no')
                        ->label('样品编号')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(64),
                    TextInput::make('contact_name')
                        ->label('姓名')
                        ->maxLength(191),
                    Select::make('sample_type')
                        ->label('样品类型')
                        ->options([
                            'adult_bee' => '成蜂',
                            'capped_brood' => '封盖子脾',
                            'uncapped_brood' => '未封盖子脾',
                            'other' => '其他',
                        ])
                        ->native(false)
                        ->nullable(),
                    TextInput::make('address_text')
                        ->label('地址')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('时间线')
                ->schema([
                    DatePicker::make('sampled_at')->label('取样时间')->native(false),
                    DatePicker::make('tested_at')->label('检测完成时间')->native(false),
                    DatePicker::make('reported_at')->label('报告时间')->native(false),
                    DatePicker::make('submitted_at')->label('提交时间')->native(false),
                ])->columns(2),

            Section::make('报告与状态')
                ->schema([
                    TextInput::make('tested_by')->label('检测人员/机构')->maxLength(64),
                    TextInput::make('report_no')->label('报告编号')->maxLength(64),
                    Select::make('status')
                        ->label('状态')
                        ->options([
                            'pending' => '待处理',
                            'received' => '已接收',
                            'processing' => '检测中',
                            'completed' => '已完成',
                        ])->native(false)->required(),
                    Textarea::make('lab_notes')->label('备注')->rows(4)->columnSpanFull(),
                ])->columns(2),

            Section::make('结果（RNA病毒）')
                ->schema([
                    Select::make('rna_iapv_level')->label('IAPV')->options([
                        'weak' => '弱', 'medium' => '中', 'strong' => '强',
                    ])->native(false)->nullable(),
                    Select::make('rna_bqcv_level')->label('BQCV')->options([
                        'weak' => '弱', 'medium' => '中', 'strong' => '强',
                    ])->native(false)->nullable(),
                    Select::make('rna_sbv_level')->label('SBV')->options([
                        'weak' => '弱', 'medium' => '中', 'strong' => '强',
                    ])->native(false)->nullable(),
                    Select::make('rna_abpv_level')->label('ABPV')->options([
                        'weak' => '弱', 'medium' => '中', 'strong' => '强',
                    ])->native(false)->nullable(),
                    Select::make('rna_cbpv_level')->label('CBPV')->options([
                        'weak' => '弱', 'medium' => '中', 'strong' => '强',
                    ])->native(false)->nullable(),
                    Select::make('rna_dwv_level')->label('DWV')->options([
                        'weak' => '弱', 'medium' => '中', 'strong' => '强',
                    ])->native(false)->nullable(),
                ])->columns(3),

            Section::make('结果（DNA/细菌/真菌）')
                ->schema([
                    Select::make('dna_afb_level')->label('AFB')->options([
                        'weak' => '弱', 'medium' => '中', 'strong' => '强',
                    ])->native(false)->nullable(),
                    Select::make('dna_efb_level')->label('EFB')->options([
                        'weak' => '弱', 'medium' => '中', 'strong' => '强',
                    ])->native(false)->nullable(),
                    Select::make('dna_ncer_level')->label('N.C (NCER)')->options([
                        'weak' => '弱', 'medium' => '中', 'strong' => '强',
                    ])->native(false)->nullable(),
                    Select::make('dna_napi_level')->label('NAPI')->options([
                        'weak' => '弱', 'medium' => '中', 'strong' => '强',
                    ])->native(false)->nullable(),
                    Select::make('dna_cb_level')->label('CB')->options([
                        'weak' => '弱', 'medium' => '中', 'strong' => '强',
                    ])->native(false)->nullable(),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->toggleable(),
                TextColumn::make('detectionCodeFull')
                    ->label('检测码')
                    ->getStateUsing(fn ($record) => optional($record->detectionCode)->prefix . optional($record->detectionCode)->code)
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('detectionCode', function ($q) use ($search) {
                            $q->whereRaw("CONCAT(prefix, code) LIKE ?", ['%' . $search . '%']);
                        });
                    })
                    ->toggleable(),
                TextColumn::make('user.phone')
                    ->label('用户手机号')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => $state ?: '—')
                    ->toggleable(),
                TextColumn::make('sample_no')->label('样品编号')->searchable()->sortable(),
                TextColumn::make('sample_type')->label('样品类型')->badge()->toggleable(),
                TextColumn::make('sampled_at')->label('取样时间')->dateTime()->sortable(),
                TextColumn::make('tested_at')->label('检测完成')->dateTime()->sortable(),
                TextColumn::make('reported_at')->label('报告时间')->dateTime()->sortable(),
                TextColumn::make('status')->label('状态')->badge()->sortable(),
                TextColumn::make('positive_count')
                    ->label('阳性数')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $fields = [
                            'rna_iapv_level','rna_bqcv_level','rna_sbv_level','rna_abpv_level','rna_cbpv_level','rna_dwv_level',
                            'dna_afb_level','dna_efb_level','dna_ncer_level','dna_napi_level','dna_cb_level',
                        ];
                        $cnt = 0;
                        foreach ($fields as $f) {
                            $val = $record->{$f} ?? null;
                            if ($val === 'weak' || $val === 'medium' || $val === 'strong') {
                                $cnt++;
                            }
                        }
                        return $cnt;
                    }),
                TagsColumn::make('positive_tags')
                    ->label('阳性项')
                    ->getStateUsing(function ($record) {
                        $map = [
                            'IAPV' => 'rna_iapv_level',
                            'BQCV' => 'rna_bqcv_level',
                            'SBV'  => 'rna_sbv_level',
                            'ABPV' => 'rna_abpv_level',
                            'CBPV' => 'rna_cbpv_level',
                            'DWV'  => 'rna_dwv_level',
                            'AFB'  => 'dna_afb_level',
                            'EFB'  => 'dna_efb_level',
                            'NCER' => 'dna_ncer_level',
                            'NAPI' => 'dna_napi_level',
                            'CB'   => 'dna_cb_level',
                        ];
                        $tags = [];
                        foreach ($map as $code => $col) {
                            $val = $record->{$col} ?? null;
                            if ($val === 'weak' || $val === 'medium' || $val === 'strong') {
                                $tags[] = $code;
                            }
                        }
                        return $tags;
                    })
                    ->limit(6),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('missing_sample_no')
                    ->label('待补样品编号')
                    ->query(fn ($query) => $query->whereNull('sample_no')),

                \Filament\Tables\Filters\Filter::make('only_pending')
                    ->label('仅看待处理')
                    ->query(fn ($query) => $query->where('status', 'pending')),

                SelectFilter::make('status')->options([
                    'pending' => '待处理',
                    'received' => '已接收',
                    'processing' => '检测中',
                    'completed' => '已完成',
                ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('view_survey')
                    ->label('查看问卷')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(function ($record) {
                        $surveyId = \App\Models\Survey::query()
                            ->where('detection_code_id', $record->detection_code_id)
                            ->latest('id')
                            ->value('id');
                        return $surveyId ? \App\Filament\Admin\Resources\SurveyResource::getUrl('view', ['record' => $surveyId]) : null;
                    })
                    ->hidden(function ($record) {
                        return ! \App\Models\Survey::query()->where('detection_code_id', $record->detection_code_id)->exists();
                    }),
                \Filament\Actions\Action::make('view_shipping')
                    ->label('查看邮寄')
                    ->icon('heroicon-o-truck')
                    ->url(function ($record) {
                        $snId = \App\Models\ShippingNotification::query()
                            ->where('detection_code_id', $record->detection_code_id)
                            ->latest('id')
                            ->value('id');
                        if ($snId) {
                            return \App\Filament\Admin\Resources\ShippingNotificationResource::getUrl('view', ['record' => $snId]);
                        }
                        $full = (optional($record->detectionCode)->prefix ?? '') . (optional($record->detectionCode)->code ?? '');
                        return \App\Filament\Admin\Resources\ShippingNotificationResource::getUrl('index') . ($full ? ('?tableSearch=' . rawurlencode($full)) : '');
                    }),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\DeleteBulkAction::make(),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make(),
                \Filament\Actions\Action::make('import')
                    ->label('批量导入（稍后提供）')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->disabled()
                    ->tooltip('等模板与字段稳定后提供导入'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['detectionCode', 'user']));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDetections::route('/'),
            'create' => Pages\CreateDetection::route('/create'),
            'edit' => Pages\EditDetection::route('/{record}/edit'),
        ];
    }
}
