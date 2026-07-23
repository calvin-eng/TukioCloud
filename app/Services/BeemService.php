<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BeemService
{
    private string $apiKey;
    private string $secretKey;
    private string $senderId;

    public function __construct()
    {
        $this->apiKey = config('services.beem.api_key');
        $this->secretKey = config('services.beem.secret_key');
        $this->senderId = config('services.beem.sender_id', 'TukioCloud');
    }

    public function send(string $phone, string $message): array
    {
        $normalizedPhone = ltrim($phone, '+');
        $auth = base64_encode($this->apiKey.':'.$this->secretKey);

        $response = Http::withHeaders([
                'Authorization' => 'Basic '.$auth,
                'Content-Type' => 'application/json',
            ])
            ->timeout(15)
            ->post('https://apisms.beem.africa/v1/send', [
                'source_addr' => $this->senderId,
                'encoding' => 0,
                'message' => $message,
                'recipients' => [
                    ['recipient_id' => 1, 'dest_addr' => $normalizedPhone],
                ],
            ]);

        $body = $response->json();

        $hasError = isset($body['error']) || isset($body['errors']);
        if ($response->successful() && ! $hasError) {
            $ref = $body['response'][0]['reference'] ?? null;
            return ['success' => true, 'provider_ref' => $ref, 'response' => json_encode($body)];
        }

        Log::warning('Beem SMS failed', ['phone' => $phone, 'response' => $body]);
        return ['success' => false, 'provider_ref' => null, 'response' => json_encode($body)];
    }
}
