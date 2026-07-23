<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\MessageLog;
use App\Models\Tenant;
use App\Services\BeemService;
use App\Services\CardGenerationService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendInvitationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public array $guestIds = [],
    ) {}

    public function handle(
        CardGenerationService $cards,
        BeemService $beem,
        WhatsAppService $whatsApp,
    ): void {
        $channels = $this->event->delivery_channels ?? ['sms_beem', 'whatsapp_api', 'whatsapp_manual_export'];
        $waExportChannel = in_array('whatsapp_manual_export', $channels, true);
        $whatsAppSendingEnabled = (bool) config('services.whatsapp.sending_enabled', false);

        $guests = $this->guestIds
            ? $this->event->guests()->whereIn('id', $this->guestIds)->get()
            : $this->event->guests;

        foreach ($guests as $guest) {
            // Always send SMS to every guest.
            $this->sendSms($guest, $beem);

            if (! $whatsAppSendingEnabled) {
                continue;
            }

            if ($waExportChannel && $guest->has_whatsapp) {
                try {
                    $cards->generate($guest);
                    $this->logManualExport($guest);
                } catch (\Throwable $e) {
                    Log::error('WhatsApp manual export failed', ['guest_id' => $guest->id, 'error' => $e->getMessage()]);
                }
            }

            // WhatsApp API send is independent of SMS and runs for WhatsApp-capable guests.
            if ($guest->has_whatsapp) {
                try {
                    $cardPath = $cards->generate($guest);
                    $this->sendWhatsApp($guest, $cardPath, $whatsApp);
                } catch (\Throwable $e) {
                    Log::error('WhatsApp API send failed', ['guest_id' => $guest->id, 'error' => $e->getMessage()]);
                    MessageLog::create([
                        'guest_id' => $guest->id,
                        'channel' => 'whatsapp_api',
                        'status' => 'failed',
                        'response' => $e->getMessage(),
                        'sent_at' => now(),
                    ]);
                }
            }
        }
    }

    private function sendSms($guest, BeemService $beem): void
    {
        if (!config('services.beem.sender_approved', false)) {
            MessageLog::create([
                'guest_id' => $guest->id,
                'channel' => 'sms_beem',
                'status' => 'skipped_pending_approval',
                'sent_at' => now(),
            ]);
            return;
        }

        $tenant = Tenant::find($this->event->tenant_id);
        $template = $tenant?->sms_template ?: $this->defaultSmsTemplate();
        $message = $this->renderSmsTemplate($template, $guest);

        try {
            $result = $beem->send($guest->phone, $message);
        } catch (\Throwable $e) {
            Log::error('SMS send failed', ['guest_id' => $guest->id, 'error' => $e->getMessage()]);
            $result = ['success' => false, 'provider_ref' => null, 'response' => $e->getMessage()];
        }

        MessageLog::create([
            'guest_id' => $guest->id,
            'channel' => 'sms_beem',
            'status' => $result['success'] ? 'sent' : 'failed',
            'provider_ref' => $result['provider_ref'],
            'response' => $result['response'],
            'sent_at' => now(),
        ]);
    }

    private function sendWhatsApp($guest, string $cardPath, WhatsAppService $whatsApp): void
    {
        $eventDateTime = $this->event->date
            ? $this->event->date->format('F j, Y')
            : '';

        $result = $whatsApp->sendInvitation(
            phone: $guest->phone,
            imagePath: $cardPath,
            guestName: $guest->name,
            eventName: $this->event->name,
            eventDateTime: $eventDateTime,
            eventVenue: $this->event->venue ?? '',
            language: $this->event->language,
        );

        MessageLog::create([
            'guest_id' => $guest->id,
            'channel' => 'whatsapp_api',
            'status' => $result['success'] ? 'sent' : 'failed',
            'provider_ref' => $result['provider_ref'],
            'response' => $result['response'],
            'sent_at' => now(),
        ]);
    }

    private function logManualExport($guest): void
    {
        MessageLog::create([
            'guest_id' => $guest->id,
            'channel' => 'whatsapp_manual_export',
            'status' => 'exported',
            'sent_at' => now(),
        ]);
    }

    private function defaultSmsTemplate(): string
    {
        return 'Ndg/Mr/Mrs {guest_name}, tunakualika {event_name} tarehe {date}. Kadi: {code} (tunza, ni uthibitisho). Bonyeza {link}. - Vivaro Events';
    }

    private function renderSmsTemplate(string $template, $guest): string
    {
        $eventDate = $this->event->date?->format('d/m/Y') ?? '';
        $shortCode = (string) ($guest->short_code ?: $guest->qr_token);
        $link = 'https://vivaroslimited.live/c/' . rawurlencode($shortCode);

        return str_replace(
            ['{guest_name}', '{event_name}', '{code}', '{link}', '{date}'],
            [
                (string) $guest->name,
                (string) $this->event->name,
                $shortCode,
                $link,
                $eventDate,
            ],
            $template,
        );
    }
}
