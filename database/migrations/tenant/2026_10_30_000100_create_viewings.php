<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Viewing appointments booked from the office's website (or by staff) for a listed unit. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viewings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at')->index();            // the office's local wall-clock time
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('status', 12)->default('booked');   // booked, done, no_show, cancelled
            $table->string('name');
            $table->string('mobile', 30);
            $table->string('email')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->string('source', 10)->default('website');  // website, staff
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete(); // once the visitor becomes a client
            $table->timestamp('reminded_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['unit_id', 'starts_at']); // one live booking per unit and slot is enforced when booking
        });

        (new \Database\Seeders\Tenant\RolesAndPermissionsSeeder)->run();
        (new \Database\Seeders\Tenant\TemplateSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('viewings');
    }
};
