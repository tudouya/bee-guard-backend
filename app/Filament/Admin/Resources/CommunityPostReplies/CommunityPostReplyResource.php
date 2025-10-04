<?php

namespace App\Filament\Admin\Resources\CommunityPostReplies;

use App\Filament\Admin\Resources\CommunityPostReplies\Pages\ListCommunityPostReplies;
use App\Filament\Admin\Resources\CommunityPostReplies\Pages\ViewCommunityPostReply;
use App\Filament\Admin\Resources\CommunityPostReplies\Schemas\CommunityPostReplyForm;
use App\Filament\Admin\Resources\CommunityPostReplies\Schemas\CommunityPostReplyInfolist;
use App\Filament\Admin\Resources\CommunityPostReplies\Tables\CommunityPostRepliesTable;
use App\Models\CommunityPostReply;
use App\Support\AdminNavigation;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CommunityPostReplyResource extends Resource
{
    protected static ?string $model = CommunityPostReply::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_COMMUNITY;
    protected static ?string $navigationLabel = '帖子回复';
    protected static ?int $navigationSort = AdminNavigation::ORDER_COMMUNITY_REPLIES;
    protected static ?int $pendingCountCache = null;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return CommunityPostReplyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CommunityPostReplyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunityPostRepliesTable::configure($table);
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
            'index' => ListCommunityPostReplies::route('/'),
            'view' => ViewCommunityPostReply::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    protected static function getPendingCount(): int
    {
        return static::$pendingCountCache ??=
            CommunityPostReply::query()
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
        return '待审核回复数量';
    }
}
