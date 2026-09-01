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

<div class="flex items-center justify-center h-screen">
    {{-- You must be the change you wish to see in the world. - Mahatma Gandhi --}}
    <div>

        <h1 class="text-4xl">Login</h1>
        <form wire:submit.prevent="login">
            <label class="block">
                Username:
                <input class="block border border-black" type="username" placeholder="Username" wire:model="username">
                @error('username')
                <span class="text-red-600">{{ $message }}</span>
                @enderror
            @error('invalid')
            <span class="text-red-600">{{ $message }}</span>
            @enderror
        </label>
        <label class="block">
            Password:
            <input class="block border border-black" type="password" placeholder="Password" wire:model="password">
            @error('password')
            <span class="text-red-600">{{ $message }}</span>
            @enderror
            @error('invalid')
            <span class="text-red-600">{{ $message }}</span>
            @enderror
        </label>

        <label>
            <input type="checkbox" wire:model="remember">
            Remember me
        </label>

        <button class="border-black border font-bold py-2 px-4 rounded" type="submit">Login</button>
    </form>
</div>
</div>
