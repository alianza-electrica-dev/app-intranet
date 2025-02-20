<?php

namespace App\Http\Controllers;

class CompanyController extends Controller
{
    public function enterprise()
    {
        try {
            $companies = $this->getcompanies();
            return response()->json([
                'success' => true,
                'message' => 'Empresas encontradas',
                'Companies' => $companies
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las empresas',
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    private function getcompanies()
    {
        return [
            ["id" => 1, "name" => "Alianza Electrica"],
            ["id" => 2, "name" => "Fg-electrical"],
            ["id" => 3, "name" => "Alianza Electrica Pacifico"],
            ["id" => 4, "name" => "FG Electrica"],
            ["id" => 5, "name" => "Fg Manufacturing Services S de RL de CV"],
            ["id" => 6, "name" => "Tableros y Arracadores"],
        ];
    }
}
