<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Http;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void { }
    public function boot(): void
    {
        $this->autoLogin(); // <-- Agregar esta línea
    
        Http::macro('sapSL', function () {
            $sessionId = session('sessionId');
            if (!$sessionId) {
                throw new \Exception('There is no active session in SAP');
            }
            $host = config('services.sap.host');
            return Http::withOptions(['verify' => false])
                ->withHeaders(['Cookie' => "B1SESSION={$sessionId}"])
                ->baseUrl("{$host}/b1s/v1");
        });
    }

    protected function autoLogin()
    {
        if (!session()->has('sessionId')) {
            $companyDb = 'SBO_Pruebas'; // Puedes cambiarlo o hacerlo dinámico
            $response = Http::withOptions(['verify' => false])
                ->post(config('services.sap.host') . '/b1s/v1/Login', [
                    'CompanyDB' => $companyDb,
                    'UserName'  => config('services.sap.username'),
                    'Password'  => config('services.sap.password'),
                ]);
    
            if ($response->successful()) {
                session([
                    'sessionId' => $response->json()['SessionId'],
                    'companyDb' => $companyDb
                ]);
            }
        }
    }
    
}