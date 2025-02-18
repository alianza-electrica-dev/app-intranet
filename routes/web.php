<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestServiceLayerController;
use App\Http\Controllers\TestServiceLayerMacroController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\ListaPreciosController;
use App\Http\Controllers\EdoCuentaController;
use App\Http\Controllers\ProductosController;
use App\Http\Controllers\DireccionEnvioController;
use App\Http\Controllers\CotizacionController;






Route::get('/', function () {
    return view('welcome');
});


# login y conexiones a SL
Route::get('/login/{company}', [TestServiceLayerController::class, 'login']);
Route::get('/providers', [TestServiceLayerController::class, 'getProviders']);
Route::get('/macro/login/{company}', [TestServiceLayerMacroController::class, 'loginMacro']);
Route::get('/providers-macro', [TestServiceLayerMacroController::class, 'getProvidersMacro']);
Route::get('/logout', [TestServiceLayerController::class, 'logout']); 
Route::get('/logout-macro', [TestServiceLayerMacroController::class, 'logoutMacro']); 


//Cotizador
Route::get('/empresas', [EmpresaController::class, 'enterprise']);
Route::get('/{company}/clientes/{identifier}', [ClientesController::class, 'getClientes']);
Route::get('/{company}/listas-precios/{cardCode}', [ListaPreciosController::class, 'getPriceLists']);
Route::get('/{company}/estado-cuenta/{identifier}', [EdoCuentaController::class, 'getEstadoCuenta']);
Route::get('/{company}/productos/{identifier}', [ProductosController::class, 'getProductos']);


//Rutas POST
Route::post('/{company}/{cliente}/direccionenvio', [DireccionEnvioController::class, 'store'])
    ->withoutMiddleware([Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/{company}/{cliente}/cotizacion', [CotizacionController::class, 'crearCotizacion'])
    ->withoutMiddleware([Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

