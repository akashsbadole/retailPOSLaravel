<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Create products with different stock levels for testing
        $testProducts = [
            [
                'name' => 'Laptop Computer',
                'description' => 'High-performance laptop for business use',
                'quantity' => 15,
                'minimum_stock' => 10,
                'stock_status' => 'normal'
            ],
            [
                'name' => 'Wireless Mouse',
                'description' => 'Ergonomic wireless mouse',
                'quantity' => 8,
                'minimum_stock' => 10,
                'stock_status' => 'low'
            ],
            [
                'name' => 'USB Keyboard',
                'description' => 'Mechanical keyboard with RGB lighting',
                'quantity' => 3,
                'minimum_stock' => 5,
                'stock_status' => 'low'
            ],
            [
                'name' => 'Monitor Stand',
                'description' => 'Adjustable monitor stand',
                'quantity' => 0,
                'minimum_stock' => 5,
                'stock_status' => 'out'
            ],
            [
                'name' => 'Webcam HD',
                'description' => 'High definition webcam for video calls',
                'quantity' => 25,
                'minimum_stock' => 10,
                'stock_status' => 'normal'
            ]
        ];

        foreach ($testProducts as $productData) {
            Product::create([
                'name' => $productData['name'],
                'description' => $productData['description'],
                'image' => '',
                'quantity' => $productData['quantity'],
                'minimum_stock' => $productData['minimum_stock'],
                'barcode' => $faker->unique()->ean13,
                'sku' => 'SKU-' . strtoupper($faker->unique()->lexify('??????')),
                'regular_price' => $faker->randomFloat(2, 50, 200),
                'price' => $faker->randomFloat(2, 40, 190),
                'cost_price' => $faker->randomFloat(2, 30, 150),
                'status' => true
            ]);
        }

        // Create additional random products
        for ($i = 0; $i < 5; $i++) {
            Product::create([
                'name' => ucwords($faker->word),
                'description' => $faker->sentence,
                'image' => '',
                'quantity' => $faker->numberBetween(5, 100),
                'minimum_stock' => $faker->numberBetween(5, 20),
                'barcode' => $faker->unique()->ean13,
                'sku' => 'SKU-' . strtoupper($faker->unique()->lexify('??????')),
                'regular_price' => $faker->randomFloat(2, 50, 200),
                'price' => $faker->randomFloat(2, 40, 190),
                'cost_price' => $faker->randomFloat(2, 30, 150),
                'status' => true
            ]);
        }
    }
}
