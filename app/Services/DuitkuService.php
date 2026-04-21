<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DuitkuService
{
    private string $apiUrl;
    private string $merchantCode;
    private string $apiKey;
    private bool $isSandbox;

    public function __construct()
    {
        $this->merchantCode = config('services.duitku.merchant_code');
        $this->apiKey       = config('services.duitku.api_key');
        $this->isSandbox    = config('services.duitku.sandbox', true);
        $this->apiUrl       = $this->isSandbox
            ? 'https://sandbox.duitku.com/webapi/api/merchant'
            : 'https://passport.duitku.com/webapi/api/merchant';
    }

    public function createTransaction(
        string $orderId,
        int $amount,
        string $productDetails,
        string $email,
        string $phoneNumber,
        string $customerName,
        string $returnUrl,
        string $callbackUrl
    ): ?array {
        try {
            $signature = md5($this->merchantCode . $orderId . $amount . $this->apiKey);

            $payload = [
                'merchantCode'   => $this->merchantCode,
                'paymentAmount'  => $amount,
                'merchantOrderId'=> $orderId,
                'productDetails' => $productDetails,
                'email'          => $email,
                'phoneNumber'    => $phoneNumber,
                'customerVaName' => $customerName,
                'paymentMethod'  => 'QR',
                'returnUrl'      => $returnUrl,
                'callbackUrl'    => $callbackUrl,
                'signature'      => $signature,
                'expiryPeriod'   => 60,
            ];

            $response = Http::post("{$this->apiUrl}/createInvoice", $payload);

            if ($response->failed()) {
                Log::error('Duitku Create Transaction Error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Duitku Exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function verifyCallback(array $data): bool
    {
        $signature = md5(
            $this->merchantCode .
            $data['amount'] .
            $data['merchantOrderId'] .
            $this->apiKey
        );

        return $signature === $data['signature'];
    }
}