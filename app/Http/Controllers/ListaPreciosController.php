<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ListaPreciosController extends Controller
{
    public function getPriceLists($company, $cardCode)
    {
        if (!$this->validarSesion($company)) {
            return response()->json(['error' => 'No has iniciado sesión con esta empresa o la sesión ha expirado.'], 401);
        }

        if (!$cardCode) {
            return response()->json(['error' => 'Debes proporcionar un CardCode.'], 400);
        }

        try {
            $cliente = $this->obtenerCliente($cardCode);
            if (!$cliente) {
                return response()->json(['message' => 'Cliente no encontrado.'], 404);
            }

            $priceListNum = $cliente['PriceListNum'] ?? null;
            if (!$priceListNum) {
                return response()->json(['error' => 'El cliente no tiene una lista de precios asignada.'], 404);
            }

            return $this->obtenerListaPrecios($priceListNum);
        } catch (\Exception $e) {
            Log::error('Error en la solicitud a SAP Business One: ' . $e->getMessage());
            return response()->json(['error' => 'Ocurrió un error al conectarse al servicio', 'details' => $e->getMessage()], 500);
        }
    }

    private function validarSesion($company)
    {
        $loggedCompany = session('companyDb');
        return $loggedCompany && strtoupper($company) === str_replace('SBO_', '', strtoupper($loggedCompany));
    }

    private function obtenerCliente($cardCode)
    {
        $response = Http::sapSL()->get('BusinessPartners', [
            '$filter' => "(CardCode eq '$cardCode' or CardName eq '$cardCode') and CardType eq 'C'"
        ]);

        if ($response->status() === 401) {
            response()->json(['error' => 'Sesión expirada. Por favor inicia sesión nuevamente.'], 401)->send();
            exit;
        }

        return collect($response->json()['value'] ?? [])->first();
    }

    private function obtenerListaPrecios($priceListNum)
    {
        $response = Http::sapSL()->get("PriceLists({$priceListNum})");

        return $response->successful()
            ? response()->json($response->json(), 200)
            : response()->json(['error' => 'Error al obtener la lista de precios', 'details' => $response->json()], $response->status());
    }
}
