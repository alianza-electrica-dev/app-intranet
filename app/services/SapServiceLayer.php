<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SapServiceLayer
{
    protected $host;
    protected $username;
    protected $password;
    protected $sessionId;
    protected $companyDb;

    public function __construct()
    {
        $this->host = config('services.sap.host');
        $this->username = config('services.sap.username');
        $this->password = config('services.sap.password');
    }

    public function login($companyDb)
{
    if (session()->has('sessionId')) {
        return response()->json([
            'message'   => 'Already logged in',
            'company'   => session('companyDb'),
            'sessionId' => session('sessionId')
        ]);
    }

    $this->companyDb = $companyDb;
    $response = Http::withOptions(['verify' => false])
        ->post("{$this->host}/b1s/v1/Login", [
            'CompanyDB' => $this->companyDb,
            'UserName'  => $this->username,
            'Password'  => $this->password,
        ]);

    $sapResponse = $response->json();
    if ($response->successful() && isset($sapResponse['SessionId'])) {
        $this->sessionId = $sapResponse['SessionId'];
        session([
            'sessionId' => $this->sessionId,
            'companyDb' => $this->companyDb
        ]);
        return response()->json([
            'message'   => 'Login successful',
            'company'   => $this->companyDb,
            'sessionId' => $this->sessionId
        ]);
    }
    return response()->json([
        'error'      => 'Login failed in SAP',
        'companyDb'  => $this->companyDb,
        'status'     => $response->status(),
        'sap_error'  => $sapResponse
    ], 401);
}

    public function getSessionId()
    {
        return $this->sessionId;
    }
    public function logout()
    {
        if (!$this->sessionId) {
            return response()->json(['message' => 'Session now closed.'], 200);
        }
        $response = Http::withOptions(['verify' => false])
            ->withHeaders(['Cookie' => "B1SESSION={$this->sessionId}"])
            ->post("{$this->host}/b1s/v1/Logout");
        session()->forget(['sessionId', 'companyDb']);
        $this->sessionId = null;
        if ($response->successful()) {
            return response()->json(['message' => 'Successful logout']);
        }
        return response()->json(['error' => 'Error when logging out of SAP'], 500);
    }

    
}
