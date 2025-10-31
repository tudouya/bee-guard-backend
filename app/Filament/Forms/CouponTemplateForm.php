<?php

namespace App\Filament\Forms;

use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class CouponTemplateForm
{
    /**
     * @param  array{
     *     description?: string|null,
     *     columns?: array{default?: int, md?: int},
     *     platformOptions?: array<string, string> | Closure,
     *     storeUrlHelperText?: string|null,
     *     enterpriseField?: array{
     *         options?: array<int, string> | array<string, string> | Closure,
     *         required?: bool,
     *         placeholder?: string|null,
     *         helperText?: string|null,
     *         default?: mixed,
     *         disabled?: bool|Closure,
     *         native?: bool,
     *         searchable?: bool,
     *         preload?: bool,
     *     }
     * }  $config
     * @return array<int, Section>
     */
    public static function make(array $config = []): array
    {
        $enterpriseConfig = $config['enterpriseField'] ?? [];

        $enterpriseSelect = Select::make('enterprise_id')
            ->label('所属企业');

        $options = $enterpriseConfig['options'] ?? null;
        if ($options instanceof Closure) {
            $enterpriseSelect->options($options);
        } elseif (is_array($options)) {
            $enterpriseSelect->options($options);
        }

        $enterpriseSelect->native($enterpriseConfig['native'] ?? false);

        if ($enterpriseConfig['searchable'] ?? true) {
            $enterpriseSelect->searchable();
        }

        if ($enterpriseConfig['preload'] ?? false) {
            $enterpriseSelect->preload();
        }

        if (($enterpriseConfig['required'] ?? true) === true) {
            $enterpriseSelect->required();
        } else {
            $enterpriseSelect->nullable();
        }

        if (array_key_exists('placeholder', $enterpriseConfig)) {
            $enterpriseSelect->placeholder($enterpriseConfig['placeholder']);
        }

        if (! empty($enterpriseConfig['helperText'])) {
            $enterpriseSelect->helperText($enterpriseConfig['helperText']);
        }

        if (array_key_exists('default', $enterpriseConfig)) {
            $enterpriseSelect->default($enterpriseConfig['default']);
        }

        if (array_key_exists('disabled', $enterpriseConfig)) {
            $enterpriseSelect->disabled($enterpriseConfig['disabled']);
        }

        $platformOptions = $config['platformOptions'] ?? [
            'jd' => '京东',
            'taobao' => '淘宝',
            'pinduoduo' => '拼多多',
            'offline' => '线下门店',
            'other' => '其他平台',
        ];

        $storeUrlHelperText = $config['storeUrlHelperText'] ?? '请填写可访问的店铺或优惠券链接，便于蜂农快速跳转。';

        $schema = [
            $enterpriseSelect,
            TextInput::make('title')
                ->label('券名称')
                ->required()
                ->maxLength(191),
            Select::make('platform')
                ->label('发券平台')
                ->options($platformOptions)
                ->required()
                ->native(false),
            TextInput::make('store_name')
                ->label('店铺名称')
                ->required()
                ->maxLength(191),
            TextInput::make('store_url')
                ->label('店铺链接')
                ->url()
                ->required()
                ->maxLength(255)
                ->helperText($storeUrlHelperText),
            TextInput::make('face_value')
                ->label('面值（元）')
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(0.01),
            TextInput::make('total_quantity')
                ->label('发放总量（留空表示不限）')
                ->numeric()
                ->nullable()
                ->minValue(1),
            DatePicker::make('valid_from')
                ->label('有效期开始')
                ->required()
                ->native(false)
                ->displayFormat('Y-m-d')
                ->format('Y-m-d'),
            DatePicker::make('valid_until')
                ->label('有效期结束')
                ->required()
                ->native(false)
                ->displayFormat('Y-m-d')
                ->format('Y-m-d')
                ->afterOrEqual('valid_from'),
            Textarea::make('usage_instructions')
                ->label('使用说明')
                ->rows(5)
                ->required()
                ->columnSpanFull(),
        ];

        $section = Section::make('券基础信息')
            ->description($config['description'] ?? null)
            ->schema($schema)
            ->columns($config['columns'] ?? [
                'default' => 1,
                'md' => 2,
            ]);

        if (isset($config['sectionColumnSpan'])) {
            $section->columnSpan($config['sectionColumnSpan']);
            $section->extraAttributes(['class' => 'mx-auto']);
        } else {
            $section->columnSpanFull();
        }

        return [$section];
    }
}
