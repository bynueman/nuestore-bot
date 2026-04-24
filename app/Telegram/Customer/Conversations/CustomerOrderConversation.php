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

            // Cek pending order
            if ($customer->hasPendingOrder()) {
                $pending = $customer->pendingOrder();
                $statusText = $pending->status === 'PROOF_SUBMITTED'
                    ? "📸 Bukti sudah dikirim, menunggu konfirmasi admin."
                    : "💳 Belum dibayar. Silakan bayar atau batalkan dulu.";

                $bot->sendMessage(
                    text: "⚠️ *Kamu masih punya pesanan aktif!*\n\n"
                        . "{$statusText}\n\n"
                        . "📦 {$pending->service_name}\n"
                        . "💰 Rp " . number_format($pending->total_amount, 0, ',', '.') . "\n\n"
                        . "Selesaikan atau batalkan pesanan sebelumnya untuk membuat pesanan baru.",
                    parse_mode: 'Markdown',
                    reply_markup: InlineKeyboardMarkup::make()
                        ->addRow(
                            InlineKeyboardButton::make('❌ Batalkan Pesanan Lama', callback_data: "customer_cancel:{$pending->id}"),
                        )
                );
                $this->end();
                return;
            }

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
                        InlineKeyboardButton::make('📽 YouTube',   callback_data: 'co_platform:YouTube'),
                        InlineKeyboardButton::make('🕊 Twitter',   callback_data: 'co_platform:Twitter'),
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
            $this->next('findService');
            return;
        }

        $this->start($bot);
    }

    // ─────────────────────────────────────────────
    // STEP 4: Tampilkan 5 Rekomendasi Layanan
    // ─────────────────────────────────────────────

    public function findService(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';
        if (!str_starts_with($cb, 'co_cat:')) {
            $this->start($bot);
            return;
        }

        $this->category = substr($cb, 7);
        $bot->answerCallbackQuery();

        $bot->editMessageText(
            text: "⏳ *Mencari 5 layanan terbaik untuk kamu...*",
            parse_mode: 'Markdown'
        );

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

            return true;
        })->sortBy('rate')->take(5)->values();

        if ($filtered->isEmpty()) {
            $bot->sendMessage("❌ Maaf, tidak ada layanan yang cocok saat ini. Silakan pilih kategori lain.");
            $this->end();
            return;
        }

        $markup = InlineKeyboardMarkup::make();
        $markupMultiplier = (float) NuestoreSetting::get('global_markup_multiplier', '2.0');

        $text = "⭐ *5 Rekomendasi Layanan Terbaik*\n"
              . "Platform: {$this->platform} | Region: " . ($region === 'id' ? 'ID 🇮🇩' : 'WW 🌐') . "\n"
              . "━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        foreach ($filtered as $index => $s) {
            $price = ceil(($s['rate'] * $markupMultiplier));
            $priceFmt = number_format($price, 0, ',', '.');
            $num = $index + 1;
            
            $text .= "*{$num}. {$s['name']}*\n";
            $text .= "💰 Rp {$priceFmt} / 1000\n";
            $text .= "📊 Min: {$s['min']} | Max: {$s['max']}\n\n";

            $markup->addRow(InlineKeyboardButton::make(
                "Pilih #{$num} (Rp {$priceFmt})", 
                callback_data: "co_sid:{$s['service']}"
            ));
        }

        $markup->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'));

        $bot->editMessageText(
            text: $text,
            parse_mode: 'Markdown',
            reply_markup: $markup
        );

        $this->next('askLink');
    }

    // ─────────────────────────────────────────────
    // STEP 5: Edukasi (IG) & Input Link
    // ─────────────────────────────────────────────

    public function askLink(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';
        if (!str_starts_with($cb, 'co_sid:')) {
            $this->start($bot);
            return;
        }

        $this->serviceId = (int) substr($cb, 7);
        $bot->answerCallbackQuery();

        // Get service details for validation later
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
        $this->serviceMin  = (int) $best['min'];
        $this->serviceMax  = (int) $best['max'];

        // --- EDUKASI & PERINGATAN ---
        if ($this->platform === 'Instagram' && $this->category === 'followers') {
            $igSettingPath = public_path('images/igsetting.png');
            if (file_exists($igSettingPath)) {
                $bot->sendPhoto(
                    photo: InputFile::make($igSettingPath),
                    caption: "⚠️ *PENTING: Setting Instagram Kamu*\n\n"
                           . "Agar followers masuk, pastikan settingan berikut *DIMATIKAN*:\n"
                           . "1. Follow and Invite Friends\n"
                           . "2. Flag for Review / Laporkan untuk Ditinjau\n\n"
                           . "Lihat gambar di atas untuk panduannya.",
                    parse_mode: 'Markdown'
                );
            }
        }

        $bot->sendMessage(
            text: "🔒 *PENTING: JANGAN PRIVATE AKUN!*\n"
                . "Pastikan akun target bersifat *PUBLIK* selama proses berlangsung.\n\n"
                . "🔗 *Langkah 5: Masukkan Link Target*\n"
                . "Contoh: `https://www.instagram.com/nuestore/`",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'))
        );

        $this->next('askQuantity');
    }

    // ─────────────────────────────────────────────
    // STEP 6: Input Jumlah (Qty)
    // ─────────────────────────────────────────────

    public function askQuantity(Nutgram $bot): void
    {
        $link = $bot->message()?->text;

        if (!$link || filter_var($link, FILTER_VALIDATE_URL) === false) {
            $bot->sendMessage("❌ Link tidak valid. Kirim URL yang benar:");
            return;
        }

        $this->targetLink = $link;

        $bot->sendMessage(
            text: "🔢 *Langkah 6: Masukkan Jumlah*\n\n"
                . "⚠️ *INFO:* Minimal order biasanya *100* unit.\n"
                . "🚩 Batas layanan ini: Min *{$this->serviceMin}* - Max *{$this->serviceMax}*\n\n"
                . "Kirim jumlah yang ingin kamu beli:",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'))
        );

        $this->next('confirmOrder');
    }

    // ─────────────────────────────────────────────
    // STEP 7: Konfirmasi Final & Simpan PENDING
    // ─────────────────────────────────────────────

    public function confirmOrder(Nutgram $bot): void
    {
        $qty = $bot->message()?->text;

        if (!is_numeric($qty)) {
            $bot->sendMessage("❌ Jumlah harus berupa angka:");
            return;
        }

        $qty = (int) $qty;
        if ($qty < $this->serviceMin || $qty > $this->serviceMax) {
            $bot->sendMessage("❌ Jumlah harus antara {$this->serviceMin} - {$this->serviceMax}:");
            return;
        }

        $this->quantity = $qty;

        // Hitung harga
        $markupMultiplier = (float) NuestoreSetting::get('global_markup_multiplier', '2.0');
        $this->basePrice  = (float) ceil(($this->serviceRate * $markupMultiplier / 1000) * $this->quantity);
        $this->uniqueCode = rand(1, 999);
        $this->totalAmount = $this->basePrice + $this->uniqueCode;
        $this->modalCost   = (float) ($this->serviceRate / 1000 * $this->quantity);
        $this->profit      = $this->basePrice - $this->modalCost;

        $totalFmt = number_format($this->totalAmount, 0, ',', '.');

        $bot->sendMessage(
            text: "📋 *KONFIRMASI PESANAN FINAL*\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━\n"
                . "📦 Layanan: {$this->serviceName}\n"
                . "🔗 Target: `{$this->targetLink}`\n"
                . "🔢 Jumlah: " . number_format($this->quantity) . "\n\n"
                . "💰 Total Bayar: *Rp {$totalFmt}*\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━\n"
                . "Ketik *YA* untuk konfirmasi dan lanjut ke pembayaran, atau *BATAL*.",
            parse_mode: 'Markdown'
        );

        $this->next('generatePayment');
    }

    // ─────────────────────────────────────────────
    // STEP 8: Kirim QRIS & Buat Order di DB
    // ─────────────────────────────────────────────

    public function generatePayment(Nutgram $bot): void
    {
        $input = strtoupper(trim($bot->message()?->text ?? ''));

        if ($input === 'BATAL') {
            $bot->sendMessage("❌ Dibatalkan.");
            $this->end();
            return;
        }

        if ($input !== 'YA') {
            $bot->sendMessage("Ketik *YA* untuk lanjut atau *BATAL* untuk berhenti.");
            return;
        }

        try {
            // Save order as PENDING_PAYMENT
            $order = NuestoreOrder::create([
                'id'               => (string) Str::uuid(),
                'customer_id'      => NuestoreCustomer::fromTelegramUser($bot->user())->id,
                'platform'         => $this->platform,
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
            ]);

            $this->orderId = $order->id;

            // Kirim QRIS
            $qrisPath = public_path('images/qris.jpg');
            if (file_exists($qrisPath) && is_readable($qrisPath)) {
                $bot->sendPhoto(
                    photo: InputFile::make($qrisPath),
                    caption: "🏦 *SCAN QRIS UNTUK BAYAR*\n\n"
                           . "Total: *Rp " . number_format($this->totalAmount, 0, ',', '.') . "*\n"
                           . "*(Harus sesuai nominal agar dicek lebih cepat)*",
                    parse_mode: 'Markdown'
                );
            }

            $bot->sendMessage(
                text: "✅ *Pesanan Tersimpan!*\n\n"
                    . "Silakan lakukan transfer sesuai nominal di atas. Setelah bayar, klik tombol di bawah untuk kirim bukti.",
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
            \Illuminate\Support\Facades\Log::error('Order Flow Error: ' . $e->getMessage());
            $bot->sendMessage("❌ Terjadi kesalahan teknis. Silakan lapor admin.");
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

        $photo = $bot->message()?->photo;
        if (!$photo) {
            $bot->sendMessage("❌ Kirim *gambar/foto* bukti bayarnya ya.");
            return;
        }

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

