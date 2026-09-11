<?php

use App\Livewire\Checkout;
use App\Livewire\Resultado;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/checkout', Checkout::class);
Route::get('/pedido-criado/{order}', Resultado::class)
    ->middleware(['signed'])
    ->name('checkout.result');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
