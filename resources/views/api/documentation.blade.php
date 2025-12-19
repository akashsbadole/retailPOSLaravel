<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - SuperMart Mobile POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-standalone-preset.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui.css">
    <style>
        .swagger-ui .topbar {
            background-color: #1b1b1b;
            border-bottom: 1px solid #2d2d2d;
        }
        .swagger-ui .topbar .download-url-wrapper {
            display: none;
        }
        .swagger-ui .info {
            margin: 50px 0;
        }
        .swagger-ui .scheme-container {
            margin: 0 0 20px 0;
        }
        .api-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .api-header h1 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .api-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .api-info {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin: 2rem;
        }
        .endpoint-section {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .endpoint-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0.5rem;
        }
        .endpoint-list {
            list-style: none;
            padding: 0;
        }
        .endpoint-list li {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.2s;
        }
        .endpoint-list li:hover {
            background-color: #f8f9fa;
        }
        .endpoint-list a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        .method-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            margin-right: 0.5rem;
        }
        .method-get {
            background-color: #10b981;
            color: white;
        }
        .method-post {
            background-color: #3b82f6;
            color: white;
        }
        .method-put {
            background-color: #f59e0b;
            color: white;
        }
        .method-delete {
            background-color: #ef4444;
            color: white;
        }
        .auth-section {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 2rem 0;
        }
        .auth-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: #92400e;
            margin-bottom: 1rem;
        }
        .code-block {
            background: #1f2937;
            color: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 1rem 0;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <div class="api-header">
        <h1>SuperMart Mobile POS API</h1>
        <p>RESTful API documentation for the SuperMart Point of Sale system</p>
    </div>

    <!-- API Information -->
    <div class="api-info">
        <h2 class="text-2xl font-bold mb-4">API Overview</h2>
        <div class="space-y-4">
            <div>
                <h3 class="text-lg font-semibold mb-2">Base URL</h3>
                <div class="code-block">{{ config('app.url') }}/api</div>
            </div>
            
            <div>
                <h3 class="text-lg font-semibold mb-2">Authentication</h3>
                <p class="mb-2">This API uses Laravel Sanctum for authentication. Include the token in the Authorization header:</p>
                <div class="code-block">Authorization: Bearer {your-token}</div>
            </div>
            
            <div>
                <h3 class="text-lg font-semibold mb-2">Rate Limiting</h3>
                <p>API requests are limited to 60 requests per minute per IP address. Rate limit headers are included in responses:</p>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li><code>X-RateLimit-Limit</code> - The rate limit ceiling</li>
                    <li><code>X-RateLimit-Remaining</code> - The number of requests remaining</li>
                    <li><code>X-RateLimit-Reset</code> - The time at which the rate limit resets</li>
                </ul>
            </div>
            
            <div>
                <h3 class="text-lg font-semibold mb-2">Response Format</h3>
                <p>All API responses follow this format:</p>
                <div class="code-block">
{
    "success": true,
    "message": "Operation successful",
    "data": { ... }
}
                </div>
            </div>
        </div>
    </div>

    <!-- Authentication Section -->
    <div class="auth-section">
        <h3 class="auth-title">Authentication Endpoints</h3>
        <div class="space-y-4">
            <div class="endpoint-section">
                <h4 class="endpoint-title">POST /auth/login</h4>
                <p class="mb-3">Authenticate user and return access token</p>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h5 class="font-semibold mb-2">Request Body:</h5>
                    <pre class="text-sm bg-gray-800 text-white p-3 rounded overflow-x-auto">
{
    "email": "user@example.com",
    "password": "password",
    "device_name": "Mobile POS - iPhone"
}
                    </pre>
                    <h5 class="font-semibold mb-2 mt-4">Response:</h5>
                    <pre class="text-sm bg-gray-800 text-white p-3 rounded overflow-x-auto">
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "user@example.com",
            "store_id": 1,
            "roles": ["cashier"],
            "permissions": ["api.access", "orders.create"]
        },
        "token": "1|abc123def456...",
        "token_type": "Bearer"
    }
}
                    </pre>
                </div>
            </div>
            
            <div class="endpoint-section">
                <h4 class="endpoint-title">POST /auth/logout</h4>
                <p class="mb-3">Logout user and revoke current token</p>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h5 class="font-semibold mb-2">Headers:</h5>
                    <pre class="text-sm bg-gray-800 text-white p-3 rounded overflow-x-auto">Authorization: Bearer {token}</pre>
                    <h5 class="font-semibold mb-2 mt-4">Response:</h5>
                    <pre class="text-sm bg-gray-800 text-white p-3 rounded overflow-x-auto">
{
    "success": true,
    "message": "Logout successful"
}
                    </pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Endpoints -->
    <div class="container mx-auto px-4 py-8">
        <h2 class="text-3xl font-bold text-center mb-8">API Endpoints</h2>
        
        <!-- Products -->
        <div class="endpoint-section">
            <h3 class="endpoint-title">Products</h3>
            <ul class="endpoint-list">
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/products/get">GET /products</a> - List products with search and filtering
                </li>
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/products/getId">GET /products/{id}</a> - Get product details
                </li>
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/products/barcodeSearch">GET /products/barcode/search</a> - Search by barcode or SKU
                </li>
            </ul>
        </div>

        <!-- Customers -->
        <div class="endpoint-section">
            <h3 class="endpoint-title">Customers</h3>
            <ul class="endpoint-list">
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/customers/get">GET /customers</a> - List customers
                </li>
                <li>
                    <span class="method-badge method-post">POST</span>
                    <a href="#!/customers/post">POST /customers</a> - Create new customer
                </li>
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/customers/getId">GET /customers/{id}</a> - Get customer details
                </li>
                <li>
                    <span class="method-badge method-put">PUT</span>
                    <a href="#!/customers/putId">PUT /customers/{id}</a> - Update customer
                </li>
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/customers/search">GET /customers/search/query</a> - Search customers
                </li>
            </ul>
        </div>

        <!-- Orders -->
        <div class="endpoint-section">
            <h3 class="endpoint-title">Orders</h3>
            <ul class="endpoint-list">
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/orders/get">GET /orders</a> - List orders
                </li>
                <li>
                    <span class="method-badge method-post">POST</span>
                    <a href="#!/orders/post">POST /orders</a> - Create new order
                </li>
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/orders/getId">GET /orders/{id}</a> - Get order details
                </li>
                <li>
                    <span class="method-badge method-put">PUT</span>
                    <a href="#!/orders/putId">PUT /orders/{id}</a> - Update order
                </li>
            </ul>
        </div>

        <!-- Payments -->
        <div class="endpoint-section">
            <h3 class="endpoint-title">Payments</h3>
            <ul class="endpoint-list">
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/payments/get">GET /payments</a> - List payments
                </li>
                <li>
                    <span class="method-badge method-post">POST</span>
                    <a href="#!/payments/post">POST /payments</a> - Process payment
                </li>
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/payments/getId">GET /payments/{id}</a> - Get payment details
                </li>
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/payments/summary">GET /payments/summary</a> - Get payment summary
                </li>
            </ul>
        </div>

        <!-- Stores -->
        <div class="endpoint-section">
            <h3 class="endpoint-title">Stores</h3>
            <ul class="endpoint-list">
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/stores/get">GET /stores</a> - List stores
                </li>
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/stores/getId">GET /stores/{id}</a> - Get store information
                </li>
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/stores/getIdStatistics">GET /stores/{id}/statistics</a> - Get store statistics
                </li>
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/stores/getIdStock">GET /stores/{id}/stock</a> - Get store stock
                </li>
            </ul>
        </div>

        <!-- Users -->
        <div class="endpoint-section">
            <h3 class="endpoint-title">Users</h3>
            <ul class="endpoint-list">
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/users/profile">GET /user/profile</a> - Get user profile
                </li>
                <li>
                    <span class="method-badge method-put">PUT</span>
                    <a href="#!/users/profile">PUT /user/profile</a> - Update user profile
                </li>
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/users/currentShift">GET /user/current-shift</a> - Get current shift
                </li>
                <li>
                    <span class="method-badge method-post">POST</span>
                    <a href="#!/users/startShift">POST /user/start-shift</a> - Start shift
                </li>
                <li>
                    <span class="method-badge method-post">POST</span>
                    <a href="#!/users/endShift">POST /user/end-shift</a> - End shift
                </li>
                <li>
                    <span class="method-badge method-get">GET</span>
                    <a href="#!/users/shiftHistory">GET /user/shift-history</a> - Get shift history
                </li>
            </ul>
        </div>
    </div>

    <!-- Interactive Swagger UI -->
    <div id="swagger-ui"></div>

    <script>
        // Load OpenAPI specification
        fetch('/api/documentation/openapi')
            .then(response => response.json())
            .then(spec => {
                // Initialize Swagger UI
                const ui = SwaggerUIBundle({
                    url: '/api/documentation/openapi',
                    dom_id: '#swagger-ui',
                    deepLinking: true,
                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIStandalonePreset
                    ],
                    plugins: [
                        SwaggerUIBundle.plugins.DownloadUrl
                    ],
                    layout: "StandaloneLayout",
                    defaultModelsExpandDepth: 1,
                    defaultModelExpandDepth: 1,
                    displayRequestDuration: true,
                    docExpansion: "list",
                    filter: true,
                    supportedSubmitMethods: ['get', 'post', 'put', 'delete'],
                    onComplete: function() {
                        console.log("Swagger UI loaded");
                    }
                });

                // Add custom styling
                setTimeout(() => {
                    const swaggerContainer = document.querySelector('#swagger-ui');
                    if (swaggerContainer) {
                        // Add custom header with API info
                        const infoSection = document.querySelector('.swagger-ui .info');
                        if (infoSection) {
                            infoSection.style.margin = '50px 0';
                        }
                    }
                }, 1000);
            })
            .catch(error => {
                console.error('Failed to load API specification:', error);
                document.getElementById('swagger-ui').innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 m-4">
                        <h3 class="text-lg font-semibold text-red-800 mb-2">Error Loading Documentation</h3>
                        <p class="text-red-600">Failed to load the API specification. Please try refreshing the page.</p>
                    </div>
                `;
            });
    </script>
</body>
</html>