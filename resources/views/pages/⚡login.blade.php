<?php

use Livewire\Component;

new class extends Component
{
    public $username = '';
    public $password = '';

    public function login()
    {
        $this->validate([
            'username' => 'required|max:255',
            'password' => 'required',
        ]);

        dd($this->username, $this->password);
    }
    //
};
?>

<div>
    {{-- You must be the change you wish to see in the world. - Mahatma Gandhi --}}
    <h1 class="text-4xl">Login</h1>
    <form wire:submit.prevent="login">
        <label class="block">
            Username:
        <input class="border border-black" type="username" placeholder="Username" wire:model="username">
        </label>
        <label class="block">
            Password:
            <input class="border border-black" type="password" placeholder="Password" wire:model="password">
        </label>
        <button class="border-black border font-bold py-2 px-4 rounded" type="submit">Login</button>
    </form>
</div>
