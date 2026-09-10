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

<div class="shrink-0">
    <aside class="flex min-h-screen w-64 flex-col bg-slate-950 text-slate-300 shadow-xl" aria-label="Sidebar">
        <div class="border-b border-white/10 px-5 py-6">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-cyan-400 font-black tracking-tight text-slate-950">
                    IG
                </div>
                <div>
                    <p class="text-sm font-bold tracking-[0.2em] text-white">IOGATE</p>
                    <p class="mt-1 text-xs text-slate-500">QUẢN TRỊ HỆ THỐNG</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 py-6" aria-label="Main navigation">
            {{-- <p class="px-3 pb-3 text-[11px] font-bold tracking-[0.18em] text-slate-500">ĐIỀU HƯỚNG</p> --}}
            <div class="flex flex-col gap-1">
                @foreach (config('tablist') as $tab)
                    @php($isActive = request()->routeIs($tab['route']))
                    <a href="{{ route($tab['route']) }}"
                        @class([
                            'group flex items-center gap-3 px-3 py-3 text-sm font-semibold transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-950',
                            'bg-cyan-400 text-slate-950 shadow-lg shadow-cyan-950/30' => $isActive,
                            'text-slate-400 hover:bg-white/10 hover:text-white' => ! $isActive,
                        ])>
                        <span>{{ $tab['name'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>

        <div class="border-t border-white/10 p-4">
            <div class="mb-3 flex items-center gap-3 rounded-lg bg-white/5 px-3 py-3">
                {{-- <div class="flex size-9 items-center justify-center rounded-full bg-slate-700 text-sm font-bold text-cyan-300">
                    {{ strtoupper(substr(Auth::user()->username, 0, 1)) }}
                </div> --}}
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-white">{{ Auth::user()->username }}</p>
                    <p class="text-xs text-slate-500">Đang hoạt động</p>
                </div>
            </div>
            <button wire:click="logout"
                class="flex w-full items-center justify-center rounded-lg border border-white/10 px-3 py-2.5 text-sm font-bold text-slate-400 transition-colors duration-200 hover:border-red-400/40 hover:bg-red-400/10 hover:text-red-300 focus:outline-none focus:ring-2 focus:ring-red-400">
                Đăng xuất
            </button>
        </div>
    </aside>
</div>
