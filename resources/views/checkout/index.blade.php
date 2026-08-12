<x-app-layout>
    <div class="relative min-h-screen">
        <div class="fixed left-0 top-0 hidden h-full w-1/2 bg-tertiary-900 lg:block" aria-hidden="true"></div>
        <div class="fixed right-0 top-0 hidden h-full w-1/2 bg-tertiary-800 lg:block" aria-hidden="true"></div>

        <div class="relative mx-auto grid max-w-7xl grid-cols-1 gap-x-16 lg:grid-cols-2 lg:px-8 lg:pt-16">
            <section aria-labelledby="summary-heading" class="bg-tertiary-800 py-12 text-indigo-300 md:px-10 lg:col-start-2 lg:row-start-1 lg:mx-auto lg:w-full lg:max-w-lg lg:bg-transparent lg:px-0 lg:pb-24 lg:pt-0">
                <div class="mx-auto max-w-2xl px-4 lg:max-w-none lg:px-0">
                    <dl>
                        <dt class="text-lg font-medium text-primary-200">Resumo</dt>
                    </dl>
                        <x-checkout.product-list>
                            <x-checkout.product-item
                            name="High Wall tote"
                            price="210,00"
                            image="https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-01-related-product-01.jpg"
                            :features="[
                                'White and black',
                                '15L'
                            ]"
                            />
                        </x-checkout.product-list>

                    <dl class="space-y-6 border-t border-white border-opacity-10 pt-6 text-sm font-medium">
                        <x-checkout.summary-item title="Subtotal" value="570,00"/>
                        <x-checkout.summary-item title="Frete" value="50,00"/>
                        <x-checkout.summary-item title="Taxas" value="50,00"/>
                        <x-checkout.summary-item title="Total" value="620,00" :is-last="true"/>
                    </dl>
                </div>
            </section>
            <section aria-labelledby="payment-and-shipping-heading" class="py-16 lg:col-start-1 lg:row-start-1 lg:mx-auto lg:w-full lg:max-w-lg lg:pb-24 lg:pt-0">
                <form>
                    <div class="mx-auto max-w-2xl px-4 lg:max-w-none lg:px-0">
                        <div>
                            <x-section-title title="Informações de Contato"/>
                            <div class="mt-6">
                                <x-input-label for="email-adress" value="Email address"/>
                                <div class="mt-1">
                                    <x-text-input type="email"
                                    id="email-address"
                                    name="email"
                                    autocomplete="email"
                                    placeholder="Digite seu email"
                                    />
                                </div>
                            </div>
                            <div class="mt-10">
                                <x-section-title title="Detalhes do Pagamento"/>
                                <div class="mt-6 grid grid-cols-3 gap-x-4 gap-y-6 sm:grid-cols-4">
                                    <div class="col-span-3 sm:col-span-4">
                                        <x-input-label for="card-number" value="Número do cartão"/>
                                        <div class="mt-1">
                                            <x-text-input
                                                type="text"
                                                id="card-number"
                                                name="card-number"
                                                placeholder="Número do cartão"
                                            />
                                        </div>
                                    </div>
                                    <div class="col-span-2 sm:col-span-3">
                                        <x-input-label for="expiration-date" value="Data expiração"/>
                                        <div class="mt-1">
                                            <x-text-input
                                            type="text"
                                            id="expiration-date"
                                            name="expiration-date"
                                            placeholder="MM / AA"
                                            />
                                        </div>
                                    </div>
                                    <div class="">
                                        <x-input-label for="cvc" value="cvc"/>
                                        <div class="mt-1">
                                            <x-text-input
                                            type="text"
                                            id="cvc"
                                            name="cvc"
                                            placeholder="CVC"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-10">
                                <x-section-title title="Dados do Cliente"/>
                                <div class="mt-6 grid grid-cols-3 gap-x-4 gap-y-6 sm:grid-cols-4">
                                    <div class="col-span-5 sm:col-span-6">
                                        <x-input-label for="client-name" value="Nome"/>
                                        <div class="mt-1">
                                            <x-text-input
                                            type="text"
                                            id="client-name"
                                            name="client-name"
                                            placeholder="Digite seu nome"/>
                                        </div>
                                    </div>
                                    <div class="col-span-5 sm:col-span-6">
                                        <x-input-label for="client-cpf" value="Cpf"/>
                                        <div class="mt-1">
                                            <x-text-input
                                            type="text"
                                            id="client-cpf"
                                            name="client-cof"
                                            placeholder="Digite seu cpf"/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
