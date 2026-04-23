<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nuestore_transactions', function (Blueprint $table) {
            // Tambah kolom quantity untuk menyimpan jumlah order
            $table->unsignedInteger('quantity')->nullable()->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('nuestore_transactions', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
