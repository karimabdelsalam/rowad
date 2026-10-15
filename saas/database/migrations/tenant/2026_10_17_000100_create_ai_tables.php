<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Conversations with the AI assistant; each user sees only their own. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('panel', 20); // admin, owner, client
            $table->string('title')->nullable();
            $table->json('context')->nullable(); // e.g. the record or report a question was asked from
            $table->timestamps();
            $table->index(['user_id', 'panel', 'updated_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12); // user, assistant
            $table->longText('content');
            $table->json('tools_used')->nullable(); // names of the data tools the answer relied on
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->string('kind', 20)->default('chat')->index(); // chat, report, insights
            $table->timestamps();
            $table->index(['role', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
