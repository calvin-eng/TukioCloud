<?php

use App\Models\Checkin;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'Admin']);
    Role::create(['name' => 'EventManager']);
    Role::create(['name' => 'DoorStaff']);

    $this->tenant = Tenant::create(['name' => 'Test Venue']);
    $this->event = Event::create(['tenant_id' => $this->tenant->id, 'name' => 'Test Event']);
    $this->guest = Guest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'event_id' => $this->event->id,
    ]);
});

test('POST /api/checkin with a valid guest token creates a checkin record', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole('DoorStaff');

    $response = $this
        ->actingAs($user)
        ->withSession(['tenant_id' => $this->tenant->id])
        ->postJson('/api/checkin', [
            'guest_token' => $this->guest->qr_token,
        ]);

    $response->assertStatus(201);
    $response->assertJson([
        'message' => 'Checked in successfully',
        'guest' => ['id' => $this->guest->id, 'name' => $this->guest->name],
    ]);
    $this->assertDatabaseHas('checkins', [
        'tenant_id' => $this->tenant->id,
        'event_id' => $this->event->id,
        'guest_id' => $this->guest->id,
    ]);
});

test('second POST with the same guest token returns already checked in without duplicate row', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole('DoorStaff');

    $this
        ->actingAs($user)
        ->withSession(['tenant_id' => $this->tenant->id])
        ->postJson('/api/checkin', [
            'guest_token' => $this->guest->qr_token,
        ]);

    $response = $this
        ->actingAs($user)
        ->withSession(['tenant_id' => $this->tenant->id])
        ->postJson('/api/checkin', [
            'guest_token' => $this->guest->qr_token,
        ]);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'Already checked in',
    ]);
    $this->assertDatabaseCount('checkins', 1);
});

test('DoorStaff can access /check-in and EventManager is redirected to dashboard', function () {
    $doorStaff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $doorStaff->assignRole('DoorStaff');

    $eventManager = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $eventManager->assignRole('EventManager');

    $this
        ->actingAs($doorStaff)
        ->get('/check-in')
        ->assertOk();

    $this
        ->actingAs($eventManager)
        ->get('/check-in')
        ->assertRedirectToRoute('dashboard');
});
