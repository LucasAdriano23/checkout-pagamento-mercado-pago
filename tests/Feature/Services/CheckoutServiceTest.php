<?php

use App\Enums\OrderStatusEnum;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Contracts\PaymentGatewayClient;
use MercadoPago\Net\MPResponse;
use MercadoPago\Resources\Payment as MercadoPagoPayment;

function fakeMercadoPagoPayment(array $content): MercadoPagoPayment
{
    $payment = new MercadoPagoPayment();
    $payment->id = $content['id'] ?? null;
    $payment->status = $content['status'] ?? null;
    $payment->status_detail = $content['status_detail'] ?? null;
    $payment->payment_type_id = $content['payment_type_id'] ?? null;
    $payment->installments = $content['installments'] ?? null;
    $payment->date_approved = $content['date_approved'] ?? null;
    $payment->point_of_interaction = null;
    $payment->setResponse(new MPResponse(201, $content));

    return $payment;
}

function creditCardData(array $overrides = []): array
{
    return array_merge([
        'transaction_amount' => 100.0,
        'token' => 'card_token',
        'installments' => 1,
        'payment_method_id' => 'visa',
        'issuer_id' => 1,
        'payer' => [
            'name' => 'Maria Silva',
            'identification' => ['type' => 'CPF', 'number' => '12345678900'],
        ],
        'name' => 'Maria Silva',
        'email' => 'maria@example.com',
        'address' => [
            'zipcode' => '12345678',
            'address' => 'Rua A',
            'number' => '10',
            'district' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
    ], $overrides);
}

it('aprova o pagamento no cartão e atualiza o pedido', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatusEnum::CART,
    ]);
    $this->actingAs($user);

    $fakePayment = fakeMercadoPagoPayment([
        'id' => 123456,
        'status' => 'approved',
        'status_detail' => 'accredited',
        'payment_type_id' => 'credit_card',
        'installments' => 1,
    ]);

    $this->mock(PaymentGatewayClient::class, function ($mock) use ($fakePayment) {
        $mock->shouldReceive('charge')->once()->andReturn($fakePayment);
    });

    $content = app(CheckoutService::class)->creditCardPayment(creditCardData());

    expect($content['id'])->toBe(123456);

    $order->refresh();
    expect($order->status)->toBe(OrderStatusEnum::PAID);
    expect($order->payments()->count())->toBe(1);
    expect($order->shippings()->count())->toBe(1);
});

it('lança PaymentException com mensagem amigável quando o cartão é rejeitado', function () {
    $user = User::factory()->create();
    Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatusEnum::CART,
    ]);
    $this->actingAs($user);

    $fakePayment = fakeMercadoPagoPayment([
        'id' => 654321,
        'status' => 'rejected',
        'status_detail' => 'cc_rejected_insufficient_amount',
    ]);

    $this->mock(PaymentGatewayClient::class, function ($mock) use ($fakePayment) {
        $mock->shouldReceive('charge')->once()->andReturn($fakePayment);
    });

    app(CheckoutService::class)->creditCardPayment(creditCardData());
})->throws(PaymentException::class, 'Cartão sem saldo/limite suficiente para esta compra.');
