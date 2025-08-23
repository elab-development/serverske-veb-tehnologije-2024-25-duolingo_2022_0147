<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name'  => 'Admin User',
            'email' => 'admin@mail.com',
        ]);

        User::factory()->count(5)->teacher()->create();

        User::factory()->count(50)->student()->create();
    }
}
