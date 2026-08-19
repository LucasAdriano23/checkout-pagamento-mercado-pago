<?php

namespace App\Livewire;

use App\Enums\CheckoutStepsEnum;
use App\Livewire\Forms\AddressForm;
use App\Livewire\Forms\UserForm;
use App\Services\CheckoutService;
use Livewire\Component;

class Checkout extends Component
{
    public array $cart = [];
    public int $step;
    public int | null $method = null;
    public UserForm $user;
    public AddressForm $address;

    public function mount(CheckoutService $checkoutService)
    {
        $this->step = CheckoutStepsEnum::PAYMENT->value;
        $this->cart = $checkoutService->loadCart();
        $this->user->email = config('payment.mercadopago.buyer.buyer_email');
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
        return $checkoutService->creditCardPayment($data);
    }

    public function pixOrBankSlipPayment(array $data)
    {
        dd($data);
    }

    public function render()
    {
        return view('livewire.checkout');
    }
}
