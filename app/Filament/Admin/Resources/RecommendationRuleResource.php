<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RecommendationRuleResource\Pages;
use App\Models\RecommendationRule;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use Filament\Schemas\Components\Utilities\Set;

class RecommendationRuleResource extends Resource
{
    protected static ?string $model = RecommendationRule::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Recommendation Rules';

    protected static \UnitEnum|string|null $navigationGroup = 'Business';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Scope & Target')->schema([
                Radio::make('scope_type')
                    ->label('Scope')
                    ->options([
                        'global' => 'Global',
                        'enterprise' => 'Enterprise',
                    ])
                    ->inline()
                    ->default('global')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (string $state, Set $set, callable $get) {
                        // Set sensible tier defaults when switching scope if tier was untouched
                        $current = (int) ($get('tier') ?? 0);
                        if ($current === 0 || $current === 10 || $current === 20) {
                            $set('tier', $state === 'enterprise' ? 10 : 20);
                        }
                    }),

                Select::make('enterprise_id')
                    ->label('Enterprise')
                    ->relationship('enterprise', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (callable $get) => $get('scope_type') === 'enterprise')
                    ->required(fn (callable $get) => $get('scope_type') === 'enterprise'),

                Select::make('applies_to')
                    ->label('Applies To')
                    ->options([
                        'self_paid' => 'Self Paid',
                        'gift' => 'Gift',
                        'any' => 'Any',
                    ])
                    ->default('any')
                    ->required()
                    ->native(false),

                // 让下拉在更宽的列展示，避免换行
                Grid::make([
                    'default' => 1,
                    'md' => 2,
                    'lg' => 2,
                ])->schema([
                    Select::make('disease_id')
                        ->label('Disease')
                        ->relationship('disease', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('product_id')
                        ->label('Product')
                        ->relationship('product', 'name', fn ($query, $get) => $query
                            ->when($get('scope_type') === 'enterprise' && $get('enterprise_id'),
                                fn ($q) => $q->where('enterprise_id', $get('enterprise_id'))
                            )
                        )
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('priority')
                        ->label('Priority')
                        ->numeric()
                        ->default(0)
                        ->required(),
                ]),
            ])->columns(1),

            Section::make('Ranking & Promotion')->schema([
                Toggle::make('sponsored')
                    ->label('Sponsored (Promoted)')
                    ->default(false)
                    ->helperText('Mark as paid promotion. You can also adjust tier to control cross-scope ordering.'),
                TextInput::make('tier')
                    ->label('Tier (smaller sorts first)')
                    ->numeric()
                    ->default(fn (callable $get) => $get('scope_type') === 'enterprise' ? 10 : 20)
                    ->required()
                    ->minValue(0)
                    ->helperText('Default: 10 for enterprise, 20 for global. Set smaller to rank higher.'),
            ])->columns(2),

            Section::make('Activation')
                ->schema([
                    Toggle::make('active')
                        ->label('Active')
                        ->default(true),
                    Grid::make(2)->schema([
                        DateTimePicker::make('starts_at')->label('Starts At')->seconds(false),
                        DateTimePicker::make('ends_at')->label('Ends At')->seconds(false),
                    ]),
                ]),
            // 使用说明：为避免组件兼容性问题，这里用简要占位说明，详细说明见列表页「规则说明」按钮
            Section::make('使用说明（简要）')
                ->description('企业优先（默认：Enterprise Tier=10，全局 Tier=20）；赞助可将 Tier 调小以“置顶”。详细说明见列表页右上角「规则说明」。')
                ->schema([]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->toggleable(),
                TextColumn::make('scope_type')->badge()->sortable(),
                TextColumn::make('applies_to')->badge()->sortable(),
                TextColumn::make('enterprise.name')->label('Enterprise')->toggleable(),
                TextColumn::make('disease.name')->label('Disease')->searchable()->sortable(),
                TextColumn::make('product.name')->label('Product')->searchable()->sortable(),
                TextColumn::make('priority')->sortable(),
                TextColumn::make('tier')->sortable()->label('Tier'),
                TextColumn::make('sponsored')->badge()->formatStateUsing(fn ($s) => $s ? 'sponsored' : 'normal')->color(fn ($s) => $s ? 'warning' : 'gray'),
                TextColumn::make('active')->badge()->formatStateUsing(fn ($state) => $state ? 'active' : 'inactive'),
                TextColumn::make('starts_at')->dateTime()->label('Starts')->sortable(),
                TextColumn::make('ends_at')->dateTime()->label('Ends')->sortable(),
            ])
            ->filters([
                SelectFilter::make('scope_type')->options([
                    'global' => 'Global',
                    'enterprise' => 'Enterprise',
                ]),
                SelectFilter::make('applies_to')->options([
                    'self_paid' => 'Self Paid',
                    'gift' => 'Gift',
                    'any' => 'Any',
                ]),
                SelectFilter::make('enterprise_id')->label('Enterprise')->relationship('enterprise', 'name'),
                SelectFilter::make('disease_id')->label('Disease')->relationship('disease', 'name'),
                SelectFilter::make('product_id')->label('Product')->relationship('product', 'name'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecommendationRules::route('/'),
            'create' => Pages\CreateRecommendationRule::route('/create'),
            'edit' => Pages\EditRecommendationRule::route('/{record}/edit'),
        ];
    }
}
