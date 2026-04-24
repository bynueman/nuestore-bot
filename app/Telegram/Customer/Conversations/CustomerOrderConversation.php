<?php

namespace App\Telegram\Customer\Conversations;

use App\Models\NuestoreCustomer;
use App\Models\NuestoreOrder;
use App\Models\NuestoreSetting;
use App\Services\LollipopSmmService;
use App\Telegram\Handlers\Admin\NotificationService;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;
use Illuminate\Support\Str;

class CustomerOrderConversation extends Conversation
{
    // State yang tersimpan antar step
    protected ?string $platform     = null;
    protected ?string $region       = null; // 'id' or 'ww'
    protected ?string $category     = null; // 'followers', 'likes', etc
    protected ?string $targetLink   = null;
    protected ?int    $quantity     = null;

    protected ?int    $serviceId    = null;
    protected ?string $serviceName  = null;
    protected ?float  $serviceRate  = null;
    protected ?int    $serviceMin   = null;
    protected ?int    $serviceMax   = null;

    protected ?float  $basePrice    = null;
    protected ?int    $uniqueCode   = null;
    protected ?float  $totalAmount  = null;
    protected ?float  $modalCost    = null;
    protected ?float  $profit       = null;

    protected ?string $orderId      = null; // Set after PENDING_PAYMENT order is created

    // ─────────────────────────────────────────────
    // STEP 1: Anti-spam check + pilih platform
    // ─────────────────────────────────────────────

    public function start(Nutgram $bot): void
    {
        try {
            $user     = $bot->user();
            if (!$user) return;

            $customer = NuestoreCustomer::fromTelegramUser($user);

            // Cek blacklist
            if ($customer->is_blacklisted) {
                $bot->sendMessage("⛔ Maaf, akunmu diblokir dari layanan kami. Hubungi admin jika ada pertanyaan.");
                $this->end();
                return;
            }

            // Masuk ke pilihan platform
            $this->platform = null;
            $this->region   = null;
            $this->category = null;

            // Tampilkan pilihan platform
            $bot->sendMessage(
                text: "🛒 *Pesan Layanan Nuestore*\n\n📱 *Langkah 1: Pilih Platform*",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make('📸 Instagram', callback_data: 'co_platform:Instagram'),
                        InlineKeyboardButton::make('🎵 TikTok',    callback_data: 'co_platform:TikTok'),
                    )
                    ->addRow(
                        InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'),
                    )
            );

            $this->next('selectRegion');

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Order Start Error: ' . $e->getMessage(), [
                'user' => $bot->userId(),
                'trace' => $e->getTraceAsString()
            ]);
            $bot->sendMessage("❌ Terjadi kesalahan saat memulai pesanan. Silakan coba lagi nanti atau hubungi admin.");
            $this->end();
        }
    }

    // ─────────────────────────────────────────────
    // STEP 2: Pilih Region (Indo / WW)
    // ─────────────────────────────────────────────

    public function selectRegion(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';
        if (str_starts_with($cb, 'co_platform:')) {
            $this->platform = substr($cb, 12);
            $bot->answerCallbackQuery();

            $bot->editMessageText(
                text: "📍 *Platform: {$this->platform}*\n\n🌍 *Langkah 2: Pilih Region*",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make('🇮🇩 Indonesia', callback_data: 'co_reg:id'),
                        InlineKeyboardButton::make('🌐 Worldwide', callback_data: 'co_reg:ww'),
                    )
                    ->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'))
            );
            $this->next('selectCategory');
            return;
        }

        $this->start($bot);
    }

    // ─────────────────────────────────────────────
    // STEP 3: Pilih Jenis Layanan (Followers/Likes/dll)
    // ─────────────────────────────────────────────

    public function selectCategory(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';
        if (str_starts_with($cb, 'co_reg:')) {
            $this->region = substr($cb, 7);
            $bot->answerCallbackQuery();

            $regText = $this->region === 'id' ? 'Indonesia 🇮🇩' : 'Worldwide 🌐';

            $bot->editMessageText(
                text: "📍 *Platform: {$this->platform}*\n🌍 *Region: {$regText}*\n\n📦 *Langkah 3: Pilih Jenis Layanan*",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make('👥 Followers', callback_data: 'co_cat:followers'),
                        InlineKeyboardButton::make('❤️ Likes',     callback_data: 'co_cat:likes'),
                    )
                    ->addRow(
                        InlineKeyboardButton::make('👁 Views',     callback_data: 'co_cat:views'),
                        InlineKeyboardButton::make('🔥 Story Views', callback_data: 'co_cat:story'),
                    )
                    ->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'))
            );
            $this->next('askQuantity');
            return;
        }

        $this->start($bot);
    }

    // ─────────────────────────────────────────────
    // STEP 4: Input Jumlah (Qty) TERLEBIH DAHULU
    // ─────────────────────────────────────────────

    public function askQuantity(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';
        if (!str_starts_with($cb, 'co_cat:')) {
            $this->start($bot);
            return;
        }

        $this->category = substr($cb, 7);
        $bot->answerCallbackQuery();

        $bot->editMessageText(
            text: "🔢 *Langkah 4: Masukkan Jumlah*\n\n"
                . "Berapa banyak yang kamu butuhkan? (Contoh: `100` atau `1000`)\n\n"
                . "Kami akan menghitung harga terbaik untuk jumlah tersebut.",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'))
        );

        $this->next('findService');
    }

    // ─────────────────────────────────────────────
    // STEP 5: Tampilkan 5 Rekomendasi + TOTAL HARGA
    // ─────────────────────────────────────────────

    public function findService(Nutgram $bot): void
    {
        $qty = $bot->message()?->text;

        if (!is_numeric($qty) || (int)$qty < 10) {
            $bot->sendMessage("❌ Jumlah tidak valid. Minimal 10 unit:");
            return;
        }

        $this->quantity = (int) $qty;

        $bot->sendMessage("⏳ _Menghitung harga layanan terbaik untuk {$this->quantity} unit..._", parse_mode: 'Markdown');

        $smm      = new LollipopSmmService();
        $services = $smm->getServices();

        if (!$services) {
            $bot->sendMessage("❌ Gagal memuat layanan. Coba lagi nanti.");
            $this->end();
            return;
        }

        $platform = strtolower($this->platform);
        $region   = $this->region;
        $type     = $this->category;

        // Keywords mapping
        $keywords = [
            'followers'   => ['follower', 'pengikut'],
            'likes'       => ['like', 'suka'],
            'views'       => ['view', 'tonton'],
            'story'       => ['story'],
        ];
        $kws = $keywords[$type] ?? [$type];

        $filtered = collect($services)->filter(function ($s) use ($platform, $kws, $region) {
            $name     = strtolower($s['name']);
            $category = strtolower($s['category'] ?? '');

            // Match Platform
            if (!str_contains($name, $platform) && !str_contains($category, $platform)) return false;

            // Match Category Type
            $typeMatch = false;
            foreach ($kws as $kw) {
                if (str_contains($name, $kw) || str_contains($category, $kw)) {
                    $typeMatch = true;
                    break;
                }
            }
            if (!$typeMatch) return false;

            // Match Region
            $isIndo = str_contains($name, 'indonesia') || str_contains($name, 'indo ') || str_contains($category, 'indonesia');
            if ($region === 'id' && !$isIndo) return false;
            if ($region === 'ww' && $isIndo)  return false;

            // Match Min/Max Qty
            if ($this->quantity < $s['min'] || $this->quantity > $s['max']) return false;

            return true;
        })->sortBy('rate')->take(5)->values();

        if ($filtered->isEmpty()) {
            $bot->sendMessage("❌ Maaf, tidak ada layanan yang mendukung jumlah {$this->quantity} saat ini. Coba ganti jumlah atau kategori.");
            $this->end();
            return;
        }

        $markup = InlineKeyboardMarkup::make();
        $markupMultiplier = (float) NuestoreSetting::get('global_markup_multiplier', '2.0');

        $text = "⭐ *Daftar Layanan & Total Harga*\n"
              . "Jumlah: *{$this->quantity}* unit\n"
              . "━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        foreach ($filtered as $index => $s) {
            $totalPrice = ceil(($s['rate'] * $markupMultiplier / 1000) * $this->quantity);
            $priceFmt   = number_format($totalPrice, 0, ',', '.');
            $num        = $index + 1;
            
            $text .= "*{$num}. {$s['name']}*\n";
            $text .= "💰 *Total Bayar: Rp {$priceFmt}*\n";
            $text .= "⚡ Proses Cepat & Aman\n\n";

            $markup->addRow(InlineKeyboardButton::make(
                "Pilih #{$num} (Rp {$priceFmt})", 
                callback_data: "co_sid:{$s['service']}"
            ));
        }

        $markup->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'));

        $bot->sendMessage(
            text: $text,
            parse_mode: 'Markdown',
            reply_markup: $markup
        );

        $this->next('askLink');
    }

    // ─────────────────────────────────────────────
    // STEP 6: Edukasi (IG) & Input Link
    // ─────────────────────────────────────────────

    public function askLink(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';
        if (!str_starts_with($cb, 'co_sid:')) {
            $this->start($bot);
            return;
        }

        $this->serviceId = (int) substr($cb, 7);
        $bot->answerCallbackQuery(); // Jawab secepat mungkin agar loading di HP user hilang

        // Get service details
        $smm = new LollipopSmmService();
        $services = $smm->getServices();
        $best = collect($services)->firstWhere('service', $this->serviceId);

        if (!$best) {
            $bot->sendMessage("❌ Layanan tidak ditemukan. Ulangi proses.");
            $this->end();
            return;
        }

        $this->serviceName = $best['name'];
        $this->serviceRate = (float) $best['rate'];

        try {
            // --- EDUKASI & PERINGATAN (Wajib untuk IG Followers) ---
            if ($this->platform === 'Instagram' && $this->category === 'followers') {
                $igSettingPath = public_path('images/igsetting.png');
                if (file_exists($igSettingPath)) {
                    try {
                        // Pastikan file bisa dibaca
                        $bot->sendPhoto(
                            photo: InputFile::make(fopen($igSettingPath, 'r')),
                            caption: "⚠️ *PENTING: Setting Instagram Kamu*\n\n"
                                   . "Agar followers masuk, pastikan settingan berikut *DIMATIKAN*:\n"
                                   . "1. Follow and Invite Friends\n"
                                   . "2. Flag for Review / Laporkan untuk Ditinjau\n\n"
                                   . "Lakukan seperti pada gambar di atas agar pesananmu lancar. ✅",
                            parse_mode: 'Markdown'
                        );
                    } catch (\Throwable $photoError) {
                        \Illuminate\Support\Facades\Log::error('CRITICAL: Failed to send IG guide photo', [
                            'error' => $photoError->getMessage(),
                            'path'  => $igSettingPath
                        ]);
                        // Jika gagal kirim foto, kirim teks peringatan keras
                        $bot->sendMessage("⚠️ *PERINGATAN:* Pastikan settingan 'Follow and Invite Friends' dan 'Flag for Review' di IG kamu sudah *DIMATIKAN* agar followers masuk!");
                    }
                } else {
                    \Illuminate\Support\Facades\Log::warning('IG Guide photo not found at: ' . $igSettingPath);
                }
            }

            $bot->sendMessage(
                text: "🔒 *PENTING: JANGAN PRIVATE AKUN!*\n"
                    . "Pastikan akun target bersifat *PUBLIK* selama proses berlangsung.\n\n"
                    . "🔗 *Langkah 6: Masukkan Link Target*\n"
                    . "Contoh: `https://www.instagram.com/nuestore/`",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'))
            );

            $this->next('confirmOrder');

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('askLink Error: ' . $e->getMessage());
            $bot->sendMessage("❌ Terjadi kesalahan teknis. Silakan masukkan link target secara manual atau hubungi admin.");
            $this->next('confirmOrder');
        }
    }

    // ─────────────────────────────────────────────
    // STEP 7: Konfirmasi Final
    // ─────────────────────────────────────────────

    public function confirmOrder(Nutgram $bot): void
    {
        $link = $bot->message()?->text;

        if (!$link || filter_var($link, FILTER_VALIDATE_URL) === false) {
            $bot->sendMessage("❌ Link tidak valid. Kirim URL yang benar:");
            return;
        }

        $this->targetLink = $link;

        // Hitung harga final
        $markupMultiplier = (float) NuestoreSetting::get('global_markup_multiplier', '2.5');
        $this->basePrice  = (float) ceil(($this->serviceRate * $markupMultiplier / 1000) * $this->quantity);
        $this->uniqueCode = rand(1, 99); // Kode unik lebih kecil (1-99) agar tidak terlalu membengkak
        $this->totalAmount = $this->basePrice + $this->uniqueCode;
        $this->modalCost   = (float) ($this->serviceRate / 1000 * $this->quantity);
        $this->profit      = $this->basePrice - $this->modalCost;

        $baseFmt = number_format($this->basePrice, 0, ',', '.');

        $bot->sendMessage(
            text: "📋 *KONFIRMASI PESANAN FINAL*\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━\n"
                . "📦 Layanan: {$this->serviceName}\n"
                . "🔗 Target: `{$this->targetLink}`\n"
                . "🔢 Jumlah: " . number_format($this->quantity) . "\n\n"
                . "💰 *HARGA: Rp {$baseFmt}*\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━\n"
                . "Klik tombol di bawah untuk melanjutkan ke pembayaran.",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('✅ Bayar Sekarang', callback_data: 'co_pay'))
                ->addRow(InlineKeyboardButton::make('❌ Batalkan',      callback_data: 'co_cancel'))
        );

        $this->next('generatePayment');
    }

    // ─────────────────────────────────────────────
    // STEP 8: Kirim QRIS & Simpan DB
    // ─────────────────────────────────────────────

    public function generatePayment(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';
        if ($cb !== 'co_pay') {
            $this->start($bot);
            return;
        }
        $bot->answerCallbackQuery();

        try {
            // Save order
            $order = NuestoreOrder::create([
                'id'               => (string) \Illuminate\Support\Str::uuid(),
                'customer_id'      => NuestoreCustomer::fromTelegramUser($bot->user())->id,
                'platform'         => $this->platform,
                'category'         => $this->category,
                'service_id'       => $this->serviceId,
                'service_name'     => $this->serviceName,
                'target_link'      => $this->targetLink,
                'quantity'         => $this->quantity,
                'base_price'       => $this->basePrice,
                'unique_code'      => $this->uniqueCode,
                'total_amount'     => $this->totalAmount,
                'modal_cost'       => $this->modalCost,
                'profit_estimated' => $this->profit,
                'status'           => 'PENDING_PAYMENT',
                'expires_at'       => now()->addHours(2),
            ]);

            $this->orderId = $order->id;

            // Kirim QRIS
            $qrisPath = public_path('images/qris.jpg');
            $totalFmt = number_format($this->totalAmount, 0, ',', '.');

            if (file_exists($qrisPath) && is_readable($qrisPath)) {
                $bot->sendPhoto(
                    photo: InputFile::make($qrisPath),
                    caption: "🏦 *SCAN QRIS UNTUK BAYAR*\n\n"
                           . "Total: *Rp {$totalFmt}*\n"
                           . "_(Sudah termasuk kode unik #{$this->uniqueCode})_\n\n"
                           . "⚠️ *WAJIB BAYAR SESUAI NOMINAL!*",
                    parse_mode: 'Markdown'
                );
            }

            $bot->sendMessage(
                text: "✅ *Pesanan Menunggu Pembayaran*\n\n"
                    . "Silakan bayar *Rp {$totalFmt}*.\n"
                    . "Setelah berhasil, klik tombol di bawah untuk kirim bukti.",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('📸 Sudah Bayar, Kirim Bukti', callback_data: 'co_proof'))
                    ->addRow(InlineKeyboardButton::make('❌ Batalkan Pesanan', callback_data: 'co_cancel'))
            );

            // Notif Admin
            $notif = new NotificationService();
            $notif->notifyNewCustomerOrder($order);

            $this->next('waitProof');

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Order Final Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $bot->sendMessage("❌ Terjadi kesalahan saat memproses pembayaran. Hubungi admin.");
            $this->end();
        }
    }

    // ─────────────────────────────────────────────
    // STEP 9: Tunggu screenshot bukti bayar
    // ─────────────────────────────────────────────

    public function waitProof(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';

        if ($cb === 'co_cancel') {
            $bot->answerCallbackQuery();
            if ($this->orderId) {
                NuestoreOrder::where('id', $this->orderId)
                    ->where('status', 'PENDING_PAYMENT')
                    ->update(['status' => 'CANCELLED']);
            }
            $bot->sendMessage("❌ Pesanan dibatalkan.");
            $this->end();
            return;
        }

        if ($cb === 'co_proof') {
            $bot->answerCallbackQuery();
            $bot->sendMessage(
                text: "📸 *Kirim screenshot bukti pembayaran sekarang.*",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'))
            );
            return;
        }

        // Pastikan yang dikirim adalah FOTO, bukan dokumen/file/video
        $photo = $bot->message()?->photo;
        
        if ($bot->message()?->document || $bot->message()?->video || !$photo) {
            $bot->sendMessage(
                text: "⚠️ *FORMAT SALAH*\n\n"
                    . "Harap kirimkan bukti pembayaran dalam format **GAMBAR (Foto)** langsung, bukan sebagai File/Dokumen atau Video.\n\n"
                    . "Silakan coba kirim ulang fotonya:",
                parse_mode: 'Markdown'
            );
            return;
        }

        // Ambil ID foto (Telegram menyediakan beberapa ukuran, kita ambil yang paling pas)
        $fileId = end($photo)->file_id;
        $order = NuestoreOrder::find($this->orderId);

        if (!$order || $order->status !== 'PENDING_PAYMENT') {
            $bot->sendMessage("❌ Pesanan tidak ditemukan.");
            $this->end();
            return;
        }

        $order->update([
            'proof_file_id' => $fileId,
            'status'        => 'PROOF_SUBMITTED',
        ]);

        $notif = new NotificationService();
        $adminMsgId = $notif->notifyProofSubmitted($order);

        if ($adminMsgId) {
            $order->update(['admin_message_id' => $adminMsgId]);
        }

        $bot->sendMessage(
            text: "✅ *Bukti pembayaran diterima!*\n\n"
                . "Admin sedang memverifikasi pembayaranmu (5-15 menit).\n"
                . "Kamu akan mendapat notifikasi otomatis jika sudah diproses.",
            parse_mode: 'Markdown'
        );

        $this->end();
    }
}

