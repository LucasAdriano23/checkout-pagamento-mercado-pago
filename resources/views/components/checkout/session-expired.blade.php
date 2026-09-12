<div class="relative mx-auto flex max-w-7xl items-center justify-center px-4 py-24 lg:px-8">
    <div class="mx-auto max-w-md rounded-lg bg-tertiary-800 p-8 text-center text-indigo-300 shadow-lg">
        <svg class="mx-auto h-12 w-12 text-primary-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>

        <h2 class="mt-6 text-lg font-medium text-white">Sua sessão expirou</h2>

        <p class="mt-2 text-sm">
            Não encontramos seu carrinho. Comece a compra novamente para retomar o checkout.
        </p>

        <a href="{{ url('/') }}"
           class="mt-8 inline-flex items-center rounded-md border border-transparent bg-gray-200 px-8 py-4 text-xs font-semibold uppercase tracking-widest text-gray-800 transition duration-150 ease-in-out hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            Voltar ao início
        </a>
    </div>
</div>
