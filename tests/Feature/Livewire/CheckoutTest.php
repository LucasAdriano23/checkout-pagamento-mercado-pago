<?php

use App\Enums\CheckoutStepsEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Exceptions\CartExpiredException;
use App\Exceptions\PaymentException;
use App\Livewire\Checkout;
use App\Mail\OrderCreatedMail;
use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Contracts\PaymentGatewayClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use MercadoPago\Net\MPResponse;
use MercadoPago\Resources\Payment as MercadoPagoPayment;

/**
 * Cria o pedido em CART do usuário autenticado, que é o que mount() carrega
 * através de OrderService::getCartOrder().
 */
function checkoutCartOrder(array $attributes = []): Order
{
    $user = User::factory()->create();

    test()->actingAs($user);

    return Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatusEnum::CART,
        ...$attributes,
    ]);
}

/** Dados válidos das etapas anteriores, para chegar até o pagamento. */
function checkoutValidInformation(): array
{
    return [
        'user.name' => 'Maria Silva',
        'user.email' => 'maria@example.com',
        'user.cpf' => '529.982.247-25',
        'address.zipcode' => '01310-930',
        'address.address' => 'Avenida Paulista',
        'address.number' => '1578',
        'address.district' => 'Bela Vista',
        'address.city' => 'São Paulo',
        'address.state' => 'SP',
    ];
}

function checkoutGatewayPayment(array $content): MercadoPagoPayment
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

beforeEach(function () {
    config()->set('payment.mercadopago.buyer_email', 'comprador@mercadopago.test');
});

describe('mount', function () {

    it('checkout foi renderizado', function() {
        Livewire::test(Checkout::class)
            ->assertStatus(200);
    });

    it('abre o checkout na etapa de informações', function () {
        checkoutCartOrder();

        Livewire::test(Checkout::class)
            ->assertSet('step', CheckoutStepsEnum::INFORMATION->value)
            ->assertSet('cartExpired', false);
    });

    it('rejeita dados de contato inválidos', function (string $campo, $valor, string $regra) {
        checkoutCartOrder();

        Livewire::test(Checkout::class)
            ->set([...checkoutValidInformation(), $campo => $valor])
            ->call('submitInformationStep')
            ->assertHasErrors([$campo => $regra])
            ->assertSet('step', CheckoutStepsEnum::INFORMATION->value);
    })->with([
        'e-mail malformado' => ['user.email', 'maria', 'email'],
        'nome muito curto'  => ['user.name', 'Ma', 'min'],
        'cpf inválido'      => ['user.cpf', '111.111.111-11', 'cpf'],
    ]);

    it('carrega o pedido em carrinho do usuário como resumo do checkout', function () {
        $order = checkoutCartOrder(['total' => 199.90]);

        Livewire::test(Checkout::class)
            ->assertSet('cart.id', $order->id)
            ->assertSet('cart.total', $order->total)
            ->assertSet('cart.skus', []);
    });

    it('preenche o e-mail do comprador a partir da configuração do Mercado Pago', function () {
        checkoutCartOrder();

        Livewire::test(Checkout::class)
            ->assertSet('user.email', 'comprador@mercadopago.test');
    });

    it('mostra a tela de sessão expirada quando não existe carrinho para a sessão atual', function () {
        Livewire::test(Checkout::class)
            ->assertSet('cartExpired', true)
            ->assertSet('cart', [])
            ->assertSee('Sua sessão expirou')
            ->assertSee('Voltar ao início');
    });

    it('não renderiza o resumo do pedido quando o carrinho não é encontrado', function () {
        Livewire::test(Checkout::class)
            ->assertDontSee('Resumo')
            ->assertDontSee('Informacoes de contato');
    });

    it('não seleciona nenhum método de pagamento por padrão', function () {
        checkoutCartOrder();

        Livewire::test(Checkout::class)
            ->assertSet('method', null);
    });
});

describe('findAddress', function () {
    it('preenche o endereço com os dados retornados pela consulta de CEP', function () {
        checkoutCartOrder();

        Http::fake([
            'viacep.com.br/*' => Http::response([
                'logradouro' => 'Avenida Paulista',
                'localidade' => 'São Paulo',
                'uf' => 'SP',
                'bairro' => 'Bela Vista',
            ]),
        ]);

        Livewire::test(Checkout::class)
            ->set('address.zipcode', '01310-930')
            ->call('findAddress')
            ->assertHasNoErrors('address.zipcode')
            ->assertSet('address.address', 'Avenida Paulista')
            ->assertSet('address.city', 'São Paulo')
            ->assertSet('address.state', 'SP')
            ->assertSet('address.district', 'Bela Vista');
    });

    it('reporta CEP inválido e não preenche o endereço quando a consulta falha', function (array $response) {
        checkoutCartOrder();

        Http::fake(['viacep.com.br/*' => Http::response($response)]);

        Livewire::test(Checkout::class)
            ->set('address.zipcode', '00000-000')
            ->call('findAddress')
            ->assertHasErrors(['address.zipcode' => 'Cep Inválido'])
            ->assertSet('address.address', '')
            ->assertSet('address.city', '');
    })->with([
        'CEP inexistente' => [['erro' => true]],
        'resposta vazia' => [[]],
    ]);
});

describe('submitInformationStep', function () {
    it('avança para a etapa de frete quando os dados do comprador e do endereço são válidos', function () {
        checkoutCartOrder();

        Livewire::test(Checkout::class)
            ->set(checkoutValidInformation())
            ->call('submitInformationStep')
            ->assertHasNoErrors()
            ->assertSet('step', CheckoutStepsEnum::SHIPPING->value);
    });

    it('permanece na etapa de informações e reporta os campos obrigatórios em branco', function () {
        checkoutCartOrder();

        Livewire::test(Checkout::class)
            ->set('user.email', '')
            ->call('submitInformationStep')
            ->assertHasErrors([
                'user.email' => 'required',
                'user.name' => 'required',
            ])
            ->assertSet('step', CheckoutStepsEnum::INFORMATION->value);
    });

    it('não avança quando o endereço está incompleto', function () {
        checkoutCartOrder();

        Livewire::test(Checkout::class)
            ->set(['user.name' => 'Maria Silva', 'user.email' => 'maria@example.com'])
            ->call('submitInformationStep')
            ->assertHasErrors([
                'address.zipcode' => 'required',
                'address.address' => 'required',
                'address.city' => 'required',
                'address.state' => 'required',
                'address.district' => 'required',
                'address.number' => 'required',
            ])
            ->assertSet('step', CheckoutStepsEnum::INFORMATION->value);
    });

    it('não avança quando o CPF informado é inválido', function () {
        checkoutCartOrder();

        Livewire::test(Checkout::class)
            ->set([...checkoutValidInformation(), 'user.cpf' => '111.111.111-11'])
            ->call('submitInformationStep')
            ->assertHasErrors('user.cpf')
            ->assertSet('step', CheckoutStepsEnum::INFORMATION->value);
    });
});

describe('submitShippingStep', function () {
    it('avança da etapa de frete para a de pagamento', function () {
        checkoutCartOrder();

        Livewire::test(Checkout::class)
            ->set('step', CheckoutStepsEnum::SHIPPING->value)
            ->call('submitShippingStep')
            ->assertSet('step', CheckoutStepsEnum::PAYMENT->value);
    });
});

describe('pagamento', function () {
    it('redireciona para o resultado assinado do pedido quando o pagamento é concluído', function (string $action) {
        $order = checkoutCartOrder();
        $payload = ['transaction_amount' => 100.0, 'method' => 'pix'];

        $this->mock(CheckoutService::class, function ($mock) use ($action, $payload) {
            $mock->shouldReceive($action)->once()->with($payload)->andReturn(['id' => 123456]);
        });

        $component = Livewire::test(Checkout::class)
            ->call($action, $payload)
            ->assertHasNoErrors()
            ->assertRedirectContains(route('checkout.result', ['order' => $order->id]));

        expect(URL::hasValidSignature(Request::create($component->effects['redirect'])))->toBeTrue();
    })->with(['creditCardPayment', 'pixOrBankSlipPayment']);

    it('reporta a mensagem do gateway e não redireciona quando o pagamento é recusado', function (string $action) {
        checkoutCartOrder();

        $this->mock(CheckoutService::class, function ($mock) use ($action) {
            $mock->shouldReceive($action)
                ->once()
                ->andThrow(new PaymentException('Cartão sem saldo/limite suficiente para esta compra.'));
        });

        Livewire::test(Checkout::class)
            ->call($action, ['transaction_amount' => 100.0])
            ->assertHasErrors(['payment' => 'Cartão sem saldo/limite suficiente para esta compra.'])
            ->assertNoRedirect();
    })->with(['creditCardPayment', 'pixOrBankSlipPayment']);

    it('reporta a mensagem e não redireciona quando ocorre uma falha inesperada', function (string $action) {
        checkoutCartOrder();

        $this->mock(CheckoutService::class, function ($mock) use ($action) {
            $mock->shouldReceive($action)->once()->andThrow(new Exception('Falha inesperada no gateway'));
        });

        Livewire::test(Checkout::class)
            ->call($action, ['transaction_amount' => 100.0])
            ->assertHasErrors(['payment' => 'Falha inesperada no gateway'])
            ->assertNoRedirect();
    })->with(['creditCardPayment', 'pixOrBankSlipPayment']);

    it('reinicia o checkout quando o carrinho desaparece antes do pagamento', function (string $action) {
        checkoutCartOrder();

        $this->mock(CheckoutService::class, function ($mock) use ($action) {
            $mock->shouldReceive($action)->once()->andThrow(new CartExpiredException());
        });

        Livewire::test(Checkout::class)
            ->set('step', CheckoutStepsEnum::PAYMENT->value)
            ->call($action, ['transaction_amount' => 100.0])
            ->assertSet('cartExpired', true)
            ->assertSet('cart', [])
            ->assertSet('step', CheckoutStepsEnum::INFORMATION->value)
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSee('Sua sessão expirou');
    })->with(['creditCardPayment', 'pixOrBankSlipPayment']);

    it('conclui o pagamento em pix ponta a ponta e enfileira o e-mail de confirmação', function () {
        Mail::fake();

        $order = checkoutCartOrder();

        $this->mock(PaymentGatewayClient::class, function ($mock) {
            $mock->shouldReceive('charge')->once()->andReturn(checkoutGatewayPayment([
                'id' => 987654,
                'status' => 'pending',
                'payment_type_id' => 'bank_transfer',
            ]));
        });

        Livewire::test(Checkout::class)
            ->call('pixOrBankSlipPayment', [
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
            ])
            ->assertHasNoErrors()
            ->assertRedirectContains(route('checkout.result', ['order' => $order->id]));

        $order->refresh();

        expect($order->status)->toBe(OrderStatusEnum::PENDING)
            ->and($order->payments)->toHaveCount(1)
            ->and($order->payments->first()->external_id)->toBe('987654')
            ->and($order->payments->first()->method)->toBe(PaymentMethodEnum::PIX)
            ->and($order->shippings)->toHaveCount(1);

        Mail::assertQueued(OrderCreatedMail::class);
    });
});
