<?php

namespace App\Filament\Admin\Resources\CustomFieldGroups;

use App\Filament\Admin\Resources\CustomFieldGroups\Pages\ManageCustomFieldGroups;
use App\Models\CustomFieldGroup;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomFieldGroupResource extends Resource
{
    protected static ?string $model = CustomFieldGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'مجموعة حقول';

    protected static ?string $pluralModelLabel = 'الحقول الإضافية';

    protected static ?string $permission = 'custom_fields';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('property_category_id')
                    ->relationship('propertyCategory', 'title'),
                TextInput::make('title')
                    ->required(),
                \Filament\Forms\Components\Repeater::make('fields')
                    ->label(__('الحقول'))
                    ->relationship()
                    ->orderColumn('sort_order')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextInput::make('title')->required(),
                        Select::make('type')->options(\App\Enums\CustomFieldType::class)->default('text')->required()->live(),
                        TextInput::make('suffix'),
                        TextInput::make('description')->columnSpan(2),
                        \Filament\Forms\Components\Toggle::make('is_required'),
                        \Filament\Forms\Components\TagsInput::make('options')
                            ->label(__('الخيارات'))
                            ->columnSpanFull()
                            ->visible(fn ($get) => in_array($get('type') instanceof \BackedEnum ? $get('type')->value : $get('type'), ['select'], true)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('propertyCategory.title')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCustomFieldGroups::route('/'),
        ];
    }
}
