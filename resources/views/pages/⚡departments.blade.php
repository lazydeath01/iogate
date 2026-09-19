<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{

};
?>

<div class="flex h-screen flex-row items-start">
    {{-- sidebar --}}
    <livewire:sidebar />
    <div class="flex flex-col w-full h-screen overflow-auto">
        <livewire:departments-content/>
    </div>
</div>
