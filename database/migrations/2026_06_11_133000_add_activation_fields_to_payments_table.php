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
            $table->string('activation_status')->nullable()->after('status')->index();
            $table->text('activation_error')->nullable()->after('activation_status');
            $table->timestamp('activated_at')->nullable()->after('activation_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn([
                'activation_status',
                'activation_error',
                'activated_at',
            ]);
        });
    }
};
