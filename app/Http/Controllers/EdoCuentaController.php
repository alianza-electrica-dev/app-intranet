<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EdoCuentaController extends Controller
{
    public function getEstadoCuenta($company, $identifier)
    {
        if (!$this->validarSesion($company)) {
            return response()->json(['error' => 'No has iniciado sesión con esta empresa o la sesión ha expirado.'], 401);
        }

        if (!$identifier) {
            return response()->json(['error' => 'Debes proporcionar un CardCode o CardName.'], 400);
        }

        try {
            $cliente = $this->consultarCliente($identifier);

            if (!$cliente) {
                return response()->json(['message' => 'Cliente no encontrado.'], 404);
            }

            return response()->json($this->formatearEstadoCuenta($cliente), 200);
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

    private function consultarCliente($identifier)
    {
        $response = Http::sapSL()->get('BusinessPartners', [
            '$filter' => "(CardCode eq '$identifier' or CardName eq '$identifier')"
        ]);

        if ($response->status() === 401) {
            response()->json(['error' => 'Sesión expirada. Por favor inicia sesión nuevamente.'], 401)->send();
            exit;
        }

        return collect($response->json()['value'] ?? [])->first();
    }

    private function formatearEstadoCuenta($cliente)
    {
        $currentBalance = $cliente['CurrentAccountBalance'] ?? 0;
        $creditLimit = $cliente['CreditLimit'] ?? 0;
        $lastPaymentDate = $this->getLastPaymentDate($cliente['CardCode']);

        return [
            'CardCode'              => $cliente['CardCode'],
            'CardName'              => $cliente['CardName'],
            'CurrentAccountBalance' => $currentBalance,
            'CreditLimit'           => $creditLimit,
            'CreditoDisponible'     => max(0, $creditLimit - $currentBalance),
            'SaldoDeudor'           => max(0, $currentBalance),
            'DiasCredito'           => $lastPaymentDate ? $this->getDiasCreditoDesdeUltimoPago($lastPaymentDate) : null,
            'LastPaymentDate'       => $lastPaymentDate,
            'PayTermsGrpCode'       => $cliente['PayTermsGrpCode'] ?? null,
        ];
    }

    private function getDiasCreditoDesdeUltimoPago($lastPaymentDate)
    {
        // Convertimos el resultado a entero para eliminar cualquier decimal.
        return (int) Carbon::parse($lastPaymentDate)->diffInDays(Carbon::now());
    }

    private function getLastPaymentDate($cardCode)
    {
        $response = Http::sapSL()->get("IncomingPayments?\$filter=CardCode eq '{$cardCode}'&\$orderby=DocDate desc&\$top=1");
        return $response->successful() ? ($response->json()['value'][0]['DocDate'] ?? null) : null;
    }
}
?>
