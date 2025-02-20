<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void { }
    public function boot(): void
    {
        $this->autoLogin(); // <-- Mantener la llamada al método
    
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
        $url = Request::path(); // Obtener la URL actual sin el dominio
        preg_match('/admin\/(\w+)\/customers\//', $url, $matches);
        
        if (!isset($matches[1])) {
            return; // Si no se encuentra el nombre de la empresa, salir
        }

        $company = ucfirst(strtolower($matches[1])); // Normalizar el nombre de la empresa
        $availableCompanies = array_merge(
            config('services.sap.static_companies'),
            config('services.sap.dynamic_companies')
        );

        if (!in_array("SBO_{$company}", $availableCompanies)) {
            return; // Si la empresa no está en la lista de permitidas, salir
        }

        $currentCompany = session('companyDb');

        if ($currentCompany && $currentCompany !== "SBO_{$company}") {
            // Si la sesión actual es diferente, cerrar sesión
            $this->logoutSAP();
        }

        if (!session()->has('sessionId')) {
            $this->loginSAP("SBO_{$company}");
        }
    }

    protected function loginSAP($companyDb)
    {
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

    protected function logoutSAP()
    {
        if (!session()->has('sessionId')) {
            return;
        }

        Http::withOptions(['verify' => false])
            ->withHeaders(['Cookie' => "B1SESSION=" . session('sessionId')])
            ->post(config('services.sap.host') . '/b1s/v1/Logout');
    
        session()->forget(['sessionId', 'companyDb']);
    }
}
