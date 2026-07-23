<?php

namespace App\Livewire\Staff;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Staff Management')]
class StaffIndex extends Component
{
    public bool $showForm = false;

    public ?int $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $selectedRole = '';

    /** Temporary password shown after invite — remove once mail transport is configured. */
    public string $createdPassword = '';

    /** Name of the user just invited — for the confirmation banner. */
    public string $createdUserName = '';

    public function render()
    {
        return view('livewire.staff.staff-index', [
            'staff' => User::where('tenant_id', auth()->user()->tenant_id)
                ->where('id', '!=', auth()->id())
                ->with('roles')
                ->get(),
            'roles' => Role::whereIn('name', ['EventManager', 'DoorStaff'])->get(),
        ]);
    }

    public function openCreateForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->editingUserId = null;
    }

    public function edit(int $id): void
    {
        $user = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->selectedRole = $user->roles->first()?->name ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . ($this->editingUserId ?? 'NULL'),
            'selectedRole' => 'required|in:EventManager,DoorStaff',
        ]);

        if ($this->editingUserId) {
            $user = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->editingUserId);
            $user->update([
                'name' => $this->name,
                'email' => $this->email,
            ]);
            $user->syncRoles([$this->selectedRole]);
        } else {
            // Temporary flow: password is generated and shown once to the inviter.
            // Replace with email-based invite link once mail transport is configured.
            $plaintext = Str::password(16);
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($plaintext),
                'tenant_id' => auth()->user()->tenant_id,
            ]);
            $user->assignRole($this->selectedRole);

            $this->createdPassword = $plaintext;
            $this->createdUserName = $this->name;
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $user = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);
        $user->delete();
    }

    public function dismissCreatedPassword(): void
    {
        $this->createdPassword = '';
        $this->createdUserName = '';
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->email = '';
        $this->selectedRole = '';
        $this->editingUserId = null;
    }
}
