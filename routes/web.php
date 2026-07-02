<?php

use App\Http\Controllers\TargetStatusController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'builder')->name('home');

Route::get('/api/target-status', TargetStatusController::class)->name('target-status');
