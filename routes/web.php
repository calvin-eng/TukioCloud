<?php

use App\Http\Controllers\Api\CheckinController;
use App\Http\Controllers\GuestInvitationController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('/c/{short_code}', [GuestInvitationController::class, 'show'])->name('guest.invitation');

Volt::route('dashboard', 'pages.dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Volt::route('profile', 'pages.profile')
    ->middleware(['auth'])
    ->name('profile');

// Admin only
Route::middleware(['auth', 'verified', 'role:Admin'])->group(function () {
    Route::get('staff', \App\Livewire\Staff\StaffIndex::class)->name('staff.index');
    Volt::route('tenants', 'pages.tenants.index')->name('tenants.index');
    Route::permanentRedirect('templates', '/settings');
    Volt::route('settings/templates/calibrate/{templateId?}', 'pages.templates.calibrate')->name('settings.templates.calibrate');
    Volt::route('settings', 'pages.settings.index')->name('settings.index');

    Route::get('calibrate-bg/{filename}', function (string $filename) {
        $path = 'templates/' . basename($filename);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('public')->exists($path), 404);
        return response()->file(\Illuminate\Support\Facades\Storage::disk('public')->path($path));
    })->name('calibrate.bg');

    Route::get('calibrate-preview', function () {
        $template = new \App\Models\Template;
        $template->name_x = (int) session('preview_name_x', 540);
        $template->name_y = (int) session('preview_name_y', 600);
        $template->qr_x = (int) session('preview_qr_x', 390);
        $template->qr_y = (int) session('preview_qr_y', 950);
        $template->qr_size = (int) session('preview_qr_size', 300);
        $template->name_font_color = session('preview_name_font_color', '#1a1a1a');
        $template->name_font_size = (int) session('preview_name_font_size', 48);
        $template->name_font_path = session('preview_name_font_path', resource_path('fonts/NotoSans-Regular.ttf'));
        $template->output_width = (int) session('preview_output_width', 1080);
        $template->output_height = (int) session('preview_output_height', 1350);
        $template->output_quality = (int) session('preview_output_quality', 90);

        $bg = session('calibrate_bg');
        if ($bg) {
            $template->background_path = $bg;
        }

        try {
            $dataUri = app(\App\Services\CardGenerationService::class)->preview($template);
            $base64 = explode(',', $dataUri, 2)[1] ?? '';
            $binary = base64_decode($base64);
            if (!$binary) abort(500);
            return response($binary, 200)->header('Content-Type', 'image/jpeg');
        } catch (\Exception $e) {
            abort(404);
        }
    })->name('calibrate.preview');
});

// Admin + EventManager
Route::middleware(['auth', 'verified', 'role:Admin|EventManager'])->group(function () {
    Volt::route('events', 'pages.events.index')->name('events.index');
    Volt::route('guests', 'pages.guests.index')->name('guests.index');
    Volt::route('delivery', 'pages.delivery.index')->name('delivery.index');
});

// DoorStaff
Route::middleware(['auth', 'verified', 'role:DoorStaff'])->group(function () {
    Volt::route('check-in', 'pages.checkin.index')->name('checkin.index');
    Route::post('api/checkin', [CheckinController::class, 'store'])->name('api.checkin');
});

require __DIR__.'/auth.php';
