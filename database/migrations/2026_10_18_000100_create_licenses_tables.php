<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Licenses for copies of Aqar Nova installed on customers' own servers, and the installations using them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('max_offices')->nullable()->default(1); // null = unlimited
            $table->unsignedInteger('max_units')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->json('features')->nullable(); // null = the plan's features
            $table->unsignedSmallInteger('max_activations')->default(1);
            $table->json('allowed_domains')->nullable(); // null = any domain
            $table->string('status', 12)->default('active')->index(); // active, suspended, revoked
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // null = does not expire
            $table->timestamp('support_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('instance_id', 64);
            $table->string('domain');
            $table->string('ip', 45)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('php_version', 20)->nullable();
            $table->string('status', 12)->default('active'); // active, deactivated
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->unique(['license_id', 'instance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_activations');
        Schema::dropIfExists('licenses');
    }
};
