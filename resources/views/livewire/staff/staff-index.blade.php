<div>
    @if($createdPassword)
        <div class="mb-6 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4" wire:key="password-banner">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                        Staff member <strong>{{ $createdUserName }}</strong> created.
                    </p>
                    <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                        Temporary password: <code class="bg-yellow-100 dark:bg-yellow-800 px-2 py-0.5 rounded font-mono font-bold select-all">{{ $createdPassword }}</code>
                    </p>
                    <p class="mt-1 text-xs text-yellow-600 dark:text-yellow-400">
                        Share this password securely with {{ $createdUserName }}.
                        <em>Replace this flow with an email-based invite once mail transport is configured.</em>
                    </p>
                </div>
                <button wire:click="dismissCreatedPassword" class="shrink-0 ms-4 text-yellow-500 hover:text-yellow-700 dark:hover:text-yellow-300">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>
    @endif

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Staff</h2>
        <x-primary-button wire:click="openCreateForm">Invite Staff</x-primary-button>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Role</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($staff as $user)
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $user->name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                @if($user->roles->first()?->name === 'EventManager')
                                    bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                @elseif($user->roles->first()?->name === 'DoorStaff')
                                    bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                @else
                                    bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                @endif
                            ">{{ $user->roles->first()?->name ?? 'No Role' }}</span>
                        </td>
                        <td class="px-6 py-4 text-right text-sm space-x-2">
                            <x-secondary-button wire:click="edit({{ $user->id }})">Edit</x-secondary-button>
                            <x-danger-button
                                wire:click="delete({{ $user->id }})"
                                wire:confirm="Remove this staff member from the tenant?"
                            >Remove</x-danger-button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            No staff members yet. Click "Invite Staff" to add one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" wire:key="staff-form-modal">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" wire:click="$set('showForm', false)"></div>
            <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden sm:mx-auto sm:max-w-lg relative z-10">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        {{ $editingUserId ? 'Edit Staff' : 'Invite Staff' }}
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" class="block mt-1 w-full" wire:model="name" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" type="email" class="block mt-1 w-full" wire:model="email" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="role" value="Role" />
                            <select id="role"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                wire:model="selectedRole">
                                <option value="">Select Role</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->name }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('selectedRole')" class="mt-2" />
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
