<?php

use App\Http\Controllers\RobotsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Собираем robots.txt из БД: заменили статический public/robots.txt (test.md п. 19).
Route::get('/robots.txt', [RobotsController::class, 'show']);
