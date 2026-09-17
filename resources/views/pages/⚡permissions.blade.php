<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {};
?>

<div class="flex h-screen flex-row items-start overflow-hidden">
    {{-- sidebar --}}
    <livewire:sidebar />
    <div class="flex min-w-0 flex-1 flex-col h-screen overflow-y-auto">
        <livewire:permissions-content />
    </div>
</div>
