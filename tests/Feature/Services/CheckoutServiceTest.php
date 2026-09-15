<?php

use App\Enums\OrderStatusEnum;
use App\Exceptions\CartExpiredException;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Contracts\PaymentGatewayClient;
use Illuminate\Support\Str;
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

it('lança CartExpiredException sem chamar o gateway quando não há carrinho para a sessão', function (string $method, array $data) {
    $this->actingAs(User::factory()->create());

    $this->mock(PaymentGatewayClient::class, function ($mock) {
        $mock->shouldNotReceive('charge');
    });

    app(CheckoutService::class)->{$method}($data);
})->with([
    'cartão de crédito' => ['creditCardPayment', fn () => creditCardData()],
    'pix' => ['pixOrBankSlipPayment', fn () => ['method' => 'pix', 'amount' => 100.0]],
])->throws(CartExpiredException::class);

/** Pedido em carrinho do usuário autenticado, que é o que o CheckoutService procura. */
function checkoutServiceCartOrder(): Order
{
    $user = User::factory()->create();

    test()->actingAs($user);

    return Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatusEnum::CART,
    ]);
}

function pixOrBankSlipData(array $overrides = []): array
{
    return array_merge([
        'method' => 'pix',
        'amount' => 150.5,
        'name' => 'Maria Silva',
        'email' => 'maria@example.com',
        'cpf' => '529.982.247-25',
        'address' => [
            'zipcode' => '01310-930',
            'address' => 'Avenida Paulista',
            'number' => '1578',
            'district' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
    ], $overrides);
}

/** Captura o payload e a chave de idempotência enviados ao gateway. */
function captureGatewayCharge(MercadoPagoPayment $response): object
{
    // objeto, e não array: o retorno por valor perderia as mutações da closure
    $captured = new stdClass();
    $captured->payload = null;
    $captured->idempotencyKey = null;

    test()->mock(PaymentGatewayClient::class, function ($mock) use ($response, $captured) {
        $mock->shouldReceive('charge')
            ->once()
            ->andReturnUsing(function (array $payload, string $idempotencyKey) use ($response, $captured) {
                $captured->payload = $payload;
                $captured->idempotencyKey = $idempotencyKey;

                return $response;
            });
    });

    return $captured;
}

describe('payload enviado ao gateway', function () {
    it('envia o endereço do pagador no boleto', function () {
        checkoutServiceCartOrder();

        $captured = captureGatewayCharge(fakeMercadoPagoPayment([
            'id' => 111222,
            'status' => 'pending',
            'payment_type_id' => 'ticket',
        ]));

        app(CheckoutService::class)->pixOrBankSlipPayment(pixOrBankSlipData(['method' => 'bolbradesco']));

        expect($captured->payload['payment_method_id'])->toBe('bolbradesco')
            ->and($captured->payload['payer']['address'])->toBe([
                'zip_code' => '01310930',
                'street_name' => 'Avenida Paulista',
                'street_number' => '1578',
                'neighborhood' => 'Bela Vista',
                'city' => 'São Paulo',
                'federal_unit' => 'SP',
            ]);
    });

    it('não envia endereço do pagador no pix', function () {
        checkoutServiceCartOrder();

        $captured = captureGatewayCharge(fakeMercadoPagoPayment([
            'id' => 111333,
            'status' => 'pending',
            'payment_type_id' => 'bank_transfer',
        ]));

        app(CheckoutService::class)->pixOrBankSlipPayment(pixOrBankSlipData());

        expect($captured->payload['payment_method_id'])->toBe('pix')
            ->and($captured->payload['payer'])->not->toHaveKey('address');
    });

    it('envia cpf e e-mail do comprador configurado, com uma chave de idempotência', function () {
        checkoutServiceCartOrder();
        config()->set('payment.mercadopago.buyer_email', 'comprador@mercadopago.test');

        $captured = captureGatewayCharge(fakeMercadoPagoPayment([
            'id' => 111444,
            'status' => 'pending',
            'payment_type_id' => 'bank_transfer',
        ]));

        app(CheckoutService::class)->pixOrBankSlipPayment(pixOrBankSlipData());

        expect($captured->payload['payer']['email'])->toBe('comprador@mercadopago.test')
            ->and($captured->payload['payer']['first_name'])->toBe('Maria')
            ->and($captured->payload['payer']['last_name'])->toBe('Silva')
            ->and($captured->payload['payer']['identification'])->toBe(['type' => 'CPF', 'number' => '52998224725'])
            ->and($captured->payload['transaction_amount'])->toBe(150.5)
            ->and(Str::isUuid($captured->idempotencyKey))->toBeTrue();
    });
});

describe('mensagens de recusa do cartão', function () {
    it('traduz o status_detail do Mercado Pago em uma mensagem para o cliente', function (?string $statusDetail, string $expected) {
        checkoutServiceCartOrder();

        $this->mock(PaymentGatewayClient::class, function ($mock) use ($statusDetail) {
            $mock->shouldReceive('charge')->once()->andReturn(fakeMercadoPagoPayment([
                'id' => 654321,
                'status' => 'rejected',
                'status_detail' => $statusDetail,
            ]));
        });

        expect(fn () => app(CheckoutService::class)->creditCardPayment(creditCardData()))
            ->toThrow(PaymentException::class, $expected);
    })->with([
        'número do cartão' => ['cc_rejected_bad_filled_card_number', 'Revise o número do cartão.'],
        'validade' => ['cc_rejected_bad_filled_date', 'Revise a data de validade do cartão.'],
        'cvv' => ['cc_rejected_bad_filled_security_code', 'Revise o código de segurança (CVV).'],
        'outros dados' => ['cc_rejected_bad_filled_other', 'Revise os dados do cartão informados.'],
        'blacklist' => ['cc_rejected_blacklist', 'Não foi possível aprovar este pagamento. Tente outro cartão ou meio de pagamento.'],
        'alto risco' => ['cc_rejected_high_risk', 'Não foi possível aprovar este pagamento. Tente outro cartão ou meio de pagamento.'],
        'autorização do banco' => ['cc_rejected_call_for_authorize', 'Você precisa autorizar este pagamento junto ao seu banco antes de tentar novamente.'],
        'cartão desabilitado' => ['cc_rejected_card_disabled', 'Ligue para o seu banco para ativar o cartão ou utilize outro meio de pagamento.'],
        'pagamento duplicado' => ['cc_rejected_duplicated_payment', 'Já identificamos um pagamento com esses mesmos dados. Caso precise pagar novamente, utilize outro cartão.'],
        'sem limite' => ['cc_rejected_insufficient_amount', 'Cartão sem saldo/limite suficiente para esta compra.'],
        'parcelas inválidas' => ['cc_rejected_invalid_installments', 'Este cartão não aceita o número de parcelas escolhido.'],
        'limite de tentativas' => ['cc_rejected_max_attempts', 'Você atingiu o limite de tentativas. Tente outro cartão ou meio de pagamento.'],
        'motivo desconhecido' => ['algum_motivo_novo', 'Verifique os dados do cartão e tente novamente.'],
        'sem motivo' => [null, 'Verifique os dados do cartão e tente novamente.'],
    ]);

    it('recusa o pagamento quando o gateway não devolve id', function () {
        checkoutServiceCartOrder();

        $this->mock(PaymentGatewayClient::class, function ($mock) {
            $mock->shouldReceive('charge')->once()->andReturn(fakeMercadoPagoPayment(['status' => 'approved']));
        });

        expect(fn () => app(CheckoutService::class)->creditCardPayment(creditCardData()))
            ->toThrow(PaymentException::class);
    });

    it('recusa pix e boleto quando o gateway não devolve id', function () {
        checkoutServiceCartOrder();

        $this->mock(PaymentGatewayClient::class, function ($mock) {
            $mock->shouldReceive('charge')->once()->andReturn(fakeMercadoPagoPayment(['status' => 'pending']));
        });

        expect(fn () => app(CheckoutService::class)->pixOrBankSlipPayment(pixOrBankSlipData()))
            ->toThrow(PaymentException::class, 'Não foi possível gerar o pagamento. Verifique os dados e tente novamente.');
    });
});
