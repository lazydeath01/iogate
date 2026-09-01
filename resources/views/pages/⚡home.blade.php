<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public function logout()
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect('login');
    }
};
?>

<div>
    {{-- Simplicity is an acquired taste. - Katharine Gerould --}}
    <h1>
        Welcome <span class="font-bold">{{ Auth::user()->username }}</span>
    </h1>
    <button wire:click="logout" class="border-black border font-bold py-2 px-4 rounded  hover:bg-gray-600 hover:text-white transition-colors duration-300 ease-in-out">
        LOGOUT
    </button>
</div>