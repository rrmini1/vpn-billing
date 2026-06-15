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
        Schema::table('payments', function (Blueprint $table): void {
            $table->text('confirmation_url')->nullable()->after('provider_payment_id');
            $table->timestamp('expires_at')->nullable()->after('confirmation_url');
            $table->json('provider_payload')->nullable()->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn([
                'confirmation_url',
                'expires_at',
                'provider_payload',
            ]);
        });
    }
};
