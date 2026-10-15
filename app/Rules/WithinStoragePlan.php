<?php

namespace App\Rules;

use App\Support\PlanLimits;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** New uploads stop once the organisation has used its plan's storage; files already saved stay editable. */
class WithinStoragePlan implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $files = is_array($value) ? $value : [$value];
        $isNew = collect($files)->contains(fn ($file) => $file instanceof TemporaryUploadedFile);

        if ($isNew && PlanLimits::reached('storage_mb')) {
            $fail(__('امتلأت مساحة التخزين في باقتك (:limit ميجا). احذف ملفات غير مطلوبة أو اطلب ترقية الباقة.', ['limit' => PlanLimits::limit('storage_mb')]));
        }
    }
}
