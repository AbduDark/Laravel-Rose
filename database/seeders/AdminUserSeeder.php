<?php

// namespace Database\Seeders;

// use Illuminate\Database\Seeder;
// use App\Models\User;
// use Illuminate\Support\Facades\Hash;

// class AdminUserSeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      */
//     public function run(): void
//     {
//         // Check if admin user already exists
//         $admin = User::where('email', 'admin@roseacademy.com')->first();
        
//         if (!$admin) {
//             User::create([
//                 'name' => 'Admin',
//                 'email' => 'admin@roseacademy.com',
//                 'password' => Hash::make('admin123'),
//                 'is_admin' => true,
//                 'role' => 'admin',
//                 'email_verified_at' => now(),
//             ]);
//         } else {
//             // Update existing user to be admin
//             $admin->update([
//                 'is_admin' => true,
//                 'role' => 'admin'
//             ]);
//         }
//     }
// }
