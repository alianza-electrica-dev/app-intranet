<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestServiceLayerController;
use App\Http\Controllers\TestServiceLayerMacroController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ShippingAddressController;
use App\Http\Controllers\QuotationController;






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
Route::get('/companies', [CompanyController::class, 'enterprise']);
Route::get('/{company}/customers/{identifier}', [CustomersController::class, 'getCustomers']);
Route::get('/{company}/pricelist/{cardCode}', [PriceListController::class, 'getPriceLists']);
Route::get('/{company}/statement/{identifier}', [StatementController::class, 'getAccountStatus']);
Route::get('/{company}/products/{identifier}', [ProductsController::class, 'getProducts']);


//Rutas POST
Route::post('/{company}/{cliente}/shippingaddress', [ShippingAddressController::class, 'store'])
    ->withoutMiddleware([Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/{company}/{cliente}/quote', [QuotationController::class, 'createQuote'])
    ->withoutMiddleware([Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

