<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nuestore_customers', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_id')->unique();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->string('blacklist_reason')->nullable();
            $table->integer('failed_proofs_count')->default(0); // auto-warning after 2
            $table->integer('expired_today_count')->default(0); // auto-warning after 3
            $table->timestamp('last_order_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nuestore_customers');
    }
};
