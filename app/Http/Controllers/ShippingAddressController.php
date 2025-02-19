<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;


class ShippingAddressController extends Controller
{
    public function store(Request $request, $company, $cliente)
    {
        if (!$this->validarSesion($company)) {
            return response()->json(['error' => 'No has iniciado sesión con esta empresa o la sesión ha expirado.'], 401);
        }

        $validatedData = $request->validate([
            'tipo_envio' => 'required|string|in:CR,PAQ,RLOC,RFOR',
            'tipo_entrega' => 'required|string',
            'datos' => 'required|array',
        ]);

        return response()->json($this->formatResponse($validatedData), 200);
    }

    private function formatResponse(array $validatedData): array
    {
        $tipoEnvio = strtoupper($validatedData['tipo_envio']);
        $tipoEntrega = strtoupper($validatedData['tipo_entrega']);

        $response = [
            'Tipo de envío' => $tipoEnvio,
            'Tipo de entrega' => $tipoEntrega,
            'Teléfono de Contacto' => $validatedData['datos']['telefono_contacto'] ?? '',
            'Correo de Contacto' => $validatedData['datos']['correo_contacto'] ?? '',
            'Instrucciones de entrega' => $validatedData['datos']['instrucciones_entrega'] ?? '',
        ];
        if (in_array($tipoEnvio, ['RLOC', 'RFOR'])) {
            $response = array_merge($response, [
                'Pagado o por cobrar' => $validatedData['datos']['pagado_por_cobrar'] ?? '',
                'Dirección de Entrega del cliente' => $validatedData['datos']['direccion_entrega'] ?? '',
                'Calle y Numero' => $validatedData['datos']['calle_numero'] ?? '',
                'Colonia' => $validatedData['datos']['colonia'] ?? '',
                'Estado' => $validatedData['datos']['estado'] ?? '',
                'Ciudad' => $validatedData['datos']['ciudad'] ?? '',
                'Pais' => $validatedData['datos']['pais'] ?? '',
                'C.P' => $validatedData['datos']['cp'] ?? '',
                'RFC' => $validatedData['datos']['rfc'] ?? '',
            ]);
        } 
        // Si el envío es CR
        elseif ($tipoEnvio == 'CR') {
            $response = array_merge($response, [
                'Nombre de Persona autorizada' => $validatedData['datos']['persona_autorizada'] ?? '',
                'Sucursal' => $validatedData['datos']['sucursal'] ?? '',
                'Número de identidad' => $validatedData['datos']['numero_identidad'] ?? '',
                'Fecha de recolección' => $validatedData['datos']['fecha_recoleccion'] ?? '',
            ]);
        }
        // Si el envío es PAQ
        elseif ($tipoEnvio == 'PAQ') {
            $response = array_merge($response, [
                'Nombre de Persona autorizada' => $validatedData['datos']['persona_autorizada'] ?? '',
                'Sucursal' => $validatedData['datos']['sucursal'] ?? '',
                'Fecha de recolección' => $validatedData['datos']['fecha_recoleccion'] ?? '',
                'Número de identidad' => $validatedData['datos']['numero_identidad'] ?? '',
            ]);
        }

        return $response;
    }

    private function validarSesion($company): bool
    {
        $loggedCompany = session('companyDb');
        return $loggedCompany && strtoupper($company) === str_replace('SBO_', '', strtoupper($loggedCompany));
    }
}
?>