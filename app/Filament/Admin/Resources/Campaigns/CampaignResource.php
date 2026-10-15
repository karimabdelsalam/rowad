<?php

namespace App\Filament\Admin\Resources\Campaigns;

use App\Filament\Admin\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\Admin\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Admin\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Admin\Resources\Campaigns\Pages\ViewCampaign;
use App\Filament\Admin\Resources\Campaigns\Schemas\CampaignForm;
use App\Filament\Admin\Resources\Campaigns\Schemas\CampaignInfolist;
use App\Filament\Admin\Resources\Campaigns\Tables\CampaignsTable;
use App\Models\Campaign;
use BackedEnum;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'التواصل';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'حملة';

    protected static ?string $pluralModelLabel = 'الرسائل الجماعية';

    protected static ?string $permission = 'campaigns';

    public static function canAccess(): bool
    {
        return \App\Support\PlanLimits::allows('campaigns') && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return CampaignForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CampaignInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CampaignsTable::configure($table);
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
            'index' => ListCampaigns::route('/'),
            'create' => CreateCampaign::route('/create'),
            'view' => ViewCampaign::route('/{record}'),
            'edit' => EditCampaign::route('/{record}/edit'),
        ];
    }
}
