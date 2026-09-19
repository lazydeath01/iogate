<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {};
?>

<div class="flex flex-row items-start min-h-screen">
    {{-- sidebar --}}
    <livewire:sidebar />
    <div class="flex flex-col w-full h-screen overflow-auto">
        <livewire:roles-content />
    </div>
</div>
