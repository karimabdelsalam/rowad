<?php

namespace App\Filament\Admin\Resources\Branches\Pages;

use App\Filament\Admin\Resources\Branches\BranchResource;
use App\Support\Branches;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBranches extends ManageRecords
{
    protected static string $resource = BranchResource::class;

    public function getSubheading(): ?string
    {
        return __('اربط الموظف بفرع ليرى وحدات ذلك الفرع وعقوده وسنداته فقط. الموظف بلا فرع يرى كل الفروع.');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->after(fn () => Branches::flush())];
    }
}
