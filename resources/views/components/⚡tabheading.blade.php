<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div class="flex flex-row justify-center w-full">
    <h1 class="py-6 font-bold text-2xl">
        @foreach (config('tablist') as $tab)
            {{ request()->routeIs($tab['route']) ? $tab['name'] : '' }}
        @endforeach
    </h1>
</div>
