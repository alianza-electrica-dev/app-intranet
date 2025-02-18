<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use App\Services\SapServiceLayer; 

class TestServiceLayerMacroController extends Controller
{
    public function loginMacro($company)
    {
        $staticCompanies = config('services.sap.static_companies');
        $dynamicCompanies = array_map(fn($c) => 'SBO_' . strtoupper($c), config('services.sap.dynamic_companies'));
        if (!in_array($company, array_merge($staticCompanies, $dynamicCompanies))) {
            return response()->json(['error' => 'Empresa no válida.'], 400);
        }
        $response = Http::withOptions(['verify' => false])
            ->post(config('services.sap.host') . '/b1s/v1/Login', [
                'CompanyDB' => $company,
                'UserName'  => config('services.sap.username'),
                'Password'  => config('services.sap.password'),
            ]);
        if ($response->successful()) {
            session([
                'sessionId' => $response->json()['SessionId'],
                'companyDb' => $company
            ]);
            return response()->json(['message' => 'Login exitoso', 'company' => $company]);
        }
        return response()->json(['error' => 'Login fallido', 'sap_response' => $response->json()], 401);
    }

    public function getProvidersMacro()
    {
        try {
            $response = Http::sapSL()->get('BusinessPartners');
        } catch (\Exception $e) {
            return response()->json(['error' => 'No hay sesión activa o ha expirado'], 401);
        }
        return $response->json();
    }

    public function logoutMacro()
    {
        return (new SapServiceLayer())->logout();
    }
}
