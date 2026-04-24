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
use Illuminate\Support\Str;

class CustomerOrderConversation extends Conversation
{
    // State yang tersimpan antar step
    protected ?string $platform     = null;
    protected ?string $category     = null;
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
        $user     = $bot->user();
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

        // Tampilkan pilihan platform dengan InlineKeyboard (lebih reliable, tidak bentrok onText)
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

        $this->next('selectCategory');
    }

    // ─────────────────────────────────────────────
    // STEP 2: Pilih kategori layanan
    // ─────────────────────────────────────────────

    public function selectCategory(Nutgram $bot): void
    {
        try {
            $cb = $bot->callbackQuery()?->data ?? '';

            \Illuminate\Support\Facades\Log::info('selectCategory called', [
                'cb'       => $cb,
                'platform' => $this->platform,
                'user'     => $bot->userId(),
            ]);

            if ($cb === 'co_cancel') {
                $bot->answerCallbackQuery();
                $bot->sendMessage("❌ Order dibatalkan.");
                $this->end();
                return;
            }

            if (str_starts_with($cb, 'co_platform:')) {
                $this->platform = substr($cb, 12); // 'Instagram' atau 'TikTok'
                $bot->answerCallbackQuery();
            }

            if (!$this->platform) {
                $bot->sendMessage("⚠️ Silakan pilih platform dari tombol di atas.");
                return;
            }

            $categories = [
                'Instagram' => ['Followers ID 🇮🇩', 'Followers WW 🌐', 'Likes ID 🇮🇩', 'Likes WW 🌐', 'Views WW 🌐', 'Story Views 🌐'],
                'TikTok'    => ['Followers ID 🇮🇩', 'Followers WW 🌐', 'Likes ID 🇮🇩', 'Likes WW 🌐', 'Views WW 🌐'],
            ];

            $catList  = $categories[$this->platform] ?? ['Followers WW 🌐', 'Likes WW 🌐'];
            $keyboard = InlineKeyboardMarkup::make();
            foreach (array_chunk($catList, 2) as $chunk) {
                $btnRow = [];
                foreach ($chunk as $cat) {
                    $btnRow[] = InlineKeyboardButton::make($cat, callback_data: 'co_cat:' . $cat);
                }
                $keyboard->addRow(...$btnRow);
            }
            $keyboard->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'));

            $bot->sendMessage(
                text: "📱 Platform: *{$this->platform}*\n\n🗂️ *Langkah 2: Pilih Kategori Layanan*",
                parse_mode: 'Markdown',
                reply_markup: $keyboard
            );

            $this->next('inputLink');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('selectCategory failed', ['error' => $e->getMessage()]);
            $bot->sendMessage("❌ Terjadi kesalahan teknis. Silakan ketik /start untuk mulai ulang.");
            $this->end();
        }
    }

    // ─────────────────────────────────────────────
    // STEP 3: Input link target
    // ─────────────────────────────────────────────

    public function inputLink(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';

        if ($cb === 'co_cancel') {
            $bot->answerCallbackQuery();
            $bot->sendMessage("❌ Order dibatalkan.");
            $this->end();
            return;
        }

        if (str_starts_with($cb, 'co_cat:')) {
            $this->category = substr($cb, 7);
            $bot->answerCallbackQuery();
        }

        if (!$this->category) {
            $bot->sendMessage("⚠️ Silakan pilih kategori terlebih dahulu.");
            return;
        }

        $linkExample = match (true) {
            str_contains(strtolower($this->platform), 'instagram') => 'https://www.instagram.com/username/',
            str_contains(strtolower($this->platform), 'tiktok')    => 'https://www.tiktok.com/@username',
            str_contains(strtolower($this->platform), 'youtube')   => 'https://www.youtube.com/@channel',
            default                                                 => 'https://...',
        };

        $bot->sendMessage(
            text: "📱 Platform: *{$this->platform}*\n"
                . "🗂️ Layanan: *{$this->category}*\n\n"
                . "🔗 *Langkah 3: Masukkan Link Target*\n\n"
                . "Contoh: `{$linkExample}`\n\n"
                . "Kirim link-nya sekarang:",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'))
        );

        $this->next('inputQuantity');
    }

    // ─────────────────────────────────────────────
    // STEP 4: Input jumlah
    // ─────────────────────────────────────────────

    public function inputQuantity(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';
        if ($cb === 'co_cancel') {
            $bot->answerCallbackQuery();
            $bot->sendMessage("❌ Order dibatalkan.");
            $this->end();
            return;
        }

        $text = trim($bot->message()?->text ?? '');

        if (!filter_var($text, FILTER_VALIDATE_URL)) {
            $bot->sendMessage("❌ Link tidak valid. Pastikan diawali dengan `https://`.", parse_mode: 'Markdown');
            return;
        }

        $this->targetLink = $text;

        $bot->sendMessage(
            text: "🔗 Target: `{$this->targetLink}`\n\n"
                . "🔢 *Langkah 4: Masukkan Jumlah*\n\n"
                . "Contoh: `1000`",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'))
        );

        $this->next('findService');
    }

    // ─────────────────────────────────────────────
    // STEP 5: Cari layanan, hitung harga, tampilkan QRIS
    // ─────────────────────────────────────────────

    public function findService(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';
        if ($cb === 'co_cancel') {
            $bot->answerCallbackQuery();
            $bot->sendMessage("❌ Order dibatalkan.");
            $this->end();
            return;
        }

        $text = trim($bot->message()?->text ?? '');

        if (!is_numeric($text) || (int)$text <= 0) {
            $bot->sendMessage("❌ Jumlah harus berupa angka positif. Contoh: `1000`", parse_mode: 'Markdown');
            return;
        }

        $this->quantity = (int) $text;

        $bot->sendMessage("⏳ _Mencari layanan terbaik untuk kamu..._", parse_mode: 'Markdown');

        // Cari layanan dari Lollipop
        $lollipop = new LollipopSmmService();
        $services = $lollipop->getServices();

        if (!$services) {
            \Illuminate\Support\Facades\Log::error('OrderConversation: Failed to fetch services from Lollipop', [
                'user_id' => $bot->userId()
            ]);
            $bot->sendMessage("❌ Gagal mengambil data layanan. Saldo mungkin habis atau API sedang gangguan.");
            $this->end();
            return;
        }

        \Illuminate\Support\Facades\Log::info('Filtering services', [
            'platform' => $this->platform,
            'category' => $this->category,
            'total_services' => count($services)
        ]);

        $catLower = strtolower($this->category);
        $region   = 'all';
        if (str_contains($catLower, 'id'))  $region = 'id';
        if (str_contains($catLower, 'ww'))  $region = 'ww';

        $baseCategory = '';
        foreach (['followers', 'likes', 'views', 'subscribers', 'story', 'saves', 'shares'] as $kw) {
            if (str_contains($catLower, $kw)) { $baseCategory = $kw; break; }
        }

        $platform = strtolower($this->platform);

        $filtered = collect($services)->filter(function ($s) use ($platform, $baseCategory, $region) {
            $name     = strtolower($s['name']);
            $category = strtolower($s['category'] ?? '');

            // 1. Cek Platform (Instagram/TikTok/dll)
            $platformMatch = str_contains($name, $platform) || str_contains($category, $platform);
            if (!$platformMatch || !$baseCategory) return false;

            // 2. Cek Kategori Utama (Followers/Likes/dll)
            $keywords = [
                'followers'   => ['follower', 'pengikut'],
                'likes'       => ['like', 'suka'],
                'views'       => ['view', 'tonton'],
                'story'       => ['story'],
                'saves'       => ['save'],
                'shares'      => ['share'],
                'subscribers' => ['subscriber', 'langganan']
            ];
            
            $kws           = $keywords[$baseCategory] ?? [$baseCategory];
            $categoryMatch = false;
            foreach ($kws as $kw) {
                if (str_contains($name, $kw) || str_contains($category, $kw)) {
                    $categoryMatch = true;
                    break;
                }
            }
            if (!$categoryMatch) return false;

            // 3. Cek Region (Indo vs WW)
            $isIndo = str_contains($name, 'indonesia') || str_contains($name, 'indo ') || str_contains($category, 'indonesia');
            if ($region === 'id' && !$isIndo) return false;
            if ($region === 'ww' && $isIndo)  return false;

            return true;
        })->sortBy('rate')->values();

        \Illuminate\Support\Facades\Log::info('Filter result', [
            'count' => $filtered->count(),
            'first_match' => $filtered->first()['name'] ?? 'none'
        ]);

        if ($filtered->isEmpty()) {
            $bot->sendMessage("❌ Maaf, tidak ada layanan yang cocok untuk kategori ini saat ini. Silakan pilih kategori lain.");
            $this->end();
            return;
        }

        $best = $filtered->first();

        if ($this->quantity < $best['min'] || $this->quantity > $best['max']) {
            $bot->sendMessage(
                text: "❌ *Jumlah tidak sesuai syarat layanan.*\n\n"
                    . "Layanan: `{$best['name']}`\n"
                    . "Min: " . number_format($best['min'], 0, ',', '.') . "\n"
                    . "Max: " . number_format($best['max'], 0, ',', '.') . "\n"
                    . "Kamu input: " . number_format($this->quantity, 0, ',', '.') . "\n\n"
                    . "Silakan mulai ulang dan masukkan jumlah yang sesuai.",
                parse_mode: 'Markdown'
            );
            $this->end();
            return;
        }

        $this->serviceId   = (int) $best['service'];
        $this->serviceName = $best['name'];
        $this->serviceRate = (float) $best['rate'];
        $this->serviceMin  = (int) $best['min'];
        $this->serviceMax  = (int) $best['max'];

        $markup           = (float) NuestoreSetting::get('global_markup_multiplier', '2.0');
        $this->basePrice  = (float) ceil(($this->serviceRate * $markup / 1000) * $this->quantity);
        $this->uniqueCode = rand(1, 999); // Kode unik identifier
        $this->totalAmount = $this->basePrice + $this->uniqueCode;
        $this->modalCost   = (float) ($this->serviceRate / 1000 * $this->quantity);
        $this->profit      = $this->basePrice - $this->modalCost;

        $baseFmt   = number_format($this->basePrice, 0, ',', '.');
        $totalFmt  = number_format($this->totalAmount, 0, ',', '.');
        $profitFmt = number_format($this->profit, 0, ',', '.');

        try {
            // Kirim QRIS image
            $qrisPath = public_path('images/qris.jpg');

            if (file_exists($qrisPath)) {
                $bot->sendPhoto(
                    photo: fopen($qrisPath, 'r'),
                    caption: "🏦 *Scan QRIS untuk Pembayaran*",
                    parse_mode: 'Markdown'
                );
            }

        // Create the order NOW so the 15-min timer starts and hasPendingOrder() blocks spam
        $user     = $bot->user();
        $customer = NuestoreCustomer::fromTelegramUser($user);

            $order = NuestoreOrder::create([
                'id'                => (string) \Illuminate\Support\Str::uuid(),
                'customer_id'       => $customer->id,
                'platform'          => $this->platform,
                'category'          => $this->category,
                'service_id'        => $this->serviceId,
                'service_name'      => $this->serviceName,
                'target_link'       => $this->targetLink,
                'quantity'          => $this->quantity,
                'base_price'        => $this->basePrice,
                'unique_code'       => $this->uniqueCode,
                'total_amount'      => $this->totalAmount,
                'modal_cost'        => $this->modalCost,
                'profit_estimated'  => $this->profit,
                'status'            => 'PENDING_PAYMENT',
                'expires_at'        => now()->addMinutes(15),
            ]);

        $customer->update(['last_order_at' => now()]);

        // Notify admin of new order (no proof yet)
        $notif = new NotificationService();
        $notif->notifyNewCustomerOrder($order);

        // Store order ID in conversation state for waitProof
        $this->orderId = $order->id;

        $bot->sendMessage(
            text: "✅ *Ringkasan Pesanan*\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━\n"
                . "📱 Platform: {$this->platform}\n"
                . "🗂️ Layanan: {$this->serviceName}\n"
                . "🔗 Target: `{$this->targetLink}`\n"
                . "🔢 Jumlah: " . number_format($this->quantity, 0, ',', '.') . "\n\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━\n"
                . "💰 *Total yang harus dibayar:*\n"
                . "# *Rp {$totalFmt}*\n"
                . "_(Harga Rp {$baseFmt} + Kode Unik #{$this->uniqueCode})_\n\n"
                . "⚠️ *WAJIB bayar PERSIS Rp {$totalFmt}*\n"
                . "Kode unik membantu admin mengidentifikasi pembayaranmu.\n\n"
                . "⏰ *Batas waktu: 15 menit*\n\n"
                . "Setelah bayar, klik tombol di bawah dan kirim screenshot bukti:",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('📸 Sudah Bayar, Kirim Bukti', callback_data: 'co_proof'),
                )
                ->addRow(
                    InlineKeyboardButton::make('❌ Batalkan Pesanan', callback_data: 'co_cancel'),
                )
        );

        $this->next('waitProof');

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Order Flow Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $bot->sendMessage("❌ Terjadi kesalahan saat memproses pesanan. Silakan hubungi admin.");
            $this->end();
        }
    }

    // ─────────────────────────────────────────────
    // STEP 6: Tunggu screenshot bukti bayar
    // ─────────────────────────────────────────────

    public function waitProof(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';

        if ($cb === 'co_cancel') {
            $bot->answerCallbackQuery();
            // Cancel the pending order in DB
            if ($this->orderId) {
                NuestoreOrder::where('id', $this->orderId)
                    ->where('status', 'PENDING_PAYMENT')
                    ->update(['status' => 'CANCELLED']);
            }
            $bot->sendMessage("❌ Pesanan dibatalkan. Kamu bisa order lagi kapan saja.");
            $this->end();
            return;
        }

        if ($cb === 'co_proof') {
            $bot->answerCallbackQuery();
            $bot->sendMessage(
                text: "📸 *Kirim screenshot bukti pembayaran sekarang.*\n\n"
                    . "Pastikan terlihat:\n"
                    . "• Nominal yang dibayar\n"
                    . "• Nama toko tujuan\n"
                    . "• Tanggal & waktu transaksi",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'co_cancel'))
            );
            return; // Tetap di step ini, tunggu foto
        }

        // Tangkap foto yang dikirim user
        $photo = $bot->message()?->photo;

        if (!$photo) {
            $bot->sendMessage(
                text: "❌ Kirim *gambar/foto* screenshot bukti bayarnya ya, bukan teks.",
                parse_mode: 'Markdown'
            );
            return;
        }

        // Ambil foto resolusi tertinggi
        $fileId = end($photo)->file_id;

        // Update existing PENDING_PAYMENT order to PROOF_SUBMITTED
        $order = NuestoreOrder::find($this->orderId);

        if (!$order || $order->status !== 'PENDING_PAYMENT') {
            $bot->sendMessage("❌ Pesanan tidak ditemukan atau sudah expired. Silakan buat pesanan baru.");
            $this->end();
            return;
        }

        $order->update([
            'proof_file_id' => $fileId,
            'status'        => 'PROOF_SUBMITTED',
        ]);

        // Kirim notif ke Admin Bot
        $notif = new NotificationService();
        $adminMsgId = $notif->notifyProofSubmitted($order);

        // Simpan admin_message_id untuk diedit nanti saat approve/reject
        if ($adminMsgId) {
            $order->update(['admin_message_id' => $adminMsgId]);
        }

        $bot->sendMessage(
            text: "✅ *Bukti pembayaran diterima!*\n\n"
                . "Admin kami sedang memverifikasi pembayaranmu.\n"
                . "Biasanya konfirmasi berlangsung dalam *5-15 menit*.\n\n"
                . "Kamu akan mendapat notifikasi otomatis dari bot ini setelah dikonfirmasi.\n\n"
                . "_Jangan tutup bot ini ya!_ 😊",
            parse_mode: 'Markdown'
        );

        $this->end();
    }
}
