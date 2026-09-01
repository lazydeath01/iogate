<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/overview')->middleware('auth')->name('home');

// Route::livewire('/','pages::home')->middleware('auth')->name('home');

Route::livewire('/post/create','pages::post.create');

Route::livewire('/login','pages::login')->middleware('guest')->name('login');

Route::livewire('/overview','pages::overview')->middleware('auth')->name('overview');

Route::livewire('/departments','pages::departments')->middleware('auth')->name('departments');

Route::livewire('/roles','pages::roles')->middleware('auth')->name('roles');

Route::livewire('/permissions','pages::permissions')->middleware('auth')->name('permissions');

Route::livewire('/users','pages::users')->middleware('auth')->name('users');

Route::livewire('/vehicles','pages::vehicles')->middleware('auth')->name('vehicles');

Route::livewire('/gates','pages::gates')->middleware('auth')->name('gates');