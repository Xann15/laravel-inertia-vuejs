<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Carbon;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Format activeDate ke berbagai format
        $activeDate = session('activeDate');

        $formattedDates = [
            'original' => $activeDate,
            'short' => $activeDate ? Carbon::parse($activeDate)->format('d-M-Y') : null,
            'long' => $activeDate ? Carbon::parse($activeDate)->translatedFormat('d F Y') : null,
            'numeric' => $activeDate ? Carbon::parse($activeDate)->format('d/m/Y') : null,
            'system' => $activeDate ? Carbon::parse($activeDate)->format('Y-m-d') : null,
        ];

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user(),
                'username' => session('username'),
                'hotelName' => session('hotelName'),
                'ICNO' => session('ICNO'),
                'property' => session('property'),
                'activeDate' => $formattedDates,
                'baseCurrency' => session('baseCurrency'),
            ],
        ]);
    }
}
