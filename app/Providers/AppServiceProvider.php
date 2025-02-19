<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Http;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void { }
    public function boot(): void
    {
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
}
