<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('parent_payment_id')->nullable()->after('reservation_id')->constrained('payments')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
            $table->foreignId('processed_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable()->after('processed_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_payment_id');
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn(['refunded_at', 'internal_notes']);
        });
    }
};
