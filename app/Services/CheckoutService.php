<?php

namespace App\Services;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use Database\Seeders\OrderSeeder;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use App\Models\Payment;

class CheckoutService {

    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('payment.mercadopago.access_token'));
    }

    public function loadCart(): array
    {
        $cart = Order::with('skus.product','skus.features')
            ->where('status', OrderStatusEnum::CART)
            ->where(function($query){
                $query->where('session_id', session()->getId());
                if (auth()->check()) {
                    $query->orWhere('user_id', auth()->id());
                }
            })->first();

        if(!$cart && config('app.env') == 'local'){
            $seed = new OrderSeeder();
            $seed->run(session()->getId());
            return $this->loadCart();
        }

        return $cart->toArray();
    }

    public function creditCardPayment(array $data)
    {

        $payment = new Payment();
        $payment->transaction_amount = (float)$data['transaction_amount'];
        $payment->token = $data['token'];
        $payment->description = $data['description'];
        $payment->installments = (int)$data['installments'];
        $payment->payment_method_id = $data['payment_method_id'];
        $payment->issuer_id = (int)$data['issuer_id'];

        $client = new PaymentClient();

        $payer = $client->create([
            'transaction_amount' => (float)$data['transaction_amount'],
            'token' => $data['token'],
            'description' => $data['description'],
            'installments' => (int)$data['installments'],
            'payment_method_id' => $data['payment_method_id'],
            'issuer_id' => (int)$data['issuer_id'],
            'payer' => [
                'email' => $data['payer']['email'],
                'identification' => [
                    'type' => $data['payer']['identification']['type'],
                    'number' => $data['payer']['identification']['number'],
                ],
            ],
        ]);

        $payment->payer = $payer;

        $payment->save();
    }
}
