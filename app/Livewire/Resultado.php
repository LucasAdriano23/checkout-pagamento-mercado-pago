<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Order;

class Resultado extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order->load('payments', 'shippings');
    }

    public function render()
    {
        return view('livewire.resultado');
    }
}
