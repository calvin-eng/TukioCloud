<?php

use App\Models\Event;
use App\Models\Template;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingEventId = null;

    public string $name = '';

    public string $date = '';

    public string $venue = '';

    public string $language = 'sw';

    /** @var list<string> */
    public array $deliveryChannels = [];

    public ?int $templateId = null;

    public function render(): mixed
    {
        return view('livewire.pages.events.index', [
            'events' => Event::where('tenant_id', auth()->user()->tenant_id)
                ->withCount('guests')
                ->orderByDesc('created_at')
                ->get(),
            'templates' => Template::orderBy('name')->get(),
        ]);
    }

    public function openCreateForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->editingEventId = null;
    }

    public function edit($id): void
    {
        $event = Event::where('tenant_id', auth()->user()->tenant_id)->findOrFail((int) $id);
        $this->editingEventId = $event->id;
        $this->name = $event->name;
        $this->date = $event->date ? $event->date->format('Y-m-d') : '';
        $this->venue = $event->venue ?? '';
        $this->language = $event->language;
        $this->deliveryChannels = $event->delivery_channels ?? [];
        $this->templateId = $event->template_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'date' => 'nullable|date',
            'venue' => 'nullable|string|max:255',
            'language' => 'required|in:sw,en',
            'deliveryChannels' => 'nullable|array',
            'deliveryChannels.*' => 'string|in:sms_beem,whatsapp_api,whatsapp_manual_export',
            'templateId' => 'nullable|exists:templates,id',
        ]);

        $data = [
            'name' => $this->name,
            'date' => $this->date ?: null,
            'venue' => $this->venue ?: null,
            'language' => $this->language,
            'delivery_channels' => $this->deliveryChannels,
            'template_id' => $this->templateId ?: null,
        ];

        if ($this->editingEventId) {
            $event = Event::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->editingEventId);
            $event->update($data);
        } else {
            $data['tenant_id'] = auth()->user()->tenant_id;
            Event::create($data);
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function delete($id): void
    {
        $event = Event::where('tenant_id', auth()->user()->tenant_id)->findOrFail((int) $id);
        $event->delete();
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->date = '';
        $this->venue = '';
        $this->language = 'sw';
        $this->deliveryChannels = [];
        $this->templateId = null;
        $this->editingEventId = null;
    }
}; ?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Events</h2>
        <x-primary-button wire:click="openCreateForm">Create Event</x-primary-button>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Venue</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Language</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Guests</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($events as $event)
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $event->name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $event->date ? $event->date->format('M j, Y') : '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $event->venue ?: '—' }}</td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-2 py-1 text-xs font-medium rounded-full {{ $event->language === 'sw' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' }}">
                                {{ strtoupper($event->language) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-center">
                            <a href="{{ route('guests.index', ['event_id' => $event->id]) }}" wire:navigate
                               class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                {{ $event->guests_count }}
                            </a>
                        </td>
                        <td class="px-6 py-4 text-right text-sm space-x-2">
                            <x-secondary-button wire:click="edit({{ $event->id }})">Edit</x-secondary-button>
                            <x-danger-button
                                wire:click="delete({{ $event->id }})"
                                wire:confirm="Delete this event and all its guests?"
                            >Delete</x-danger-button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            No events yet. Click "Create Event" to add one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" wire:key="event-form-modal">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" wire:click="$set('showForm', false)"></div>
            <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden sm:mx-auto sm:max-w-lg relative z-10">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        {{ $editingEventId ? 'Edit Event' : 'Create Event' }}
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <x-input-label for="name" value="Event Name" />
                            <x-text-input id="name" class="block mt-1 w-full" wire:model="name" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="date" value="Date" />
                            <x-text-input id="date" type="date" class="block mt-1 w-full" wire:model="date" />
                            <x-input-error :messages="$errors->get('date')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="venue" value="Venue" />
                            <x-text-input id="venue" class="block mt-1 w-full" wire:model="venue" />
                            <x-input-error :messages="$errors->get('venue')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="language" value="Language" />
                            <select id="language"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                wire:model="language">
                                <option value="sw">Swahili</option>
                                <option value="en">English</option>
                            </select>
                            <x-input-error :messages="$errors->get('language')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="template" value="Card Template" />
                            <select id="template"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                wire:model="templateId">
                                <option value="">Tenant Default</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('templateId')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label value="Delivery Channels" />
                            <div class="mt-2 space-y-2">
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" value="sms_beem" wire:model="deliveryChannels"
                                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    SMS (Beem)
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" value="whatsapp_api" wire:model="deliveryChannels"
                                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    WhatsApp API
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" value="whatsapp_manual_export" wire:model="deliveryChannels"
                                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    WhatsApp Manual Export
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('deliveryChannels')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <x-secondary-button type="button" wire:click="$set('showForm', false)">Cancel</x-secondary-button>
                        <x-primary-button wire:click="save">Save</x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
