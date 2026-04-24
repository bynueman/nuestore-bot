<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nuestore_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Relasi ke pelanggan
            $table->foreignId('customer_id')->constrained('nuestore_customers')->onDelete('cascade');

            // Info layanan
            $table->string('platform');       // e.g. Instagram, TikTok
            $table->string('category');       // e.g. Followers ID, Likes WW
            $table->integer('service_id');
            $table->string('service_name');
            $table->string('target_link');
            $table->integer('quantity');

            // Info pembayaran
            $table->decimal('base_price', 15, 2);  // Harga asli sebelum kode unik
            $table->smallInteger('unique_code');    // Kode unik 1-999
            $table->decimal('total_amount', 15, 2); // base_price + unique_code (yang user bayar)
            $table->decimal('modal_cost', 15, 2)->default(0);
            $table->decimal('profit_estimated', 15, 2)->default(0);

            // Bukti bayar & notifikasi admin
            $table->string('proof_file_id')->nullable();      // Telegram file_id dari screenshot
            $table->bigInteger('admin_message_id')->nullable(); // ID pesan di Admin Bot untuk diedit
            $table->string('rejected_reason')->nullable();

            // Provider
            $table->string('provider_order_id')->nullable();

            // Status & timer
            $table->enum('status', [
                'PENDING_PAYMENT',   // QRIS sudah dikirim, menunggu pembayaran
                'PROOF_SUBMITTED',   // Screenshot dikirim, menunggu admin
                'APPROVED',          // Admin approve, disubmit ke Lollipop
                'REJECTED',          // Admin tolak
                'PROCESSING',        // Berjalan di Lollipop
                'COMPLETED',         // Selesai
                'EXPIRED',           // 15 menit habis
                'CANCELLED',         // User cancel sendiri
            ])->default('PENDING_PAYMENT');

            $table->timestamp('expires_at');  // created_at + 15 menit
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nuestore_orders');
    }
};
