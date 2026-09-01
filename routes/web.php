<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/','pages::home')->middleware('auth')->name('home');
// get('/', function () {
//     return view('welcome');
// });

Route::livewire('/post/create','pages::post.create');

Route::livewire('/login','pages::login')->middleware('guest')->name('login');
