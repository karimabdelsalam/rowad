<?php

namespace App\Filament\Admin\Resources\OfficeFiles;

use App\Filament\Admin\Resources\OfficeFiles\Pages\ManageOfficeFiles;
use App\Filament\Resource;
use App\Models\OfficeFile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

/** Legacy home/upload: one place for the office's shared documents, each with a link staff can copy into messages and pages. */
class OfficeFileResource extends Resource
{
    protected static ?string $model = OfficeFile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'ملف';

    protected static ?string $pluralModelLabel = 'سحابة الملفات';

    protected static ?string $permission = 'office_files';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SpatieMediaLibraryFileUpload::make('file')->label(__('الملف'))->collection('file')
                ->required()->maxSize(51200)->downloadable()->columnSpanFull()
                ->afterStateUpdated(function ($state, $set, $get) {
                    if (blank($get('title')) && $state instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                        $set('title', pathinfo($state->getClientOriginalName(), PATHINFO_FILENAME));
                    }
                })->live(),
            TextInput::make('title')->label(__('اسم الملف'))->required()->maxLength(190)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['media', 'creator']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('اسم الملف'))->searchable()->weight('bold')
                    ->icon(fn (OfficeFile $record) => str_starts_with((string) $record->file()?->mime_type, 'image/') ? Heroicon::OutlinedPhoto : Heroicon::OutlinedDocument)
                    ->description(fn (OfficeFile $record) => $record->file()?->file_name),
                TextColumn::make('type')->label(__('النوع'))->badge()->color('gray')
                    ->state(fn (OfficeFile $record) => strtoupper((string) pathinfo((string) $record->file()?->file_name, PATHINFO_EXTENSION)) ?: null),
                TextColumn::make('size')->label(__('الحجم'))
                    ->state(fn (OfficeFile $record) => $record->file() ? Number::fileSize($record->file()->size, 1) : null),
                TextColumn::make('creator.name')->label(__('رفع بواسطة'))->placeholder('-'),
                TextColumn::make('created_at')->label(__('تاريخ الرفع'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('link')->label(__('الرابط'))->state(fn (OfficeFile $record) => $record->url())
                    ->copyable()->copyMessage(__('تم نسخ الرابط'))->limit(30)->color('primary')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('download')->label(__('تحميل'))->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (OfficeFile $record) => $record->url(), true)
                    ->visible(fn (OfficeFile $record) => $record->file() !== null),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOfficeFiles::route('/'),
        ];
    }
}
