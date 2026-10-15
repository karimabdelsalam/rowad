<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Legacy home/upload ("سحابة الملفات"): the office's shared files, stored in the media library. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_files', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // offices created before this module: give the new permissions to the admin role only
        if (Schema::hasTable('permissions') && Role::where('name', 'admin')->exists()) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $names = array_map(fn ($a) => "office_files.{$a}", config('rowad.permissions.office_files'));
            foreach ($names as $name) {
                Permission::findOrCreate($name, 'web');
            }
            Role::findByName('admin', 'web')->givePermissionTo($names);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('office_files');
    }
};
