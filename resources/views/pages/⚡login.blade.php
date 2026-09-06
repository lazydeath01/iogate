<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $username = '';
    public $password = '';
    public $remember = false;

    public function login()
    {
        $this->validate([
            'username' => 'required|max:255',
            'password' => 'required',
        ]);

        if (
            !Auth::attempt([
                'username' => $this->username,
                'password' => $this->password,
            ], $this->remember)
        ) {
            $this->addError('invalid', 'Username hoặc mật khẩu không đúng.');
            return;
        }
        session()->regenerate();
        return redirect('/');
        // dd($this->username, $this->password);
    }
    //
};
?>

<div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4 py-10">
    <div class="absolute -left-32 -top-32 h-80 w-80 rounded-full bg-indigo-500/20 blur-3xl"></div>
    <div class="absolute -bottom-40 -right-20 h-96 w-96 rounded-full bg-cyan-400/10 blur-3xl"></div>

    <div id="panel" class="relative w-full max-w-md rounded-2xl border border-white/10 bg-white px-6 py-8 shadow-2xl shadow-black/30 sm:px-10 sm:py-10">
        <div class="mb-8">
            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.24em] text-indigo-600">Secure access</p>
            <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Welcome back</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">Sign in to continue to your workspace.</p>
        </div>

        <form class="space-y-5" wire:submit.prevent="login">
            <label class="block text-sm font-medium text-slate-700">
                Username
                <input class="mt-2 block w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10" type="text" placeholder="Enter your username" wire:model="username" autocomplete="username">
                @error('username')
                <span class="mt-1.5 block text-sm text-red-600">{{ $message }}</span>
                @enderror
            </label>

            <label class="block text-sm font-medium text-slate-700">
                Password
                <input class="mt-2 block w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10" type="password" placeholder="Enter your password" wire:model="password" autocomplete="current-password">
                @error('password')
                <span class="mt-1.5 block text-sm text-red-600">{{ $message }}</span>
                @enderror
            </label>

            @error('invalid')
            <p class="rounded-lg border border-red-100 bg-red-50 px-3 py-2.5 text-sm text-red-700">{{ $message }}</p>
            @enderror

            <label class="flex items-center gap-2.5 text-sm text-slate-600">
                <input class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" type="checkbox" wire:model="remember">
                Remember me
            </label>

            <button class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-500/20 active:translate-y-px" type="submit">Sign in</button>
        </form>
    </div>
</div>
