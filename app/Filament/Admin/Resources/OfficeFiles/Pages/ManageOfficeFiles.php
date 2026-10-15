<?php

namespace App\Filament\Admin\Resources\OfficeFiles\Pages;

use App\Filament\Admin\Resources\OfficeFiles\OfficeFileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOfficeFiles extends ManageRecords
{
    protected static string $resource = OfficeFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('رفع ملف'))
                ->mutateDataUsing(fn (array $data) => $data + ['created_by' => auth()->id()]),
        ];
    }
}
