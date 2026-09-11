<x:mail::message>
    #Pedido criado

    O pedido {{ $order->id }} foi criado.

    Valor total: @money($order->total)

    Obrigado por comprar conosco!
</x:mail::message>
