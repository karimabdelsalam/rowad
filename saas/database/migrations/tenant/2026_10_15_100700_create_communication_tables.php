<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel database notifications (legacy notifications table, bell icon in navbar).
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Legacy email_list: imported newsletter recipients.
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('mobile', 30)->nullable()->index();
            $table->timestamps();
        });

        // Legacy newsletter: a bulk email/SMS campaign to a user group or the list.
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 10); // mail, sms
            $table->string('audience', 30); // owners, clients, renters, buyers, employees, subscribers, custom
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->string('status', 20)->default('draft'); // draft, queued, sending, sent
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Legacy email_queue: delivery log for every outgoing email/SMS (campaigns and notifications).
        Schema::create('outgoing_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 10); // mail, sms
            $table->string('event', 50)->nullable(); // notification event key
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->string('status', 20)->default('pending')->index(); // pending, sent, failed
            $table->text('error')->nullable();
            $table->string('provider', 30)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['outgoing_messages', 'campaigns', 'newsletter_subscribers', 'notifications'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
