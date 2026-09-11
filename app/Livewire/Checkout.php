<?php

namespace App\Livewire;

use App\Enums\CheckoutStepsEnum;
use App\Exceptions\PaymentException;
use App\Livewire\Forms\AddressForm;
use App\Livewire\Forms\UserForm;
use App\Services\CheckoutService;
use App\Services\OrderService;
use Exception;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

class Checkout extends Component
{
    public array $cart = [];
    public int $step;
    public int | null $method = null;
    public UserForm $user;
    public AddressForm $address;

    public function mount(OrderService $orderService)
    {
        $this->step = CheckoutStepsEnum::PAYMENT->value;
        $this->cart = $orderService->getCartOrder()->toArray();
        $this->user->email = config('payment.mercadopago.buyer_email');
    }

    public function findAddress(){
        $this->address->findAddress();
    }

    public function submitInformationStep()
    {
        $this->user->validate();
        $this->address->validate();
        $this->step = CheckoutStepsEnum::SHIPPING->value;
    }

    public function submitShippingStep()
    {
        $this->step = CheckoutStepsEnum::PAYMENT->value;
    }

    public function creditCardPayment(CheckoutService $checkoutService, array $data)
    {
        try {
            $checkoutService->creditCardPayment($data);

            $this->responsePayment();

        } catch(PaymentException $e){
            $this->addError('payment', $e->getMessage());
        } catch(Exception $e){
            $this->addError('payment', $e->getMessage());
        }
    }

    public function pixOrBankSlipPayment(CheckoutService $checkoutService, array $data)
    {
        try {
            $checkoutService->pixOrBankSlipPayment($data);

            $this->responsePayment();

        } catch(PaymentException $e){
            $this->addError('payment', $e->getMessage());
        } catch(Exception $e){
            $this->addError('payment', $e->getMessage());
        }
    }

    public function responsePayment()
    {
        $url = URL::temporarySignedRoute(
            name: 'checkout.result',
            expiration:3600,
            parameters:[
                'order' => $this->cart['id']
            ]
        );

        $this->redirect($url);
    }

    public function render()
    {
        return view('livewire.checkout');
    }
}
