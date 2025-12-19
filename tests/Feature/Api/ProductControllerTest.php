<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can list products.
     */
    public function test_user_can_list_products()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Device')->plainTextToken;

        $products = Product::factory()->count(5)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'products' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'barcode',
                            'sku',
                            'price',
                            'cost_price',
                            'tax',
                            'image',
                            'status',
                            'stock',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                        'from',
                        'to',
                    ],
                ],
            ]);
    }

    /**
     * Test user can search products.
     */
    public function test_user_can_search_products()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Device')->plainTextToken;

        Product::factory()->create(['name' => 'Test Product 1']);
        Product::factory()->create(['name' => 'Test Product 2']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/products?search=Test Product');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data.products')));
    }

    /**
     * Test user can get product details.
     */
    public function test_user_can_get_product_details()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Device')->plainTextToken;

        $product = Product::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'barcode',
                    'sku',
                    'price',
                    'cost_price',
                    'tax',
                    'image',
                    'status',
                    'stock',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    /**
     * Test user cannot get non-existent product.
     */
    public function test_user_cannot_get_non_existent_product()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Device')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/products/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Product not found',
            ]);
    }

    /**
     * Test user can search product by barcode.
     */
    public function test_user_can_search_product_by_barcode()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Device')->plainTextToken;

        $product = Product::factory()->create(['barcode' => '1234567890']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/products/barcode/search?barcode=1234567890');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'sku' => $product->sku,
                    'price' => $product->price,
                ],
            ]);
    }

    /**
     * Test user cannot access products without authentication.
     */
    public function test_user_cannot_access_products_without_authentication()
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401);
    }

    /**
     * Test user can filter products by stock status.
     */
    public function test_user_can_filter_products_by_stock_status()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Device')->plainTextToken;

        // Create products with different stock levels
        Product::factory()->create(['quantity' => 0]); // Out of stock
        Product::factory()->create(['quantity' => 5, 'minimum_stock' => 10]); // Low stock
        Product::factory()->create(['quantity' => 20, 'minimum_stock' => 5]); // In stock

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/products?in_stock=true');

        $response->assertStatus(200);
        
        $products = $response->json('data.products');
        $this->assertTrue(count($products) > 0);
        
        // All products should have quantity > 0
        foreach ($products as $product) {
            $this->assertGreaterThan(0, $product['stock']['quantity']);
        }
    }

    /**
     * Test user can filter products by store.
     */
    public function test_user_can_filter_products_by_store()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Device')->plainTextToken;

        $store = Store::factory()->create();
        $product = Product::factory()->create();

        // Create store stock
        StoreStock::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/products?store_id={$store->id}");

        $response->assertStatus(200);
        
        $products = $response->json('data.products');
        $this->assertTrue(count($products) > 0);
    }
}