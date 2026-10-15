<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(config('rowad.permissions'))
            ->flatMap(fn (array $actions, string $resource) => array_map(fn ($a) => "{$resource}.{$a}", $actions))
            ->values();

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (config('rowad.roles') as $roleName => $patterns) {
            $role = Role::findOrCreate($roleName, 'web');
            $granted = $patterns === '*'
                ? $all
                : $all->filter(fn (string $permission) => collect($patterns)->contains(fn ($p) => Str::is($p, $permission)));
            $role->syncPermissions($granted->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
