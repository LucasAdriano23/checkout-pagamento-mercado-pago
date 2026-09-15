<?php

use App\Models\Address;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;

function userService(): UserService
{
    return app(UserService::class);
}

function userServiceContact(array $overrides = []): array
{
    return array_merge([
        'name' => 'Maria Silva',
        'email' => 'maria@example.com',
    ], $overrides);
}

function userServiceAddressData(array $overrides = []): array
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

describe('usuário', function () {
    it('cria o usuário quando o e-mail ainda não existe', function () {
        $user = userService()->store(userServiceContact(), userServiceAddressData());

        expect($user->name)->toBe('Maria Silva')
            ->and($user->email)->toBe('maria@example.com');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['email' => 'maria@example.com', 'name' => 'Maria Silva']);
    });

    it('define uma senha aleatória e com hash para o usuário criado', function () {
        userService()->store(userServiceContact(), userServiceAddressData());

        $password = User::firstWhere('email', 'maria@example.com')->password;

        expect($password)->not->toBeEmpty()
            ->and(Hash::isHashed($password))->toBeTrue();
    });

    it('reaproveita o usuário existente com o mesmo e-mail em vez de criar outro', function () {
        $existing = User::factory()->create(['email' => 'maria@example.com', 'name' => 'Maria Antiga']);

        $user = userService()->store(userServiceContact(), userServiceAddressData());

        expect($user->id)->toBe($existing->id);
        $this->assertDatabaseCount('users', 1);
    });

    it('não sobrescreve o nome do usuário existente com o nome informado no checkout', function () {
        User::factory()->create(['email' => 'maria@example.com', 'name' => 'Maria Antiga']);

        $user = userService()->store(userServiceContact(['name' => 'Maria Nova']), userServiceAddressData());

        expect($user->name)->toBe('Maria Antiga');
        $this->assertDatabaseHas('users', ['email' => 'maria@example.com', 'name' => 'Maria Antiga']);
    });

    it('diferencia usuários por e-mail', function () {
        User::factory()->create(['email' => 'outra@example.com']);

        userService()->store(userServiceContact(), userServiceAddressData());

        $this->assertDatabaseCount('users', 2);
    });
});

describe('endereço', function () {
    it('cria o endereço vinculado ao usuário', function () {
        $user = userService()->store(userServiceContact(), userServiceAddressData());

        $this->assertDatabaseCount('addresses', 1);
        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'zipcode' => '01310930',
            'address' => 'Avenida Paulista',
            'number' => '1578',
            'district' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
            'complement' => 'Sala 4',
        ]);
    });

    it('não duplica o endereço quando cep, logradouro e número se repetem', function () {
        userService()->store(userServiceContact(), userServiceAddressData());
        userService()->store(userServiceContact(), userServiceAddressData());

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('addresses', 1);
    });

    it('ignora diferenças fora de cep, logradouro e número ao deduplicar', function (array $overrides) {
        userService()->store(userServiceContact(), userServiceAddressData());
        userService()->store(userServiceContact(), userServiceAddressData($overrides));

        $this->assertDatabaseCount('addresses', 1);
    })->with([
        'outro bairro' => [['district' => 'Consolação']],
        'outra cidade' => [['city' => 'Campinas']],
        'outro complemento' => [['complement' => 'Apto 12']],
    ]);

    it('cria um novo endereço quando cep, logradouro ou número mudam', function (array $overrides) {
        userService()->store(userServiceContact(), userServiceAddressData());
        userService()->store(userServiceContact(), userServiceAddressData($overrides));

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('addresses', 2);
    })->with([
        'outro cep' => [['zipcode' => '04538133']],
        'outro logradouro' => [['address' => 'Rua Augusta']],
        'outro número' => [['number' => '900']],
    ]);

    it('considera apenas os endereços do próprio usuário ao deduplicar', function () {
        Address::factory()->create([
            'user_id' => User::factory()->create(['email' => 'outra@example.com']),
            ...userServiceAddressData(),
        ]);

        userService()->store(userServiceContact(), userServiceAddressData());

        $this->assertDatabaseCount('addresses', 2);
    });

    it('adiciona o endereço a um usuário que já existia sem endereço igual', function () {
        $existing = User::factory()->create(['email' => 'maria@example.com']);

        userService()->store(userServiceContact(), userServiceAddressData());

        $this->assertDatabaseHas('addresses', ['user_id' => $existing->id, 'zipcode' => '01310930']);
    });
});
