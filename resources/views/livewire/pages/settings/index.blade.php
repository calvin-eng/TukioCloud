<?php

use App\Models\Event;
use App\Models\Template;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?string $deleteError = null;
    public string $smsTemplate = '';
    public string $smsTemplateName = 'Default Invitation SMS';
    public string $savedSmsTemplateName = '';

    public function mount(): void
    {
        $tenant = Tenant::find(auth()->user()->tenant_id);
        $this->smsTemplate = (string) ($tenant?->sms_template ?: $this->defaultSmsTemplate());
        $this->smsTemplateName = (string) ($tenant?->sms_template_name ?: 'Default Invitation SMS');
        $this->savedSmsTemplateName = $this->smsTemplateName;
    }

    public function render(): mixed
    {
        $charCount = mb_strlen($this->smsTemplate);
        $segments = $charCount > 0 ? (int) ceil($charCount / 160) : 0;

        $preview = str_replace(
            ['{guest_name}', '{event_name}', '{code}', '{link}', '{date}'],
            ['John Doe', 'D & G', 'AB7X2K', 'https://vivaroslimited.live/c/AB7X2K', '25/07/2026'],
            $this->smsTemplate,
        );

        return view('livewire.pages.settings.index', [
            'templates' => Template::orderBy('name')->get(),
            'smsCharCount' => $charCount,
            'smsSegments' => $segments,
            'smsPreview' => $preview,
        ]);
    }

    public function deleteTemplate(int $id): void
    {
        $this->deleteError = null;
        $template = Template::findOrFail($id);

        $assignedEvents = Event::where('template_id', $id)->count();
        if ($assignedEvents > 0) {
            $this->deleteError = "Cannot delete \"{$template->name}\": it is assigned to {$assignedEvents} event(s). Unassign it first.";
            return;
        }

        if ($template->background_path && Storage::disk('public')->exists($template->background_path)) {
            Storage::disk('public')->delete($template->background_path);
        }

        $template->delete();
    }

    public function saveSmsTemplate(): void
    {
        $this->validate([
            'smsTemplateName' => 'required|string|max:255',
            'smsTemplate' => 'required|string|max:2000',
        ]);

        $tenant = Tenant::findOrFail(auth()->user()->tenant_id);
        $tenant->update([
            'sms_template_name' => $this->smsTemplateName,
            'sms_template' => $this->smsTemplate,
        ]);

        $this->savedSmsTemplateName = $this->smsTemplateName;
        session()->flash('smsSaved', 'SMS template saved.');
    }

    private function defaultSmsTemplate(): string
    {
        return 'Ndg/Mr/Mrs {guest_name}, tunakualika {event_name} tarehe {date}. Kadi: {code} (tunza, ni uthibitisho). Bonyeza {link}. - Vivaro Events';
    }
}; ?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Settings</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <a href="#card-templates"
           class="block p-6 bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-md transition-shadow border border-gray-200 dark:border-gray-700">
           <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Card Templates</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
               Manage invitation card designs and choose tenant default behavior.
            </p>
        </a>
        <a href="#sms-template"
           class="block p-6 bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-md transition-shadow border border-gray-200 dark:border-gray-700">
           <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">SMS Template</h3>
           <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
               Customize SMS body text with placeholders and preview before sending.
           </p>
        </a>
    </div>

    <div id="card-templates" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
           <div class="flex items-center justify-between gap-3">
               <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Card Templates</h3>
               <a href="{{ route('settings.templates.calibrate') }}" wire:navigate>
                   <x-primary-button type="button">New Template</x-primary-button>
               </a>
           </div>
        </div>

        @if ($deleteError)
            <div class="mx-6 mt-4 p-3 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg text-sm text-red-700 dark:text-red-300">
                {{ $deleteError }}
            </div>
        @endif

        @if($templates->isNotEmpty())
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($templates as $template)
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $template->name }}</td>
                            <td class="px-6 py-4 text-right text-sm">
                                <a href="{{ route('settings.templates.calibrate', $template->id) }}" wire:navigate class="inline">
                                    <x-secondary-button>Edit</x-secondary-button>
                                </a>
                                <x-danger-button wire:click="deleteTemplate({{ $template->id }})" wire:confirm="Delete template '{{ $template->name }}'? This cannot be undone." class="ml-2">
                                    Delete
                                </x-danger-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                No saved templates yet.
            </div>
        @endif
    </div>

    <div id="sms-template" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">SMS Template</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Placeholders: <code>{guest_name}</code>, <code>{event_name}</code>, <code>{code}</code>, <code>{link}</code>, <code>{date}</code>
            </p>
        </div>

        <div class="p-6 space-y-4">
            @if(session('smsSaved'))
                <div class="px-4 py-3 rounded-md bg-green-50 dark:bg-green-900/50 text-green-700 dark:text-green-300 text-sm">
                    {{ session('smsSaved') }}
                </div>
            @endif

            <div>
                <x-input-label for="smsTemplateName" value="Template Name" />
                <x-text-input
                    id="smsTemplateName"
                    wire:model.live.debounce.250ms="smsTemplateName"
                    class="mt-1 block w-full"
                />
                <x-input-error :messages="$errors->get('smsTemplateName')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="smsTemplate" value="SMS Body" />
                <textarea
                    id="smsTemplate"
                    wire:model.live.debounce.250ms="smsTemplate"
                    rows="5"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                ></textarea>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ $smsCharCount }} characters • {{ $smsSegments }} SMS segment{{ $smsSegments === 1 ? '' : 's' }} (160 chars/segment)
                </p>
                <x-input-error :messages="$errors->get('smsTemplate')" class="mt-2" />
            </div>

            <div class="rounded-md border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Preview</p>
                <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line">{{ $smsPreview }}</p>
            </div>

            <div class="flex justify-end">
                <x-primary-button wire:click="saveSmsTemplate" wire:loading.attr="disabled">Save SMS Template</x-primary-button>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-300">Saved: {{ $savedSmsTemplateName }}</p>
        </div>
    </div>
</div>
