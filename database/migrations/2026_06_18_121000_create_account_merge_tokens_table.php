<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_merge_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['source_user_id', 'target_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_merge_tokens');
    }
};
