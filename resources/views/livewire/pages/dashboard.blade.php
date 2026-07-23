<?php

use function Livewire\Volt\{layout, title};

layout('layouts.app');

title('Dashboard');

?>

<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @role('Admin')
                        <p class="text-lg">Welcome, <strong>{{ auth()->user()->name }}</strong>.</p>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">You have full system access. Use the sidebar to manage tenants, events, guests, templates, and staff.</p>
                    @endrole
                    @role('EventManager')
                        <p class="text-lg">Welcome, <strong>{{ auth()->user()->name }}</strong>.</p>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Manage your assigned events, guests, and delivery status from the sidebar.</p>
                    @endrole
                    @role('DoorStaff')
                        <p class="text-lg">Ready for check-in duty.</p>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Use the Check-In page to scan guest QR codes when the event starts.</p>
                    @endrole
                    @php $noRole = auth()->user()->roles->isEmpty(); @endphp
                    @if($noRole)
                        <p class="text-lg">Welcome, <strong>{{ auth()->user()->name }}</strong>.</p>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Your account has not been assigned a role yet. Please contact your administrator.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
