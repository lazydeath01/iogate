<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    
};
?>

<div class="flex flex-row items-start min-h-screen overflow-auto">
    {{-- sidebar --}}
    <livewire:sidebar />
    <div class="flex flex-col w-full h-screen overflow-auto">
        <livewire:users-content />
    </div>
</div>