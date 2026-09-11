<?php

namespace App\Services;
use App\Models\User;
use Illuminate\Support\Str;

class UserService {

    public function store(array $user, array $adress): User
    {
        $existingUser = User::where('email', $user['email'])->first(['id','name','email']);

        $user = $existingUser ?? User::create([...$user, 'password' => bcrypt(Str::uuid())]);

        $addressExists = $user->addresses()
            ->where('zipcode', $adress['zipcode'] ?? null)
            ->where('address', $adress['address'] ?? null)
            ->where('number', $adress['number'] ?? null)
            ->exists();

        if (!$addressExists) {
            $user->addresses()->create($adress);
        }

        return $user;
    }
}
