@php
    $user = auth()->user();

    $navItems = [];

    if ($user->hasRole('Admin')) {
        $navItems = [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
            ['label' => 'Events', 'route' => 'events.index', 'active' => request()->routeIs('events.*')],
            ['label' => 'Guests', 'route' => 'guests.index', 'active' => request()->routeIs('guests.*')],
            ['label' => 'Staff', 'route' => 'staff.index', 'active' => request()->routeIs('staff.*')],
            ['label' => 'Tenant', 'route' => 'tenants.index', 'active' => request()->routeIs('tenants.*')],
            ['label' => 'Delivery', 'route' => 'delivery.index', 'active' => request()->routeIs('delivery.*')],
            ['label' => 'Settings', 'route' => 'settings.index', 'active' => request()->routeIs('settings.*')],
        ];
    } elseif ($user->hasRole('EventManager')) {
        $navItems = [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
            ['label' => 'Events', 'route' => 'events.index', 'active' => request()->routeIs('events.*')],
            ['label' => 'Guests', 'route' => 'guests.index', 'active' => request()->routeIs('guests.*')],
            ['label' => 'Delivery', 'route' => 'delivery.index', 'active' => request()->routeIs('delivery.*')],
        ];
    } elseif ($user->hasRole('DoorStaff')) {
        $navItems = [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
            ['label' => 'Check-In', 'route' => 'checkin.index', 'active' => request()->routeIs('checkin.*'), 'useNavigate' => false],
        ];
    }
@endphp

<!-- Backdrop -->
<div x-show="$store.ui.sidebarOpen" @click="$store.ui.sidebarOpen = false" class="fixed inset-0 bg-gray-900/50 z-30 lg:!hidden" style="display:none"></div>

<aside x-show="$store.ui.sidebarOpen"
       x-transition:enter="transition ease-in-out duration-300 transform"
       x-transition:enter-start="-translate-x-full"
       x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in-out duration-300 transform"
       x-transition:leave-start="translate-x-0"
       x-transition:leave-end="-translate-x-full"
       style="display:none"
       class="fixed inset-y-0 left-0 z-40 w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 min-h-screen lg:!block lg:static lg:z-auto shrink-0">
    <nav class="p-4 space-y-1">
        @foreach($navItems as $item)
            <a href="{{ route($item['route']) }}"
               {{ ($item['useNavigate'] ?? true) ? 'wire:navigate' : '' }}
               @click="$store.ui.sidebarOpen = false"
               class="flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors
                    {{ $item['active']
                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-200'
                        : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</aside>

