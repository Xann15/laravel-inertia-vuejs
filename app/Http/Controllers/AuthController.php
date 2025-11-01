<?php

namespace App\Http\Controllers;

use App\Services\PublicServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

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
        $icno = $request->input('ICNO');
        $property = session('property');
        $filteredProperty = collect($property)->firstWhere('ICNO', $icno);
        
        session([
            'ICNO' => $filteredProperty['ICNO'],
            'ICKEY' => $filteredProperty['ICKEY'],
            'hotelName' => $filteredProperty['hotelName']
        ]);

        $auth = $this->authCheck($filteredProperty['ICNO'], $filteredProperty['ICKEY']);
        $comp = DB::table('tblcomp')->first();
        $activeDate = $comp->activeDate;
        $active = Carbon::parse($activeDate);
        $tom = $active->addDay();
        $now = Carbon::parse($activeDate)->toDateString();
        $tomorrow = $tom->toDateString();

        session(['activeDate' => $comp->activeDate]);

        return response()->json([
            'ICNO' => $icno,
            'hotelName' => $filteredProperty['hotelName'],
            'activeDate' => $now,
            'hotelActiveDate' => Carbon::parse(session('activeDate'))->format('d-M-Y'),
            'tomorrow' => $tomorrow
        ]);
    }

    public function authCheck($ICNO, $ICKEY)
    {
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

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $result = json_decode($response);
        $dbname = $result[0]->Data[0]->dbname ?? null;

        if (!$dbname) {
            return back()->withErrors(['msg' => 'Database not found!']);
        }

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
        session(['activeDate' => $comp->activeDate]);
        session(['baseCurrency' => $comp->baseCurrency]);

        return true;
    }

    public function logout()
    {
        session()->forget([
            'auth_token', 'ICNO', 'ICKEY', 'tenant_db', 'property', 
            'hotelName', 'username', 'userid', 'activeDate', 'baseCurrency'
        ]);

        return redirect()->route('login')->with('success', 'Successfully logged out.');
    }
}