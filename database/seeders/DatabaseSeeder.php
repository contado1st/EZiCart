<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin Account
        User::updateOrCreate(
            ['email' => 'admin@ezicart.com'],
            [
                'first_name'     => 'System',
                'last_name'      => 'Admin',
                'middle_initial' => 'A',
                'sex'            => 'Other',
                'contact_no'     => '09123456780',
                'birthday'       => '1990-01-01',
                'age'            => 34,
                'province'       => 'Laguna',
                'municipality'   => 'Majayjay',
                'barangay'       => 'Poblacion',
                'street_address' => '123 Admin Way',
                'role'           => 'admin',
                'status'         => 'approved',
                'password'       => Hash::make('password123'),
            ]
        );

        // 2. Buyer Account (Approved)
        User::updateOrCreate(
            ['email' => 'buyer@ezicart.com'],
            [
                'first_name'     => 'John',
                'last_name'      => 'Buyer',
                'middle_initial' => 'B',
                'sex'            => 'Male',
                'contact_no'     => '09123456781',
                'birthday'       => '1998-05-12',
                'age'            => 26,
                'province'       => 'Laguna',
                'municipality'   => 'Majayjay',
                'barangay'       => 'San Roque',
                'street_address' => '45 Market Rd.',
                'role'           => 'buyer',
                'status'         => 'approved',
                'password'       => Hash::make('password123'),
            ]
        );

        // 3. Seller Account (Approved)
        User::updateOrCreate(
            ['email' => 'seller@ezicart.com'],
            [
                'first_name'       => 'Sarah',
                'last_name'        => 'Seller',
                'middle_initial'   => 'S',
                'sex'              => 'Female',
                'contact_no'       => '09123456782',
                'birthday'         => '1995-11-20',
                'age'              => 29,
                'province'         => 'Laguna',
                'municipality'     => 'Majayjay',
                'barangay'         => 'Origuel',
                'street_address'   => '78 Merchant Ave.',
                'business_name'    => 'Sarah Essentials Store',
                'line_of_business' => 'Beauty & Personal Care',
                'role'             => 'seller',
                'status'           => 'approved',
                'password'         => Hash::make('password123'),
            ]
        );

        // 4. Courier Account (Approved)
        User::updateOrCreate(
            ['email' => 'courier@ezicart.com'],
            [
                'first_name'     => 'Carlos',
                'last_name'      => 'Courier',
                'middle_initial' => 'C',
                'sex'            => 'Male',
                'contact_no'     => '09123456783',
                'birthday'       => '1996-03-15',
                'age'            => 28,
                'province'       => 'Laguna',
                'municipality'   => 'Majayjay',
                'barangay'       => 'Malinao',
                'street_address' => '12 Logistics St.',
                'vehicle_type'   => 'Motorcycle',
                'plate_number'   => 'MC-4589',
                'role'           => 'courier',
                'status'         => 'approved',
                'password'       => Hash::make('password123'),
            ]
        );

        // 5. Pending Seller (To test Admin Approval flow)
        User::updateOrCreate(
            ['email' => 'pending.seller@ezicart.com'],
            [
                'first_name'       => 'Pending',
                'last_name'        => 'Merchant',
                'middle_initial'   => 'P',
                'sex'              => 'Male',
                'contact_no'       => '09123456784',
                'birthday'         => '2000-01-10',
                'age'              => 24,
                'province'         => 'Laguna',
                'municipality'     => 'Majayjay',
                'barangay'         => 'Poblacion',
                'street_address'   => '99 Waitlist St.',
                'business_name'    => 'Tech Gear Central',
                'line_of_business' => 'Electronics',
                'role'             => 'seller',
                'status'           => 'pending',
                'password'         => Hash::make('password123'),
            ]
        );
    }
}
