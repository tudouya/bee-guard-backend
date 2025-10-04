<?php

namespace App\Filament\Admin\Resources;

use App\Models\ShippingNotification;
use App\Support\AdminNavigation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Models\Survey;
use App\Models\Order;

class ShippingNotificationResource extends Resource
{
    protected static ?string $model = ShippingNotification::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = '邮寄通知';
    protected static \UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_DETECTION_OPERATIONS;
    protected static ?int $navigationSort = AdminNavigation::ORDER_SHIPPING;

    public static function form(Schema $schema): Schema
    {
        // Read-only resource: add/edit not provided
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('created_at')->label('提交时间')->dateTime()->sortable(),
                TextColumn::make('shipped_at')->label('寄出日期')->date()->sortable(),

                TextColumn::make('courier_company')->label('快递公司')->sortable()->searchable(),
                TextColumn::make('tracking_no')->label('快递单号')->sortable()->searchable(),
                TextColumn::make('contact_phone')->label('联系电话')->searchable()->toggleable(),

                TextColumn::make('detectionCodeFull')
                    ->label('检测号')
                    ->getStateUsing(fn ($record) => optional($record->detectionCode)->prefix . optional($record->detectionCode)->code)
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('detectionCode', function ($q) use ($search) {
                            $q->whereRaw("CONCAT(prefix, code) LIKE ?", ['%' . $search . '%']);
                        });
                    })
                    ->toggleable(),
                BadgeColumn::make('detectionCode.source_type')->label('来源')
                    ->colors(['primary' => 'self_paid', 'warning' => 'gift'])
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'self_paid' => '自费',
                        'gift' => '企业赠送',
                        default => $state,
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('detectionCode.enterprise.name')->label('所属企业')->toggleable(),

                TextColumn::make('user.display_name')->label('提交用户')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('courier_company')->label('快递公司')
                    ->options(collect(config('shipping.courier_companies', []))
                        ->mapWithKeys(fn ($v) => [$v => $v])->all()),
                SelectFilter::make('source_type')->label('来源')->options([
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
            ->actions([])
            ->bulkActions([])
            ->modifyQueryUsing(fn ($query) => $query->with(['detectionCode.enterprise', 'user']));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基本信息')
                ->icon('heroicon-o-truck')
                ->collapsible()
                ->columns(4)
                ->schema([
                    Text::make('创建时间')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (ShippingNotification $record) => optional($record->created_at)?->format('Y-m-d H:i') ?: '—')->columnSpan(1),

                    Text::make('寄出日期')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (ShippingNotification $record) => optional($record->shipped_at)?->format('Y-m-d') ?: '—')->columnSpan(1),

                    Text::make('快递公司')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (ShippingNotification $record) => (string) $record->courier_company ?: '—')->columnSpan(1),

                    Text::make('快递单号')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (ShippingNotification $record) => (string) $record->tracking_no ?: '—')->columnSpan(3),

                    Text::make('联系电话')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (ShippingNotification $record) => (string) $record->contact_phone ?: '—')->columnSpan(1),
                ]),

            Section::make('检测信息')
                ->icon('heroicon-o-clipboard-document-check')
                ->collapsible()
                ->columns(4)
                ->schema([
                    Text::make('检测号')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (ShippingNotification $record) => optional($record->detectionCode)?->prefix . optional($record->detectionCode)?->code ?: '—')->columnSpan(1),

                    Text::make('来源')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(function (ShippingNotification $record) {
                        return match ($record->detectionCode?->source_type) {
                            'self_paid' => '自费',
                            'gift' => '企业赠送',
                            default => '—',
                        };
                    })
                        ->badge()
                        ->color(fn (ShippingNotification $record) => $record->detectionCode?->source_type === 'self_paid' ? 'primary' : 'warning')
                        ->columnSpan(1),

                    Text::make('企业')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (ShippingNotification $record) => optional($record->detectionCode?->enterprise)?->name ?: '—')->columnSpan(3),
                ]),

            Section::make('用户')
                ->icon('heroicon-o-user')
                ->columns(4)
                ->schema([
                    Text::make('用户')->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(fn (ShippingNotification $record) => optional($record->user)?->display_name ?: ('#' . $record->user_id))->columnSpan(3),
                ]),

            Section::make('关联记录')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->columns(4)
                ->schema([
                    Text::make('问卷')
                        ->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(function (ShippingNotification $record) {
                        $survey = Survey::query()->where('detection_code_id', $record->detection_code_id)->latest('id')->first();
                        return $survey ? ('问卷 #' . $survey->id) : '—';
                    })->columnSpan(3),

                    Text::make('订单')
                        ->color('gray')->weight('medium')->columnSpan(1),
                    Text::make(function (ShippingNotification $record) {
                        $order = Order::query()->where('detection_code_id', $record->detection_code_id)->latest('id')->first();
                        return $order ? ('订单 #' . $order->id) : '—';
                    })->columnSpan(3),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\ShippingNotificationResource\Pages\ListShippingNotifications::route('/'),
            'view' => \App\Filament\Admin\Resources\ShippingNotificationResource\Pages\ViewShippingNotification::route('/{record}'),
        ];
    }
}
