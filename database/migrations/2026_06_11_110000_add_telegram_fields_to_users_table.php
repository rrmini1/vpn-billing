<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('telegram_id')->nullable()->unique()->after('id');
            $table->string('telegram_username')->nullable()->after('telegram_id');
            $table->string('telegram_first_name')->nullable()->after('telegram_username');
            $table->string('telegram_last_name')->nullable()->after('telegram_first_name');
            $table->text('telegram_photo_url')->nullable()->after('telegram_last_name');
            $table->timestamp('telegram_auth_date')->nullable()->after('telegram_photo_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['telegram_id']);
            $table->dropColumn([
                'telegram_id',
                'telegram_username',
                'telegram_first_name',
                'telegram_last_name',
                'telegram_photo_url',
                'telegram_auth_date',
            ]);
        });
    }
};
