<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('merged_into_user_id')
                ->nullable()
                ->after('role')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('merged_at')->nullable()->after('merged_into_user_id');

            $table->index(['merged_into_user_id', 'merged_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['merged_into_user_id', 'merged_at']);
            $table->dropConstrainedForeignId('merged_into_user_id');
            $table->dropColumn('merged_at');
        });
    }
};
