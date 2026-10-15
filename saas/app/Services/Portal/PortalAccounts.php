<?php

namespace App\Services\Portal;

use App\Enums\UserType;
use App\Exceptions\BusinessRuleException;
use App\Models\Client;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Owner/client portal logins (legacy owner/buyer "reset" and username/password fields). */
class PortalAccounts
{
    /** Create the login or reset its credentials; returns the plain password once. */
    public function issue(Owner|Client $person, string $login, ?string $password = null): array
    {
        $password ??= Str::password(10, symbols: false);
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;
        $field = $isEmail ? 'email' : 'username';

        $taken = User::where($field, $login)->when($person->user_id, fn ($q, $id) => $q->whereKeyNot($id))->exists();
        if ($taken) {
            throw new BusinessRuleException(__('اسم المستخدم مستخدم من قبل'));
        }

        $user = DB::transaction(function () use ($person, $field, $login, $password) {
            $user = $person->user ?? new User;
            $user->fill([
                'type' => $person instanceof Owner ? UserType::Owner : UserType::Client,
                'name' => $person instanceof Client ? $person->displayName() : $person->full_name,
                $field => $login,
                'mobile' => $person->mobile,
                'password' => $password,
                'is_active' => true,
            ])->save();
            $person->user()->associate($user)->save();

            return $user;
        });

        return ['user' => $user, 'login' => $login, 'password' => $password];
    }

    public function disable(Owner|Client $person): void
    {
        $person->user?->update(['is_active' => false]);
    }
}
