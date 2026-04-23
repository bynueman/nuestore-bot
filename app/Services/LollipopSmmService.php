<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LollipopSmmService
{
    private string $apiUrl = 'https://lollipop-smm.com/api/v2';
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.lollipop.key');
    }

    private function connect(array $data): ?array
    {
        try {
            $payload = array_merge(['key' => $this->apiKey], $data);
            $response = Http::asForm()->post($this->apiUrl, $payload);

            if ($response->failed()) {
                Log::error('Lollipop API Error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Lollipop API Exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function getServices(): ?array
    {
        return $this->connect(['action' => 'services']);
    }

    public function createOrder(int $serviceId, string $link, ?int $quantity = null, array $extra = []): ?array
    {
        $data = [
            'action'  => 'add',
            'service' => $serviceId,
            'link'    => $link,
        ];

        if ($quantity) {
            $data['quantity'] = $quantity;
        }

        return $this->connect(array_merge($data, $extra));
    }

    public function getStatus(string $orderId): ?array
    {
        return $this->connect([
            'action' => 'status',
            'order'  => $orderId,
        ]);
    }

    public function getStatuses(array $orderIds): ?array
    {
        return $this->connect([
            'action' => 'status',
            'orders' => implode(',', $orderIds),
        ]);
    }

    public function getBalance(): ?array
    {
        return $this->connect(['action' => 'balance']);
    }
}