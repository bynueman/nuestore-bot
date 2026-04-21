<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nuestore_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('nuestore_users')->onDelete('cascade');
            $table->string('provider_order_id')->nullable();
            $table->string('target_link');
            $table->integer('service_id');
            $table->string('duitku_ref')->nullable();
            $table->decimal('amount_paid', 15, 2);
            $table->decimal('modal_cost', 15, 2)->default(0);
            $table->decimal('pg_fee_estimated', 15, 2)->default(0);
            $table->decimal('profit_estimated', 15, 2)->default(0);
            $table->decimal('profit_actual', 15, 2)->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_retried_at')->nullable();
            $table->text('retry_error_log')->nullable();
            $table->enum('status', [
                'UNPAID',
                'PAID_QUEUED',
                'PROCESSING',
                'COMPLETED',
                'FAILED_PG',
                'FAILED_PROVIDER',
                'REFUND_REQUESTED',
                'DISPUTED',
                'CANCELED'
            ])->default('UNPAID');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nuestore_transactions');
    }
};