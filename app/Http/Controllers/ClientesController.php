<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class ClientesController extends Controller
{
    public function getClientes(string $company, string $identifier): JsonResponse
    {
        if (!$this->validarSesion($company)) {
            return $this->errorResponse('No has iniciado sesión con esta empresa o la sesión ha expirado.', 401);
        }

        try {
            // Obtiene todos los clientes con CardType 'C'
            $clientes = $this->obtenerClientes();
            if ($clientes->isEmpty()) {
                return $this->errorResponse('No se encontraron clientes.', 404);
            }

            // Busca un cliente exacto: por CardCode o CardName
            $clienteExacto = $clientes->first(function ($cliente) use ($identifier) {
                return $cliente['CardCode'] === $identifier || $cliente['CardName'] === $identifier;
            });

            if ($clienteExacto) {
                return $this->successResponse($this->formatearCliente($clienteExacto));
            }

            // Si no hay coincidencia exacta, filtra los clientes que contengan el identificador
            $clientesFiltrados = $clientes->filter(function ($cliente) use ($identifier) {
                return stripos($cliente['CardCode'], $identifier) !== false
                    || stripos($cliente['CardName'], $identifier) !== false;
            })->map(function ($cliente) {
                return [
                    'CardCode' => $cliente['CardCode'],
                    'CardName' => $cliente['CardName']
                ];
            })->values();

            if ($clientesFiltrados->isNotEmpty()) {
                return $this->successResponse($clientesFiltrados->toArray());
            }

            return $this->errorResponse('Cliente no encontrado.', 404);
        } catch (\Exception $e) {
            Log::error('Error en la solicitud a SAP Business One: ' . $e->getMessage());
            return $this->errorResponse('Ocurrió un error al conectarse al servicio', 500);
        }
    }

    public function getClienteDetalle(string $company, string $cardCode): JsonResponse
    {
        if (!$this->validarSesion($company)) {
            return $this->errorResponse('No has iniciado sesión con esta empresa o la sesión ha expirado.', 401);
        }

        try {
            $clientes = $this->obtenerClientes();
            if ($clientes->isEmpty()) {
                return $this->errorResponse('No se encontraron clientes.', 404);
            }

            // Busca el cliente exacto usando el CardCode
            $clienteExacto = $clientes->first(function ($cliente) use ($cardCode) {
                return $cliente['CardCode'] === $cardCode;
            });

            if ($clienteExacto) {
                return $this->successResponse($this->formatearCliente($clienteExacto));
            }

            return $this->errorResponse('Cliente no encontrado.', 404);
        } catch (\Exception $e) {
            Log::error('Error en la solicitud a SAP Business One: ' . $e->getMessage());
            return $this->errorResponse('Ocurrió un error al conectarse al servicio', 500);
        }
    }

    private function validarSesion(string $company): bool
    {
        return strtoupper($company) === str_replace('SBO_', '', strtoupper(session('companyDb', '')));
    }

    private function obtenerClientes(): Collection
    {
        $response = Http::sapSL()->get('BusinessPartners', [
            '$filter' => "CardType eq 'C'"
        ]);

        if (!$response->successful()) {
            Log::error('Error al obtener clientes: ' . $response->body());
            return collect([]);
        }

        return collect($response->json()['value'] ?? []);
    }

    private function formatearCliente(array $cliente): array
    {
        return [
            'CardCode'      => $cliente['CardCode'] ?? null,
            'CardName'      => $cliente['CardName'] ?? null,
            'Fiscal'        => $this->obtenerDireccion($cliente['BPAddresses'] ?? [], 'FISCAL'),
            'Envio'         => $this->obtenerDireccion($cliente['BPAddresses'] ?? [], 'ENTREGA'),
            'PriceListName' => $this->obtenerNombreListaPrecios($cliente['PriceListNum'] ?? null),
            'PriceListNum'  => $cliente['PriceListNum'] ?? null
        ];
    }

    private function obtenerNombreListaPrecios(?int $priceListNum): ?string
    {
        if (!$priceListNum) {
            return null;
        }
        $response = Http::sapSL()->get("PriceLists({$priceListNum})");
        return $response->successful() ? $response->json()['PriceListName'] ?? null : null;
    }

    private function obtenerDireccion(array $direcciones, string $tipo): array
    {
        $direccion = collect($direcciones)->firstWhere('AddressName', $tipo) ?? [];
        return [
            'Calle'          => $direccion['Street'] ?? null,
            'Estado'         => $direccion['State'] ?? null,
            'CodigoPostal'   => $direccion['ZipCode'] ?? null,
            'Colonia'        => $direccion['Block'] ?? null,
            'NumeroInterior' => $direccion['BuildingFloorRoom'] ?? null,
            'NumeroExterior' => $direccion['StreetNo'] ?? null,
        ];
    }

    private function successResponse($data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json(['error' => $message], $status);
    }
}
