<?php

namespace App\Filament\Admin\Resources\CommunityPosts;

use App\Filament\Admin\Resources\CommunityPosts\Pages\ListCommunityPosts;
use App\Filament\Admin\Resources\CommunityPosts\Pages\ViewCommunityPost;
use App\Filament\Admin\Resources\CommunityPosts\Schemas\CommunityPostForm;
use App\Filament\Admin\Resources\CommunityPosts\Schemas\CommunityPostInfolist;
use App\Filament\Admin\Resources\CommunityPosts\Tables\CommunityPostsTable;
use App\Models\CommunityPost;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CommunityPostResource extends Resource
{
    protected static ?string $model = CommunityPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static UnitEnum|string|null $navigationGroup = '社区管理';
    protected static ?string $navigationLabel = '社区帖子';
    protected static ?int $pendingCountCache = null;

    protected static ?string $recordTitleAttribute = 'title';

    protected static function getPendingCount(): int
    {
        return static::$pendingCountCache ??=
            CommunityPost::query()
                ->where('status', 'pending')
                ->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getPendingCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getPendingCount() > 0 ? 'warning' : 'gray';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return '待审核帖子数量';
    }

    public static function form(Schema $schema): Schema
    {
        return CommunityPostForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CommunityPostInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunityPostsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityPosts::route('/'),
            'view' => ViewCommunityPost::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
