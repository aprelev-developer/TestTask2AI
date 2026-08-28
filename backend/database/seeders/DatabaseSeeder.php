<?php

namespace Database\Seeders;

use App\Models\ReferencePayment;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a demo reference payment so README's curl examples work
     * out of the box after `make fresh` / first `make up`.
     */
    public function run(): void
    {
        ReferencePayment::query()->updateOrCreate(
            ['id' => '11111111-1111-1111-1111-111111111111'],
            [
                'address' => 'addr-real',
                'amount' => '1.00000000',
                'network' => 'BTC',
                'allowed_scripts' => ['https://payments.example/checkout.js'],
            ],
        );
    }
}
