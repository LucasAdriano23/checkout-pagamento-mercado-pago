<?php

use App\Enums\OrderStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Feature;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use MercadoPago\Net\MPResponse;
use MercadoPago\Resources\Payment as MercadoPagoPayment;

function orderService(): OrderService
{
    return app(OrderService::class);
}

function orderServiceGatewayPayment(array $attributes = []): MercadoPagoPayment
{
    $attributes = array_merge([
        'id' => 123456,
        'status' => 'approved',
        'payment_type_id' => 'credit_card',
        'installments' => 1,
        'date_approved' => null,
        'point_of_interaction' => null,
    ], $attributes);

    $payment = new MercadoPagoPayment();
    $payment->id = $attributes['id'];
    $payment->status = $attributes['status'];
    $payment->payment_type_id = $attributes['payment_type_id'];
    $payment->installments = $attributes['installments'];
    $payment->date_approved = $attributes['date_approved'];
    $payment->point_of_interaction = $attributes['point_of_interaction'];
    $payment->setResponse(new MPResponse(201, []));

    return $payment;
}

function orderServiceTransactionData(array $data): object
{
    return (object) ['transaction_data' => (object) $data];
}

function orderServiceAddress(array $overrides = []): array
{
    return array_merge([
        'zipcode' => '01310930',
        'address' => 'Avenida Paulista',
        'number' => '1578',
        'district' => 'Bela Vista',
        'city' => 'São Paulo',
        'state' => 'SP',
        'complement' => 'Sala 4',
    ], $overrides);
}

function orderServiceProduct(string $name): Product
{
    Brand::factory()->create();

    return Product::factory()->create([
        'category_id' => Category::factory(),
        'name' => $name,
    ]);
}

function orderServiceAttachSku(Order $order, string $productName, int $quantity): Sku
{
    $sku = Sku::factory()->create([
        'product_id' => orderServiceProduct($productName)->id,
    ]);
    $product = $sku->product;

    $order->skus()->attach([$sku->id => [
        'quantity' => $quantity,
        'unitary_price' => $sku->price,
        'product' => $product->toJson(),
    ]]);

    return $sku;
}

describe('getCartOrder', function () {
    it('retorna o pedido em carrinho da sessão atual', function () {
        $order = Order::factory()->create([
            'session_id' => session()->getId(),
            'status' => OrderStatusEnum::CART,
        ]);

        expect(orderService()->getCartOrder()->id)->toBe($order->id);
    });

    it('retorna null quando não há pedido em carrinho para a sessão', function () {
        Order::factory()->create([
            'session_id' => 'outra-sessao',
            'status' => OrderStatusEnum::CART,
        ]);

        expect(orderService()->getCartOrder())->toBeNull();
    });

    it('ignora pedidos da sessão que não estão em carrinho', function (OrderStatusEnum $status) {
        Order::factory()->create([
            'session_id' => session()->getId(),
            'status' => $status,
        ]);

        expect(orderService()->getCartOrder())->toBeNull();
    })->with([
        'pendente' => OrderStatusEnum::PENDING,
        'pago' => OrderStatusEnum::PAID,
        'cancelado' => OrderStatusEnum::CANCELED,
        'não aprovado' => OrderStatusEnum::REJECT,
    ]);

    it('encontra o carrinho pelo usuário autenticado mesmo em outra sessão', function () {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'session_id' => 'sessao-antiga',
            'status' => OrderStatusEnum::CART,
        ]);

        $this->actingAs($user);

        expect(orderService()->getCartOrder()->id)->toBe($order->id);
    });

    it('não devolve o carrinho de outro usuário', function () {
        Order::factory()->create([
            'user_id' => User::factory(),
            'session_id' => 'sessao-de-outro',
            'status' => OrderStatusEnum::CART,
        ]);

        $this->actingAs(User::factory()->create());

        expect(orderService()->getCartOrder())->toBeNull();
    });

    it('já traz skus, produtos e características carregados', function () {
        $order = Order::factory()->create([
            'session_id' => session()->getId(),
            'status' => OrderStatusEnum::CART,
        ]);

        $sku = orderServiceAttachSku($order, 'Cadeira Gamer', 1);
        $sku->features()->attach([Feature::factory()->create()->id => ['value' => 'preta']]);

        $cart = orderService()->getCartOrder();

        expect($cart->relationLoaded('skus'))->toBeTrue()
            ->and($cart->skus->first()->relationLoaded('product'))->toBeTrue()
            ->and($cart->skus->first()->relationLoaded('features'))->toBeTrue();
    });
});

describe('buildDescription', function () {
    it('lista a quantidade de cada produto do pedido', function () {
        $order = Order::factory()->create(['status' => OrderStatusEnum::CART]);

        orderServiceAttachSku($order, 'Cadeira Gamer', 2);
        orderServiceAttachSku($order, 'Mesa de Escritório', 1);

        expect(orderService()->buildDescription($order->load('skus.product')))
            ->toBe('2x Cadeira Gamer, 1x Mesa de Escritório');
    });

    it('soma as quantidades de skus do mesmo produto', function () {
        $order = Order::factory()->create(['status' => OrderStatusEnum::CART]);

        $product = orderServiceProduct('Cadeira Gamer');

        foreach ([2, 3] as $quantity) {
            $sku = Sku::factory()->create(['product_id' => $product->id]);
            $order->skus()->attach([$sku->id => [
                'quantity' => $quantity,
                'unitary_price' => $sku->price,
                'product' => $product->toJson(),
            ]]);
        }

        expect(orderService()->buildDescription($order->load('skus.product')))
            ->toBe('5x Cadeira Gamer');
    });

    it('usa o número do pedido como descrição quando não há skus', function () {
        $order = Order::factory()->create(['status' => OrderStatusEnum::CART]);

        expect(orderService()->buildDescription($order->load('skus.product')))
            ->toBe('Pedido '.$order->id);
    });
});

describe('update', function () {
    it('traduz o status do Mercado Pago para o status do pedido', function (?string $gatewayStatus, OrderStatusEnum $expected) {
        $order = Order::factory()->create(['status' => OrderStatusEnum::CART]);

        $updated = orderService()->update(
            $order->id,
            orderServiceGatewayPayment(['status' => $gatewayStatus]),
            User::factory()->create(),
            orderServiceAddress()
        );

        expect($updated->status)->toBe($expected);
    })->with([
        'aprovado' => ['approved', OrderStatusEnum::PAID],
        'pendente' => ['pending', OrderStatusEnum::PENDING],
        'em análise' => ['in_process', OrderStatusEnum::PENDING],
        'autorizado' => ['authorized', OrderStatusEnum::PENDING],
        'recusado' => ['rejected', OrderStatusEnum::REJECT],
        'cancelado' => ['cancelled', OrderStatusEnum::CANCELED],
        'desconhecido' => ['qualquer_outro', OrderStatusEnum::PENDING],
        'ausente' => [null, OrderStatusEnum::PENDING],
    ]);

    it('vincula o pedido ao usuário informado', function () {
        $order = Order::factory()->create(['user_id' => null, 'status' => OrderStatusEnum::CART]);
        $user = User::factory()->create();

        $updated = orderService()->update($order->id, orderServiceGatewayPayment(), $user, orderServiceAddress());

        expect($updated->user_id)->toBe($user->id);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $user->id]);
    });

    it('registra o pagamento com id externo e parcelas', function () {
        $order = Order::factory()->create(['status' => OrderStatusEnum::CART]);

        $updated = orderService()->update(
            $order->id,
            orderServiceGatewayPayment(['id' => 987654, 'installments' => 6]),
            User::factory()->create(),
            orderServiceAddress()
        );

        expect($updated->payments)->toHaveCount(1)
            ->and($updated->payments->first()->external_id)->toBe('987654')
            ->and($updated->payments->first()->installments)->toBe(6);
    });

    it('traduz o tipo de pagamento do gateway para o método do pedido', function (?string $paymentTypeId, PaymentMethodEnum $expected) {
        $order = Order::factory()->create(['status' => OrderStatusEnum::CART]);

        $updated = orderService()->update(
            $order->id,
            orderServiceGatewayPayment(['payment_type_id' => $paymentTypeId]),
            User::factory()->create(),
            orderServiceAddress()
        );

        expect($updated->payments->first()->method)->toBe($expected);
    })->with([
        'cartão de crédito' => ['credit_card', PaymentMethodEnum::CREDIT_CARD],
        'cartão de débito' => ['debit_card', PaymentMethodEnum::CREDIT_CARD],
        'pix' => ['bank_transfer', PaymentMethodEnum::PIX],
        'boleto' => ['ticket', PaymentMethodEnum::BOLETO],
        'desconhecido' => ['qualquer_outro', PaymentMethodEnum::CREDIT_CARD],
        'ausente' => [null, PaymentMethodEnum::CREDIT_CARD],
    ]);

    it('guarda o QR Code e o link do boleto devolvidos pelo gateway', function () {
        $order = Order::factory()->create(['status' => OrderStatusEnum::CART]);

        $updated = orderService()->update(
            $order->id,
            orderServiceGatewayPayment([
                'payment_type_id' => 'bank_transfer',
                'status' => 'pending',
                'point_of_interaction' => orderServiceTransactionData([
                    'qr_code' => '00020126580014br.gov.bcb.pix',
                    'qr_code_base64' => 'iVBORw0KGgoAAAANSUg==',
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/987/ticket',
                ]),
            ]),
            User::factory()->create(),
            orderServiceAddress()
        );

        $payment = $updated->payments->first();

        expect($payment->qr_code)->toBe('00020126580014br.gov.bcb.pix')
            ->and($payment->qr_code_64)->toBe('iVBORw0KGgoAAAANSUg==')
            ->and($payment->ticket_url)->toBe('https://www.mercadopago.com.br/payments/987/ticket');
    });

    it('deixa QR Code e boleto nulos quando o gateway não devolve point_of_interaction', function () {
        $order = Order::factory()->create(['status' => OrderStatusEnum::CART]);

        $updated = orderService()->update($order->id, orderServiceGatewayPayment(), User::factory()->create(), orderServiceAddress());

        $payment = $updated->payments->first();

        expect($payment->qr_code)->toBeNull()
            ->and($payment->qr_code_64)->toBeNull()
            ->and($payment->ticket_url)->toBeNull();
    });

    it('converte a data de aprovação do gateway', function () {
        $order = Order::factory()->create(['status' => OrderStatusEnum::CART]);

        $updated = orderService()->update(
            $order->id,
            orderServiceGatewayPayment(['date_approved' => '2026-09-12T10:30:00.000-03:00']),
            User::factory()->create(),
            orderServiceAddress()
        );

        expect($updated->payments->first()->approved_at)->toBe('2026-09-12 10:30:00');
    });

    it('deixa a data de aprovação nula quando o pagamento não foi aprovado', function () {
        $order = Order::factory()->create(['status' => OrderStatusEnum::CART]);

        $updated = orderService()->update(
            $order->id,
            orderServiceGatewayPayment(['status' => 'pending', 'date_approved' => null]),
            User::factory()->create(),
            orderServiceAddress()
        );

        expect($updated->payments->first()->approved_at)->toBeNull();
    });

    it('grava o status do pagamento sempre como pendente', function () {
        // Comportamento atual: update() passa $order->status->value (int) para
        // PaymentStatusEnum::parse(), que espera a string do Mercado Pago e cai no default.
        $order = Order::factory()->create(['status' => OrderStatusEnum::CART]);

        $updated = orderService()->update(
            $order->id,
            orderServiceGatewayPayment(['status' => 'approved']),
            User::factory()->create(),
            orderServiceAddress()
        );

        expect($updated->status)->toBe(OrderStatusEnum::PAID)
            ->and($updated->payments->first()->status)->toBe(PaymentStatusEnum::PENDING);
    });

    it('cria o frete com o endereço informado', function () {
        $order = Order::factory()->create(['status' => OrderStatusEnum::CART]);
        $address = orderServiceAddress();

        $updated = orderService()->update($order->id, orderServiceGatewayPayment(), User::factory()->create(), $address);

        expect($updated->shippings)->toHaveCount(1);
        $this->assertDatabaseHas('shippings', [
            'order_id' => $order->id,
            'address' => $address['address'],
            'number' => $address['number'],
            'complement' => $address['complement'],
            'district' => $address['district'],
            'city' => $address['city'],
            'state' => $address['state'],
            'zipcode' => $address['zipcode'],
        ]);
    });

    it('devolve o pedido com pagamentos e fretes carregados', function () {
        $order = Order::factory()->create(['status' => OrderStatusEnum::CART]);

        $updated = orderService()->update($order->id, orderServiceGatewayPayment(), User::factory()->create(), orderServiceAddress());

        expect($updated->relationLoaded('payments'))->toBeTrue()
            ->and($updated->relationLoaded('shippings'))->toBeTrue();
    });

    it('lança ModelNotFoundException quando o pedido não existe', function () {
        orderService()->update(
            987654321,
            orderServiceGatewayPayment(),
            User::factory()->create(),
            orderServiceAddress()
        );
    })->throws(ModelNotFoundException::class);
});
