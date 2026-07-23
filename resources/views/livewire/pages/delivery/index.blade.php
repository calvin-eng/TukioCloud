<?php

use App\Jobs\SendInvitationJob;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Template;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $eventId = null;
    public ?int $templateId = null;

    public bool $sending = false;

    public function render(): mixed
    {
        $events = Event::where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('name')
            ->get();

        $guests = collect();
        $selectedEvent = null;
        $effectiveTemplateName = null;

        if ($this->eventId) {
            $selectedEvent = Event::where('tenant_id', auth()->user()->tenant_id)
                ->with(['template', 'tenant.defaultTemplate'])
                ->find($this->eventId);

            $guests = Guest::where('tenant_id', auth()->user()->tenant_id)
                ->where('event_id', $this->eventId)
                ->with('messageLogs')
                ->orderBy('name')
                ->get();

            if ($selectedEvent) {
                $effectiveTemplateName = $selectedEvent->template?->name
                    ?? $selectedEvent->tenant?->defaultTemplate?->name;
            }
        }

        return view('livewire.pages.delivery.index', [
            'events' => $events,
            'guests' => $guests,
            'templates' => Template::orderBy('name')->get(),
            'selectedEvent' => $selectedEvent,
            'effectiveTemplateName' => $effectiveTemplateName,
        ]);
    }

    public function updatedEventId($eventId): void
    {
        $this->templateId = null;

        if (! $eventId) {
            return;
        }

        $event = Event::where('tenant_id', auth()->user()->tenant_id)->find($eventId);
        $this->templateId = $event?->template_id;
    }

    public function sendAll(): void
    {
        $event = $this->resolveEventForDispatch();
        SendInvitationJob::dispatch($event);

        $this->sending = true;

        session()->flash('sent', 'Invitations queued for all guests. Refresh to see results.');
    }

    public function retryGuest(int $guestId): void
    {
        $event = $this->resolveEventForDispatch();
        SendInvitationJob::dispatch($event, [$guestId]);

        session()->flash('sent', 'Retry queued for this guest. Refresh to see results.');
    }

    public function retryFailed(): void
    {
        $event = $this->resolveEventForDispatch();

        $guestIds = Guest::where('tenant_id', auth()->user()->tenant_id)
            ->where('event_id', $this->eventId)
            ->where(function ($q) {
                $q->whereHas('messageLogs', function ($logs) {
                    $logs->where('channel', 'sms_beem')
                        ->whereIn('status', ['failed', 'skipped_pending_approval']);
                })->orWhere(function ($waGuests) {
                    $waGuests->where('has_whatsapp', true)
                        ->whereHas('messageLogs', function ($logs) {
                            $logs->where('channel', 'whatsapp_api')
                                ->where('status', 'failed');
                        });
                });
            })
            ->pluck('id')
            ->toArray();

        if (empty($guestIds)) {
            session()->flash('info', 'No failed or skipped guests to retry.');
            return;
        }

        SendInvitationJob::dispatch($event, $guestIds);

        session()->flash('sent', 'Retry queued for ' . count($guestIds) . ' guest(s). Refresh to see results.');
    }

    public function latestLog($logs, string $channel): ?\App\Models\MessageLog
    {
        return $logs->where('channel', $channel)->sortByDesc('sent_at')->first();
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'sent', 'exported' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
            'skipped_pending_approval' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    public function shouldShowRetry(?string $status): bool
    {
        return in_array($status, ['failed', 'skipped_pending_approval'], true);
    }

    private function resolveEventForDispatch(): Event
    {
        $this->validate([
            'eventId' => 'required|exists:events,id',
            'templateId' => 'nullable|exists:templates,id',
        ]);

        $event = Event::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->eventId);

        $event->update([
            'template_id' => $this->templateId ?: null,
        ]);

        return $event->fresh(['template', 'tenant.defaultTemplate']);
    }
}; ?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Delivery Status</h2>
    </div>

    @if(session('sent'))
        <div class="mb-4 px-4 py-3 rounded-md bg-green-50 dark:bg-green-900/50 text-green-700 dark:text-green-300 text-sm">
            {{ session('sent') }}
        </div>
    @endif

    @if(session('info'))
        <div class="mb-4 px-4 py-3 rounded-md bg-blue-50 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 text-sm">
            {{ session('info') }}
        </div>
    @endif

    <div class="mb-4 flex items-center gap-3">
        <select wire:model.live="eventId"
            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            <option value="">Select Event</option>
            @foreach($events as $event)
                <option value="{{ $event->id }}">{{ $event->name }}</option>
            @endforeach
        </select>

        @if($eventId)
            <select wire:model="templateId"
                class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">Tenant Default Template</option>
                @foreach($templates as $template)
                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </select>
            <x-primary-button wire:click="sendAll" wire:loading.attr="disabled">
                Send Invitations
            </x-primary-button>
            <x-secondary-button wire:click="retryFailed" wire:loading.attr="disabled">
                Retry Failed
            </x-secondary-button>
        @endif
    </div>

    @if($eventId && $selectedEvent)
        <div class="mb-4 text-sm text-gray-600 dark:text-gray-300">
            Using card template:
            <span class="font-medium">{{ $effectiveTemplateName ?? 'No template set' }}</span>
            @if(!$selectedEvent->template && $selectedEvent->tenant?->defaultTemplate)
                <span class="text-xs text-gray-500 dark:text-gray-400">(tenant default fallback)</span>
            @endif
        </div>
    @endif

    @if($guests->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Guest</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Phone</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">SMS Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">WhatsApp Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @php
                        $component = $this;
                    @endphp
                    @foreach($guests as $guest)
                        @php
                            $smsLog = $component->latestLog($guest->messageLogs, 'sms_beem');
                            $waApiLog = $component->latestLog($guest->messageLogs, 'whatsapp_api');
                            $waStatus = $guest->has_whatsapp ? $waApiLog?->status : null;
                            $smsStatus = $smsLog?->status;
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $guest->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $guest->phone }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($smsStatus)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $component->statusBadgeClass($smsStatus) }}">
                                        {{ str_replace('_', ' ', $smsStatus) }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if(!$guest->has_whatsapp)
                                    <span class="text-gray-400 text-xs">N/A</span>
                                @elseif($waStatus)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $component->statusBadgeClass($waStatus) }}">
                                        {{ $waStatus }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                @if($component->shouldShowRetry($smsStatus) || $component->shouldShowRetry($waStatus))
                                    <x-secondary-button
                                        wire:click="retryGuest({{ $guest->id }})"
                                        wire:loading.attr="disabled"
                                        class="text-xs"
                                    >Retry</x-secondary-button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif($eventId)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center text-sm text-gray-500 dark:text-gray-400">
            No guests for this event.
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center text-sm text-gray-500 dark:text-gray-400">
            Select an event to view delivery status.
        </div>
    @endif

    <div wire:loading class="fixed bottom-4 right-4 px-4 py-2 bg-indigo-600 text-white text-sm rounded-md shadow-lg">
        Processing...
    </div>
</div>
