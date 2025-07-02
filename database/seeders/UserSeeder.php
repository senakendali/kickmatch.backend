<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        
        $data['name'] =  'Administrator';
        $data['email'] =  'admin@mandalaarena.id';
        $data['password'] = Hash::make('Lumberjack2000');
       
        User::firstOrCreate($data);

        

       
    }
}
