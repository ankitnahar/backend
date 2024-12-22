<?php

use Illuminate\Database\Seeder;
use App\Models\User;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        App\Models\User::create([
        	'username'		=> 'bdms',
        	'password'		=> app('hash')->make('password'),
        	'first_name'	=> 'Test',
        	'last_name'		=> 'User',
        	'role'			=> 'admin'
        	]);
    }
}
