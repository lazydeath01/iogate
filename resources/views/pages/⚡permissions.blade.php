<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {};
?>

<div class="flex h-screen flex-row items-start overflow-hidden">
    {{-- sidebar --}}
    <livewire:sidebar />
    <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
        <livewire:permissions-content />
    </div>
</div>
