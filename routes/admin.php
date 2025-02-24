<?php
use App\Http\Controllers\TestServiceLayerController;
use App\Http\Controllers\TestServiceLayerMacroController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ShippingAddressController;
use App\Http\Controllers\QuotationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;

# login y conexiones a SL
Route::get('/login/{company}', [TestServiceLayerController::class, 'login']);
Route::get('/providers', [TestServiceLayerController::class, 'getProviders']);
Route::get('/macro/login/{company}', [TestServiceLayerMacroController::class, 'loginMacro']);
Route::get('/providers-macro', [TestServiceLayerMacroController::class, 'getProvidersMacro']);
Route::get('/logout', [TestServiceLayerController::class, 'logout']); 
Route::get('/logout-macro', [TestServiceLayerMacroController::class, 'logoutMacro']); 


Route::prefix('admin')->name('admin')->group(function () {
    // Cotizador
    Route::get('/companies', [CompanyController::class, 'enterprise']);
    Route::get('/customers/{identifier}', [CustomersController::class, 'getCustomers']);
    Route::get('/pricelist/{cardCode}', [PriceListController::class, 'getPriceLists']);
    Route::get('/statement/{identifier}', [StatementController::class, 'getAccountStatus']);
    Route::get('/products/{identifier}', [ProductsController::class, 'getProducts']);
    Route::get('/generar-pdf', [PdfController::class, 'generarPDF']);

    // Rutas POST
    Route::post('/{cliente}/shippingaddress', [ShippingAddressController::class, 'store'])
        ->withoutMiddleware([Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    Route::post('/{cliente}/quote', [QuotationController::class, 'createQuote'])
        ->withoutMiddleware([Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
})->middleware(['cors']);





