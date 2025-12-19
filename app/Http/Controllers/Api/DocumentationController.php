<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DocumentationController extends Controller
{
    /**
     * Display API documentation
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('api.documentation');
    }

    /**
     * Get OpenAPI specification
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function openapi()
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'SuperMart Mobile POS API',
                'description' => 'RESTful API for SuperMart Mobile Point of Sale system',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'SuperMart Support',
                    'email' => 'support@supermart.com',
                ],
                'license' => [
                    'name' => 'MIT',
                    'url' => 'https://opensource.org/licenses/MIT',
                ],
            ],
            'servers' => [
                [
                    'url' => config('app.url') . '/api',
                    'description' => 'Production server',
                ],
                [
                    'url' => 'http://localhost:8000/api',
                    'description' => 'Development server',
                ],
            ],
            'paths' => $this->getPaths(),
            'components' => $this->getComponents(),
            'security' => [
                [
                    'bearerAuth' => [],
                ],
            ],
            'tags' => [
                [
                    'name' => 'Authentication',
                    'description' => 'Authentication endpoints',
                ],
                [
                    'name' => 'Products',
                    'description' => 'Product management endpoints',
                ],
                [
                    'name' => 'Customers',
                    'description' => 'Customer management endpoints',
                ],
                [
                    'name' => 'Orders',
                    'description' => 'Order management endpoints',
                ],
                [
                    'name' => 'Payments',
                    'description' => 'Payment processing endpoints',
                ],
                [
                    'name' => 'Stores',
                    'description' => 'Store management endpoints',
                ],
                [
                    'name' => 'Users',
                    'description' => 'User management endpoints',
                ],
            ],
        ];

        return response()->json($spec);
    }

    /**
     * Get API paths
     *
     * @return array
     */
    private function getPaths()
    {
        return [
            '/auth/login' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => 'User login',
                    'description' => 'Authenticate user and return access token',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['email', 'password', 'device_name'],
                                    'properties' => [
                                        'email' => [
                                            'type' => 'string',
                                            'format' => 'email',
                                            'description' => 'User email address',
                                        ],
                                        'password' => [
                                            'type' => 'string',
                                            'minLength' => 6,
                                            'description' => 'User password',
                                        ],
                                        'device_name' => [
                                            'type' => 'string',
                                            'maxLength' => 255,
                                            'description' => 'Device name for token identification',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Login successful',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => [
                                                'type' => 'boolean',
                                                'example' => true,
                                            ],
                                            'message' => [
                                                'type' => 'string',
                                                'example' => 'Login successful',
                                            ],
                                            'data' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'user' => [
                                                        '$ref' => '#/components/schemas/User',
                                                    ],
                                                    'token' => [
                                                        'type' => 'string',
                                                        'example' => '1|abc123def456...',
                                                    ],
                                                    'token_type' => [
                                                        'type' => 'string',
                                                        'example' => 'Bearer',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Invalid credentials',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error',
                                    ],
                                ],
                            ],
                        ],
                        '422' => [
                            'description' => 'Validation error',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/ValidationError',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/auth/logout' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => 'User logout',
                    'description' => 'Logout user and revoke current token',
                    'security' => [
                        ['bearerAuth' => []],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Logout successful',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Success',
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Unauthenticated',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/auth/user' => [
                'get' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Get current user',
                    'description' => 'Get authenticated user information',
                    'security' => [
                        ['bearerAuth' => []],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'User information retrieved successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => [
                                                'type' => 'boolean',
                                                'example' => true,
                                            ],
                                            'data' => [
                                                '$ref' => '#/components/schemas/User',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Unauthenticated',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/products' => [
                'get' => [
                    'tags' => ['Products'],
                    'summary' => 'List products',
                    'description' => 'Get paginated list of products with optional filtering',
                    'security' => [
                        ['bearerAuth' => []],
                    ],
                    'parameters' => [
                        [
                            'name' => 'search',
                            'in' => 'query',
                            'description' => 'Search term for product name, description, barcode, or SKU',
                            'schema' => [
                                'type' => 'string',
                            ],
                        ],
                        [
                            'name' => 'status',
                            'in' => 'query',
                            'description' => 'Filter by product status (true for active, false for inactive)',
                            'schema' => [
                                'type' => 'boolean',
                            ],
                        ],
                        [
                            'name' => 'in_stock',
                            'in' => 'query',
                            'description' => 'Filter to only include products in stock',
                            'schema' => [
                                'type' => 'boolean',
                            ],
                        ],
                        [
                            'name' => 'low_stock',
                            'in' => 'query',
                            'description' => 'Filter to only include products with low stock',
                            'schema' => [
                                'type' => 'boolean',
                            ],
                        ],
                        [
                            'name' => 'store_id',
                            'in' => 'query',
                            'description' => 'Filter by store ID',
                            'schema' => [
                                'type' => 'integer',
                            ],
                        ],
                        [
                            'name' => 'per_page',
                            'in' => 'query',
                            'description' => 'Number of items per page (1-100)',
                            'schema' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 100,
                                'default' => 15,
                            ],
                        ],
                        [
                            'name' => 'page',
                            'in' => 'query',
                            'description' => 'Page number',
                            'schema' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'default' => 1,
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Products retrieved successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => [
                                                'type' => 'boolean',
                                                'example' => true,
                                            ],
                                            'data' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'products' => [
                                                        'type' => 'array',
                                                        'items' => [
                                                            '$ref' => '#/components/schemas/Product',
                                                        ],
                                                    ],
                                                    'pagination' => [
                                                        '$ref' => '#/components/schemas/Pagination',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Unauthenticated',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/products/{id}' => [
                'get' => [
                    'tags' => ['Products'],
                    'summary' => 'Get product details',
                    'description' => 'Get detailed information about a specific product',
                    'security' => [
                        ['bearerAuth' => []],
                    ],
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'description' => 'Product ID',
                            'schema' => [
                                'type' => 'integer',
                            ],
                        ],
                        [
                            'name' => 'store_id',
                            'in' => 'query',
                            'description' => 'Store ID for stock information',
                            'schema' => [
                                'type' => 'integer',
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Product details retrieved successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => [
                                                'type' => 'boolean',
                                                'example' => true,
                                            ],
                                            'data' => [
                                                '$ref' => '#/components/schemas/Product',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '404' => [
                            'description' => 'Product not found',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error',
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Unauthenticated',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/products/barcode/search' => [
                'get' => [
                    'tags' => ['Products'],
                    'summary' => 'Search by barcode or SKU',
                    'description' => 'Find product by barcode or SKU',
                    'security' => [
                        ['bearerAuth' => []],
                    ],
                    'parameters' => [
                        [
                            'name' => 'barcode',
                            'in' => 'query',
                            'required' => true,
                            'description' => 'Barcode or SKU to search for',
                            'schema' => [
                                'type' => 'string',
                            ],
                        ],
                        [
                            'name' => 'store_id',
                            'in' => 'query',
                            'description' => 'Store ID for stock information',
                            'schema' => [
                                'type' => 'integer',
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Product found',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => [
                                                'type' => 'boolean',
                                                'example' => true,
                                            ],
                                            'data' => [
                                                '$ref' => '#/components/schemas/Product',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '404' => [
                            'description' => 'Product not found',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error',
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Unauthenticated',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            // Add more endpoints as needed...
        ];
    }

    /**
     * Get API components
     *
     * @return array
     */
    private function getComponents()
    {
        return [
            'schemas' => [
                'User' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'example' => 1,
                        ],
                        'name' => [
                            'type' => 'string',
                            'example' => 'John Doe',
                        ],
                        'email' => [
                            'type' => 'string',
                            'format' => 'email',
                            'example' => 'john@example.com',
                        ],
                        'store_id' => [
                            'type' => 'integer',
                            'nullable' => true,
                            'example' => 1,
                        ],
                        'roles' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                            'example' => ['cashier', 'admin'],
                        ],
                        'permissions' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                            'example' => ['api.access', 'orders.create'],
                        ],
                    ],
                ],
                'Product' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'example' => 1,
                        ],
                        'name' => [
                            'type' => 'string',
                            'example' => 'Sample Product',
                        ],
                        'description' => [
                            'type' => 'string',
                            'nullable' => true,
                            'example' => 'Product description',
                        ],
                        'barcode' => [
                            'type' => 'string',
                            'nullable' => true,
                            'example' => '1234567890',
                        ],
                        'sku' => [
                            'type' => 'string',
                            'nullable' => true,
                            'example' => 'SKU001',
                        ],
                        'price' => [
                            'type' => 'number',
                            'format' => 'decimal',
                            'example' => 99.99,
                        ],
                        'cost_price' => [
                            'type' => 'number',
                            'format' => 'decimal',
                            'example' => 50.00,
                        ],
                        'tax' => [
                            'type' => 'number',
                            'format' => 'decimal',
                            'example' => 5.00,
                        ],
                        'image' => [
                            'type' => 'string',
                            'nullable' => true,
                            'example' => 'http://example.com/product.jpg',
                        ],
                        'status' => [
                            'type' => 'boolean',
                            'example' => true,
                        ],
                        'stock' => [
                            'type' => 'object',
                            'properties' => [
                                'quantity' => [
                                    'type' => 'integer',
                                    'example' => 100,
                                ],
                                'minimum_stock' => [
                                    'type' => 'integer',
                                    'example' => 10,
                                ],
                                'is_low_stock' => [
                                    'type' => 'boolean',
                                    'example' => false,
                                ],
                                'is_out_of_stock' => [
                                    'type' => 'boolean',
                                    'example' => false,
                                ],
                            ],
                        ],
                    ],
                ],
                'Customer' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'example' => 1,
                        ],
                        'first_name' => [
                            'type' => 'string',
                            'example' => 'John',
                        ],
                        'last_name' => [
                            'type' => 'string',
                            'example' => 'Doe',
                        ],
                        'full_name' => [
                            'type' => 'string',
                            'example' => 'John Doe',
                        ],
                        'email' => [
                            'type' => 'string',
                            'format' => 'email',
                            'nullable' => true,
                            'example' => 'john@example.com',
                        ],
                        'phone' => [
                            'type' => 'string',
                            'nullable' => true,
                            'example' => '+1234567890',
                        ],
                        'address' => [
                            'type' => 'string',
                            'nullable' => true,
                            'example' => '123 Main St, City, State',
                        ],
                    ],
                ],
                'Order' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'example' => 1,
                        ],
                        'customer_id' => [
                            'type' => 'integer',
                            'nullable' => true,
                            'example' => 1,
                        ],
                        'total_price' => [
                            'type' => 'number',
                            'format' => 'decimal',
                            'example' => 199.99,
                        ],
                        'discount_amount' => [
                            'type' => 'number',
                            'format' => 'decimal',
                            'example' => 10.00,
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['pending', 'completed', 'cancelled'],
                            'example' => 'pending',
                        ],
                        'items_count' => [
                            'type' => 'integer',
                            'example' => 3,
                        ],
                        'total_paid' => [
                            'type' => 'number',
                            'format' => 'decimal',
                            'example' => 199.99,
                        ],
                        'remaining_amount' => [
                            'type' => 'number',
                            'format' => 'decimal',
                            'example' => 0.00,
                        ],
                    ],
                ],
                'Payment' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'example' => 1,
                        ],
                        'order_id' => [
                            'type' => 'integer',
                            'example' => 1,
                        ],
                        'amount' => [
                            'type' => 'number',
                            'format' => 'decimal',
                            'example' => 199.99,
                        ],
                        'payment_method' => [
                            'type' => 'string',
                            'enum' => ['cash', 'card', 'upi', 'bank_transfer', 'cheque'],
                            'example' => 'cash',
                        ],
                        'notes' => [
                            'type' => 'string',
                            'nullable' => true,
                            'example' => 'Payment notes',
                        ],
                    ],
                ],
                'Store' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'example' => 1,
                        ],
                        'name' => [
                            'type' => 'string',
                            'example' => 'Main Store',
                        ],
                        'code' => [
                            'type' => 'string',
                            'example' => 'STORE001',
                        ],
                        'address' => [
                            'type' => 'string',
                            'nullable' => true,
                            'example' => '123 Main St, City, State',
                        ],
                        'phone' => [
                            'type' => 'string',
                            'nullable' => true,
                            'example' => '+1234567890',
                        ],
                        'email' => [
                            'type' => 'string',
                            'format' => 'email',
                            'nullable' => true,
                            'example' => 'store@example.com',
                        ],
                        'status' => [
                            'type' => 'boolean',
                            'example' => true,
                        ],
                    ],
                ],
                'Pagination' => [
                    'type' => 'object',
                    'properties' => [
                        'current_page' => [
                            'type' => 'integer',
                            'example' => 1,
                        ],
                        'last_page' => [
                            'type' => 'integer',
                            'example' => 5,
                        ],
                        'per_page' => [
                            'type' => 'integer',
                            'example' => 15,
                        ],
                        'total' => [
                            'type' => 'integer',
                            'example' => 75,
                        ],
                        'from' => [
                            'type' => 'integer',
                            'nullable' => true,
                            'example' => 1,
                        ],
                        'to' => [
                            'type' => 'integer',
                            'nullable' => true,
                            'example' => 15,
                        ],
                    ],
                ],
                'Success' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => [
                            'type' => 'boolean',
                            'example' => true,
                        ],
                        'message' => [
                            'type' => 'string',
                            'example' => 'Operation successful',
                        ],
                    ],
                ],
                'Error' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => [
                            'type' => 'boolean',
                            'example' => false,
                        ],
                        'message' => [
                            'type' => 'string',
                            'example' => 'Error occurred',
                        ],
                    ],
                ],
                'ValidationError' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => [
                            'type' => 'boolean',
                            'example' => false,
                        ],
                        'message' => [
                            'type' => 'string',
                            'example' => 'Validation failed',
                        ],
                        'errors' => [
                            'type' => 'object',
                            'example' => [
                                'email' => ['The email field is required.'],
                                'password' => ['The password field is required.'],
                            ],
                        ],
                    ],
                ],
            ],
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                    'description' => 'Laravel Sanctum Bearer token authentication',
                ],
            ],
        ];
    }
}