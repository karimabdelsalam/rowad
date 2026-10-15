<?php

namespace App\Filament\Platform\Resources\Tenants\Pages;

use App\Filament\Platform\Resources\Tenants\TenantResource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Deleting an office drops its whole database: ask for its name, like GitHub does for repositories.
            DeleteAction::make()
                ->modalHeading(__('حذف المؤسسة نهائيًا'))
                ->modalDescription(fn () => __('ستُحذف قاعدة بيانات :name بالكامل (الوحدات والعقود والدفعات والملفات) ولا يمكن استرجاعها. للإيقاف المؤقت استخدم "إيقاف" بدل الحذف.', ['name' => $this->record->name]))
                ->schema([
                    TextInput::make('confirm_name')->label(__('اكتب اسم المؤسسة للتأكيد'))->required()->in(fn () => [$this->record->name])
                        ->validationMessages(['in' => __('الاسم غير مطابق.')]),
                ]),
        ];
    }
}
