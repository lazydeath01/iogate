<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public array $tablist = [
        [
            'id' => 1,
            'name' => 'Tổng quan',
            'route' => 'overview'
        ],
        [
            'id' => 2,
            'name' => 'Đơn vị',
            'route' => 'departments'
        ]
    ];

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
    {{-- memnu --}}
    <div class="flex items-stretch"> 
        @foreach ($tablist as $tab)
            <a href="{{ route($tab['route']) }}" class="px-4 py-2 border-b-2 {{ request()->routeIs($tab['route']) ? 'border-blue-500 text-blue-500' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $tab['name'] }}
            </a>
        @endforeach
    </div>
    {{-- Simplicity is an acquired taste. - Katharine Gerould --}}
    <h1>
        Welcome <span class="font-bold">{{ Auth::user()->username }}</span>
    </h1>
    <button wire:click="logout" class="border-black border font-bold py-2 px-4 rounded  hover:bg-gray-600 hover:text-white transition-colors duration-300 ease-in-out">
        LOGOUT
    </button>
</div>