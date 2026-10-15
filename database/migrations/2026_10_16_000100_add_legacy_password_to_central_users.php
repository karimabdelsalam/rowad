<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Platform administrators share the User model (and its login provider) with office users, so they need the same columns. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'legacy_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('legacy_password', 64)->nullable()->after('password');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('legacy_password');
        });
    }
};
