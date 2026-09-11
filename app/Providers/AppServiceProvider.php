<?php

namespace App\Providers;

use App\Services\Contracts\PaymentGatewayClient;
use App\Services\MercadoPagoPaymentClient;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayClient::class, MercadoPagoPaymentClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('money', function($expression){
            return "<?php echo 'R$ ' . number_format($expression, 2, ',', '.'); ?>";
        });
    }
}
