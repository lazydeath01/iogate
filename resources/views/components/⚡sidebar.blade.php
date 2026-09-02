<?php

use Livewire\Component;

new class extends Component {

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
    <div class="flex flex-col items-stretch h-screen min-w-50 w-[15vw] bg-gray-200">
        <div class = "flex flex-col items-center pt-6 pb-6">
            <h1 class="pb-3">
                Welcome <span class="font-bold">{{ Auth::user()->username }}</span>
            </h1>
            <button wire:click="logout"
                class="border-black border font-bold py-1 px-1   hover:bg-gray-600 hover:text-white transition-colors duration-300 ease-in-out">
                LOGOUT
            </button>
        </div>
        @foreach (config('tablist') as $tab)
            <a href="{{ route($tab['route']) }}"
                class="font-semibold px-4 py-2 border-b-2 {{ request()->routeIs($tab['route']) ? 'border-blue-500 text-blue-500' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $tab['name'] }}
            </a>
        @endforeach
    </div>
    {{-- I begin to speak only when I am certain what I will say is not better left unsaid. - Cato the Younger --}}
</div>
