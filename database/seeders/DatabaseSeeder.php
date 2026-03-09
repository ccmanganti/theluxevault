<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;


class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    protected static ?string $password;

    public function run(): void
    {
        // User::factory(10)->create();

        // 
        $role = Role::create(['name' => 'Superadmin']);
        $user = User::factory()->create([
            'name' => 'Kristine Vowles',
            'email' => 'kristine@wh.theluxurylook.com',
            'password' => static::$password ??= Hash::make('password'),
        ]);

        $user->assignRole($role);
    }
}
