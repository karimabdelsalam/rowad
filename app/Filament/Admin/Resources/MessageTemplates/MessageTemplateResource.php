<?php

namespace App\Filament\Admin\Resources\MessageTemplates;

use App\Filament\Admin\Resources\MessageTemplates\Pages\EditMessageTemplate;
use App\Filament\Admin\Resources\MessageTemplates\Pages\ListMessageTemplates;
use App\Filament\Admin\Resources\MessageTemplates\Pages\ViewMessageTemplate;
use App\Filament\Admin\Resources\MessageTemplates\Schemas\MessageTemplateForm;
use App\Filament\Admin\Resources\MessageTemplates\Schemas\MessageTemplateInfolist;
use App\Filament\Admin\Resources\MessageTemplates\Tables\MessageTemplatesTable;
use App\Models\MessageTemplate;
use BackedEnum;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MessageTemplateResource extends Resource
{
    protected static ?string $model = MessageTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'التواصل';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'قالب رسالة';

    protected static ?string $pluralModelLabel = 'قوالب الإشعارات';

    protected static ?string $permission = 'message_templates';

    public static function form(Schema $schema): Schema
    {
        return MessageTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MessageTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MessageTemplatesTable::configure($table);
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
            'index' => ListMessageTemplates::route('/'),
            'view' => ViewMessageTemplate::route('/{record}'),
            'edit' => EditMessageTemplate::route('/{record}/edit'),
        ];
    }
}
