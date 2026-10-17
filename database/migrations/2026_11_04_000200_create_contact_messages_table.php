<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Messages sent from the "تواصل معنا" form on the official site, answered from the platform console. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('company', 160)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('topic', 30)->default('general');   // demo, pricing, migration, support, partnership, general
            $table->text('message');
            $table->string('locale', 5)->default('ar');
            $table->string('ip', 45)->nullable();
            $table->string('status', 20)->default('new')->index(); // new, handled
            $table->timestamp('handled_at')->nullable();
            $table->string('handled_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
