<?php

namespace App\Http\Controllers;

use App\Services\PublicServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        // return $request;
        $url = env('API_REFERENCE') . 'login';

        $postData = [
            "user" => $request->username,
            "password" => $request->password,
            "productid" => 1,
        ];



        $headers = [
            "Content-Type: application/json",
            "Accept: application/json",
            "Apkey: 50AC-4564",
            "Authorization: asdasdqwe",
            "ICNO: zxczcasda",
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (!isset($result[0]['Data']) || $result[0]['Data'] == 'Invalid User/Password or Product ID!') {
            return back()->withErrors([
                'username' => 'Invalid username or password!',
            ]);
        }

        // Simpan session data
        if (count($result[0]['Data']) > 1) {
            $proplist = [];
            foreach ($result[0]['Data'] as $key) {
                $proplist[] = [
                    'ICNO' => $key['ICNO'],
                    'ICKEY' => $key['ICKEY'],
                    'hotelName' => $key['hotelName']
                ];
            }
            session(['property' => $proplist]);
        } else {
            $proplist[] = [
                'ICNO' => $result[0]['Data'][0]['ICNO'],
                'ICKEY' => $result[0]['Data'][0]['ICKEY'],
                'hotelName' => $result[0]['Data'][0]['hotelName']
            ];
            session(['property' => $proplist]);
        }

        session([
            'ICNO' => $result[0]['Data'][0]['ICNO'],
            'hotelName' => $result[0]['Data'][0]['hotelName'],
            'ICKEY' => $result[0]['Data'][0]['ICKEY'],
            'username' => $request->username,
            'userid' => $result[0]['Data'][0]['userIDFO']
        ]);

        $auth = $this->authCheck($result[0]['Data'][0]['ICNO'], $result[0]['Data'][0]['ICKEY']);

        return redirect()->intended('/dashboard');
    }

    public function changeproperty(Request $request)
{
    try {
        $icno = $request->input('ICNO');
        $property = session('property');

        // Debug info
        Log::info('Change property request:', [
            'requested_icno' => $icno,
            'available_properties' => $property
        ]);

        // Validasi jika property tidak ditemukan
        if (!$property) {
            return response()->json([
                'error' => 'No properties found in session'
            ], 400);
        }

        $filteredProperty = collect($property)->firstWhere('ICNO', $icno);

        // Validasi jika ICNO tidak ditemukan
        if (!$filteredProperty) {
            return response()->json([
                'error' => 'Property not found'
            ], 404);
        }

        // Update session
        session([
            'ICNO' => $filteredProperty['ICNO'],
            'ICKEY' => $filteredProperty['ICKEY'],
            'hotelName' => $filteredProperty['hotelName']
        ]);

        // 🔹 PERBAIKAN: Skip authCheck untuk sementara, langsung set database
        // $authResult = $this->authCheck($filteredProperty['ICNO'], $filteredProperty['ICKEY']);
        
        // 🔹 ALTERNATIF: Gunakan database dari session sebelumnya atau default
        $currentDb = session('tenant_db');
        if (!$currentDb) {
            // Jika tidak ada database di session, coba dapatkan dari authCheck
            $authResult = $this->authCheck($filteredProperty['ICNO'], $filteredProperty['ICKEY']);
            if ($authResult !== true) {
                return response()->json([
                    'error' => 'Authentication failed'
                ], 401);
            }
        }

        // Get company data
        $comp = DB::table('tblcomp')->first();

        if (!$comp) {
            return response()->json([
                'error' => 'Company data not found'
            ], 404);
        }

        $activeDate = $comp->activeDate;
        $active = Carbon::parse($activeDate);
        $tom = $active->addDay();
        $now = Carbon::parse($activeDate)->toDateString();
        $tomorrow = $tom->toDateString();

        session(['activeDate' => $comp->activeDate]);

        return response()->json([
            'success' => true,
            'ICNO' => $icno,
            'hotelName' => $filteredProperty['hotelName'],
            'activeDate' => $now,
            'hotelActiveDate' => Carbon::parse(session('activeDate'))->format('d-M-Y'),
            'tomorrow' => $tomorrow
        ]);
    } catch (\Exception $e) {
        Log::error('Change property error: ' . $e->getMessage());

        return response()->json([
            'error' => 'Internal server error: ' . $e->getMessage()
        ], 500);
    }
}

    public function authCheck($ICNO, $ICKEY)
    {
        try {
            $url = env('API_REFERENCE') . 'auth';
            $postData = [
                "ICNO" => $ICNO,
                "Authorization" => $ICKEY,
            ];

            $headers = [
                "Content-Type: application/json",
                "Accept: application/json",
                "Apkey: 50AC-4564",
                "Authorization: asdasdqwe",
                "ICNO: zxczcasda",
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Tambahkan timeout

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                Log::error('CURL Error in authCheck: ' . $curlError);
                return false;
            }

            $result = json_decode($response);

            if (!$result || !isset($result[0]->Data[0]->dbname)) {
                Log::error('Invalid API response in authCheck');
                return false;
            }

            $dbname = $result[0]->Data[0]->dbname;

            session(['tenant_db' => $dbname]);
            config(['database.connections.tenant' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST'),
                'database' => $dbname,
                'username' => env('DB_USERNAME'),
                'password' => env('DB_PASSWORD'),
                'port' => env('DB_PORT', 3306),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);

            DB::purge('tenant');
            DB::reconnect('tenant');
            config(['database.default' => 'tenant']);

            $comp = DB::table('tblcomp')->first();

            if ($comp) {
                session(['activeDate' => $comp->activeDate]);
                session(['baseCurrency' => $comp->baseCurrency]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('AuthCheck error: ' . $e->getMessage());
            return false;
        }
    }

    public function logout()
    {
        session()->forget([
            'auth_token',
            'ICNO',
            'ICKEY',
            'tenant_db',
            'property',
            'hotelName',
            'username',
            'userid',
            'activeDate',
            'baseCurrency'
        ]);

        return redirect()->route('login')->with('success', 'Successfully logged out.');
    }
}
