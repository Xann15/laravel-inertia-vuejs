<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Semua route web aplikasi kamu didefinisikan di sini. File ini dimuat
| oleh RouteServiceProvider dan semuanya akan di-assign ke "web" middleware group.
|
*/

// Halaman utama (Welcome) - Public
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'      => Route::has('login'),
        'canRegister'   => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'    => PHP_VERSION,
    ]);
});

// Auth Routes - Hanya untuk guest (belum login)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected Routes - Hanya untuk user yang sudah login
Route::middleware('auth.check')->group(function () {
    
    // Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Post routes
    Route::resource('posts', PostController::class);

    // Reservation API routes
    Route::get('/api/reservations/guests', [ReservationController::class, 'getallguest']);
    Route::get('/api/reservations/companies', [ReservationController::class, 'getallcompany']);
    Route::get('/api/reservations/vip-list', [ReservationController::class, 'getVIPList']);
    Route::get('/api/reservations/nationalities', [ReservationController::class, 'getNationality']);

    // Property change route
    Route::post('/change-property', [AuthController::class, 'changeproperty'])->name('change.property');

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Test DB Connection - Public (untuk testing saja)
Route::get('/test-db', function () {
    try {
        $connection = DB::connection()->getPdo();
        return "✅ Database connected: " . DB::connection()->getDatabaseName();
    } catch (\Exception $e) {
        return "❌ Connection failed: " . $e->getMessage();
    }
});

// Debug Session - Public (untuk development saja)
Route::get('/debug-session', function () {
    dd(session()->all());
});