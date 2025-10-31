<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ReservationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Semua route web aplikasi kamu didefinisikan di sini. File ini dimuat
| oleh RouteServiceProvider dan semuanya akan di-assign ke "web" middleware group.
|
*/

// Halaman utama (Welcome)
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'      => Route::has('login'),
        'canRegister'   => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'    => PHP_VERSION,
    ]);
});

// Dashboard (hanya untuk user login & verified)
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');
// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

// Grup route yang butuh autentikasi
// Route::middleware(['auth'])->group(function () {
// Profile routes (bawaan Breeze)
Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

// 🔥 CRUD Post routes (pakai resource controller)
Route::resource('posts', PostController::class);




Route::get('/api/reservations/guests', [ReservationController::class, 'getallguest']);
Route::get('/api/reservations/companies', [ReservationController::class, 'getallcompany']);
Route::get('/api/reservations/vip-list', [ReservationController::class, 'getVIPList']);
Route::get('/api/reservations/nationalities', [ReservationController::class, 'getNationality']);
// });

// Auth routes (login, register, forgot password, dsb)
require __DIR__ . '/auth.php';
