import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm'
import clipboard from '@ryangjchandler/alpine-clipboard'
import checkout from "./checkout/checkout";

Alpine.plugin(clipboard);
Alpine.data('checkout', checkout);

Livewire.start();
