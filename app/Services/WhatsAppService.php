<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private ?string $phoneNumberId;
    private ?string $accessToken;
    private string $apiVersion;
    private string $templateName;

    public function __construct()
    {
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->accessToken = config('services.whatsapp.access_token');
        $this->apiVersion = config('services.whatsapp.api_version', 'v21.0');
        $this->templateName = config('services.whatsapp.template_name', 'tukio_event_invitation');
    }

    public function sendInvitation(string $phone, string $imagePath, string $guestName, string $eventName, string $eventDateTime, string $eventVenue, string $language = 'sw'): array
    {
        if (!$this->isConfigured()) {
            Log::warning('WhatsApp not configured – missing credentials');
            return ['success' => false, 'provider_ref' => null, 'response' => 'whatsapp_not_configured'];
        }

        Log::info('WhatsApp send starting', [
            'phone' => $phone,
            'guest_name' => $guestName,
            'event_name' => $eventName,
            'event_datetime' => $eventDateTime,
            'event_venue' => $eventVenue,
            'language' => $language,
        ]);

        $mediaId = $this->uploadMedia($imagePath);

        if ($mediaId === null) {
            return ['success' => false, 'provider_ref' => null, 'response' => 'Media upload failed'];
        }

        $response = Http::withToken($this->accessToken)
            ->timeout(20)
            ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => $this->templateName,
                    'language' => ['code' => $language],
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                ['type' => 'image', 'image' => ['id' => $mediaId]],
                            ],
                        ],
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $guestName],
                                ['type' => 'text', 'text' => $eventName],
                                ['type' => 'text', 'text' => $eventDateTime],
                                ['type' => 'text', 'text' => $eventVenue],
                            ],
                        ],
                    ],
                ],
            ]);

        $body = $response->json();

        if ($response->successful() && isset($body['messages'][0]['id'])) {
            return ['success' => true, 'provider_ref' => $body['messages'][0]['id'], 'response' => json_encode($body)];
        }

        Log::warning('WhatsApp template send failed', ['phone' => $phone, 'response' => $body]);
        return ['success' => false, 'provider_ref' => null, 'response' => json_encode($body)];
    }

    private function isConfigured(): bool
    {
        return $this->phoneNumberId !== null && $this->accessToken !== null;
    }

    private function uploadMedia(string $imagePath): ?string
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(30)
            ->attach('file', file_get_contents($imagePath), basename($imagePath))
            ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
                'type' => 'image/jpeg',
            ]);

        $body = $response->json();

        if ($response->successful() && isset($body['id'])) {
            return $body['id'];
        }

        Log::warning('WhatsApp media upload failed', ['path' => $imagePath, 'response' => $body]);
        return null;
    }
}
