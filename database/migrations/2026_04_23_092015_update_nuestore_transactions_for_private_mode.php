<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nuestore_transactions', function (Blueprint $table) {
            // Hapus FK constraint dulu sebelum ubah kolom
            $table->dropForeign(['user_id']);

            // Buat user_id nullable (order sekarang tidak perlu user terdaftar)
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // Hapus kolom yang tidak relevan tanpa PG
            $table->dropColumn([
                'duitku_ref',
                'pg_fee_estimated',
            ]);

            // Tambah kolom catatan pelanggan
            $table->string('customer_note')->nullable()->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('nuestore_transactions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('nuestore_users')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->string('duitku_ref')->nullable()->after('provider_order_id');
            $table->decimal('pg_fee_estimated', 15, 2)->default(0)->after('modal_cost');
            $table->dropColumn('customer_note');
        });
    }
};