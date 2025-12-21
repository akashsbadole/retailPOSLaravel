<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mobile POS - SuperMart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/lodash@4.17.21/lodash.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.0/dist/echo.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #3b82f6;
            --dark-blue: #1e40af;
            --accent-red: #ef4444;
            --accent-yellow: #fbbf24;
            --light-blue: #dbeafe;
        }
        
        .touch-button {
            min-height: 44px;
            min-width: 44px;
            touch-action: manipulation;
        }
        .product-card {
            transition: all 0.2s ease;
            border: 2px solid transparent;
        }
        .product-card:hover {
            border-color: var(--primary-blue);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        .product-card:active {
            transform: scale(0.95);
        }
        .cart-item {
            animation: slideIn 0.3s ease;
            border-left: 4px solid var(--primary-blue);
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        .offline-indicator {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        .number-input {
            font-size: 1.5rem;
            text-align: center;
        }
        .payment-method {
            transition: all 0.2s ease;
        }
        .payment-method:active {
            transform: scale(0.95);
        }
        .payment-method.selected {
            border-color: var(--primary-blue);
            background-color: var(--light-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* POS Color Scheme */
        .btn-primary {
            background-color: var(--primary-blue);
        }
        .btn-primary:hover {
            background-color: var(--dark-blue);
        }
        .btn-danger {
            background-color: var(--accent-red);
        }
        .btn-danger:hover {
            background-color: #dc2626;
        }
        .btn-success {
            background-color: #10b981;
        }
        .btn-success:hover {
            background-color: #059669;
        }
        .btn-warning {
            background-color: var(--accent-yellow);
            color: #1f2937;
        }
        .btn-warning:hover {
            background-color: #f59e0b;
        }
    </style>
</head>
<body class="bg-gray-100 h-screen overflow-hidden">
    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-4 shadow-lg">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <i class="fas fa-cash-register text-2xl"></i>
                <h1 class="text-xl font-bold">Mobile POS</h1>
            </div>
            <div class="flex items-center space-x-4">
                <div id="connectionStatus" class="flex items-center">
                    <i class="fas fa-wifi text-yellow-300"></i>
                    <span class="ml-2 text-sm">Online</span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-user-circle text-xl"></i>
                    <span id="userName" class="ml-2 text-sm">Loading...</span>
                </div>
                <button onclick="logout()" class="touch-button bg-red-500 hover:bg-red-600 px-3 py-2 rounded">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex flex-col h-[calc(100vh-80px)]">
        <!-- Search Bar -->
        <div class="bg-white p-4 shadow-sm border-b-2 border-blue-200">
            <div class="relative">
                <input 
                    type="text" 
                    id="searchInput" 
                    placeholder="Search products or scan barcode..."
                    class="w-full pl-10 pr-4 py-3 border-2 border-blue-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg"
                    autocomplete="off"
                >
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-blue-400"></i>
                <button 
                    onclick="scanBarcode()" 
                    class="absolute right-3 top-1/2 transform -translate-y-1/2 touch-button bg-blue-500 hover:bg-blue-600 text-white p-2 rounded"
                >
                    <i class="fas fa-barcode"></i>
                </button>
            </div>
        </div>

        <!-- Products and Cart Container -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Products Grid -->
            <div class="flex-1 overflow-y-auto p-4">
                <div id="productsContainer" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <!-- Products will be loaded here -->
                </div>
                <div id="loadingIndicator" class="hidden text-center py-8">
                    <i class="fas fa-spinner fa-spin text-4xl text-blue-500"></i>
                    <p class="mt-2 text-gray-600">Loading products...</p>
                </div>
            </div>

            <!-- Cart Sidebar -->
            <div class="w-full md:w-96 bg-white shadow-lg border-l flex flex-col">
                <!-- Cart Header -->
                <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-4 border-b-2 border-blue-300">
                    <h2 class="text-lg font-semibold text-blue-900 flex items-center">
                        <i class="fas fa-shopping-cart mr-2 text-blue-600"></i>
                        Cart
                        <span id="cartCount" class="ml-2 bg-red-500 text-white px-2 py-1 rounded-full text-xs font-bold">0</span>
                    </h2>
                </div>

                <!-- Cart Items -->
                <div id="cartItems" class="flex-1 overflow-y-auto p-4">
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                        <p>Cart is empty</p>
                    </div>
                </div>

                <!-- Cart Summary -->
                <div class="border-t-2 border-blue-200 p-4 bg-gradient-to-b from-blue-50 to-white">
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-700">Subtotal:</span>
                            <span id="subtotal" class="font-semibold text-gray-900">₹0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-700">Tax:</span>
                            <span id="tax" class="font-semibold text-gray-900">₹0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-700">Discount:</span>
                            <span id="discount" class="font-semibold text-red-600">-₹0.00</span>
                        </div>
                        <div class="flex justify-between text-lg font-bold border-t-2 border-yellow-300 pt-2 bg-gradient-to-r from-yellow-50 to-transparent p-2 rounded">
                            <span class="text-gray-900">Total:</span>
                            <span id="total" class="text-blue-600">₹0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Customer Selection -->
                <div class="border-t-2 border-blue-200 p-4 bg-blue-50">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium text-blue-900">Customer:</label>
                        <button 
                            onclick="showCustomerSearch()" 
                            class="touch-button bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm font-medium"
                        >
                            <i class="fas fa-user-plus mr-1"></i>
                            Add Customer
                        </button>
                    </div>
                    <div id="selectedCustomer" class="text-sm text-blue-700 bg-white p-2 rounded border border-blue-200">
                        <i class="fas fa-user mr-1"></i>
                        Walk-in Customer
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="border-t-2 border-blue-200 p-4 space-y-2 bg-gradient-to-b from-white to-gray-50">
                    <button 
                        onclick="clearCart()" 
                        class="touch-button w-full bg-gray-500 hover:bg-gray-600 text-white py-3 rounded font-medium transition-colors"
                    >
                        <i class="fas fa-trash mr-2"></i>
                        Clear Cart
                    </button>
                    <button 
                        onclick="showPaymentModal()" 
                        class="touch-button w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 rounded font-medium text-lg transition-all shadow-md hover:shadow-lg"
                        id="checkoutButton"
                        disabled
                    >
                        <i class="fas fa-credit-card mr-2"></i>
                        Checkout
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Payment Modal -->
    <div id="mobile-pos-payment-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg w-full max-w-md shadow-2xl">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-4 rounded-t-lg">
                <h3 class="text-lg font-semibold flex items-center">
                    <i class="fas fa-credit-card mr-2"></i>
                    Complete Payment
                </h3>
            </div>
            <div class="p-4">
                <div class="text-center mb-4 bg-gradient-to-r from-yellow-50 to-blue-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-600">Total Amount</p>
                    <p id="paymentTotal" class="text-3xl font-bold text-blue-600">₹0.00</p>
                </div>

                <!-- Payment Methods -->
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <button 
                        onclick="selectPaymentMethod('cash')" 
                        class="payment-method touch-button border-2 border-gray-300 rounded-lg p-4 text-center hover:border-blue-500 hover:bg-blue-50 transition-all"
                        data-method="cash"
                    >
                        <i class="fas fa-money-bill-wave text-2xl text-green-600 mb-2"></i>
                        <p class="font-medium text-gray-800">Cash</p>
                    </button>
                    <button 
                        onclick="selectPaymentMethod('card')" 
                        class="payment-method touch-button border-2 border-gray-300 rounded-lg p-4 text-center hover:border-blue-500 hover:bg-blue-50 transition-all"
                        data-method="card"
                    >
                        <i class="fas fa-credit-card text-2xl text-blue-600 mb-2"></i>
                        <p class="font-medium text-gray-800">Card</p>
                    </button>
                    <button 
                        onclick="selectPaymentMethod('upi')" 
                        class="payment-method touch-button border-2 border-gray-300 rounded-lg p-4 text-center hover:border-blue-500 hover:bg-blue-50 transition-all"
                        data-method="upi"
                    >
                        <i class="fas fa-mobile-alt text-2xl text-purple-600 mb-2"></i>
                        <p class="font-medium text-gray-800">UPI</p>
                    </button>
                    <button 
                        onclick="selectPaymentMethod('bank_transfer')" 
                        class="payment-method touch-button border-2 border-gray-300 rounded-lg p-4 text-center hover:border-blue-500 hover:bg-blue-50 transition-all"
                        data-method="bank_transfer"
                    >
                        <i class="fas fa-university text-2xl text-orange-600 mb-2"></i>
                        <p class="font-medium text-gray-800">Bank Transfer</p>
                    </button>
                </div>

                <!-- Cash Amount Input (shown only for cash payment) -->
                <div id="cashAmountSection" class="hidden mb-4 bg-yellow-50 p-3 rounded-lg border-2 border-yellow-200">
                    <label class="block text-sm font-medium text-yellow-900 mb-2">Cash Received:</label>
                    <input 
                        type="number" 
                        id="cashReceived" 
                        class="number-input w-full border-2 border-yellow-300 rounded-lg p-3 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200"
                        placeholder="Enter cash amount"
                        step="0.01"
                    >
                    <div class="mt-2 text-sm">
                        <span class="text-yellow-900">Change: </span>
                        <span id="changeAmount" class="font-bold text-green-600">₹0.00</span>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional):</label>
                    <textarea 
                        id="paymentNotes" 
                        class="w-full border-2 border-gray-300 rounded-lg p-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        rows="2"
                        placeholder="Add payment notes..."
                    ></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-3">
                    <button 
                        onclick="closePaymentModal()" 
                        class="touch-button flex-1 bg-gray-500 hover:bg-gray-600 text-white py-3 rounded font-medium transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        onclick="processPayment()" 
                        class="touch-button flex-1 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 rounded font-medium transition-all"
                        id="processPaymentButton"
                        disabled
                    >
                        <i class="fas fa-check mr-2"></i>
                        Process Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Search Modal -->
    <div id="customerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg w-full max-w-md shadow-2xl">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-4 rounded-t-lg">
                <h3 class="text-lg font-semibold flex items-center">
                    <i class="fas fa-users mr-2"></i>
                    Select Customer
                </h3>
            </div>
            <div class="p-4">
                <div class="relative mb-4">
                    <input 
                        type="text" 
                        id="customerSearchInput" 
                        placeholder="Search customers..."
                        class="w-full pl-10 pr-4 py-3 border-2 border-blue-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-blue-400"></i>
                </div>
                <div id="customerResults" class="max-h-64 overflow-y-auto">
                    <!-- Customer results will be loaded here -->
                </div>
                <div class="mt-4 flex space-x-3">
                    <button 
                        onclick="closeCustomerModal()" 
                        class="touch-button flex-1 bg-gray-500 hover:bg-gray-600 text-white py-3 rounded font-medium transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        onclick="createNewCustomer()" 
                        class="touch-button flex-1 bg-blue-500 hover:bg-blue-600 text-white py-3 rounded font-medium transition-colors"
                    >
                        <i class="fas fa-user-plus mr-2"></i>
                        New Customer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <div id="messageContainer" class="fixed top-20 right-4 z-50 space-y-2"></div>

    <script>
        // Global variables
        let cart = [];
        let selectedCustomer = null;
        let selectedPaymentMethod = null;
        let products = [];
        let customers = [];
        let apiToken = localStorage.getItem('apiToken');
        let currentUser = null;
        let currentStore = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            checkAuthentication();
            setupEventListeners();
            initializeApp();
        });

        // Check authentication
        function checkAuthentication() {
            if (!apiToken) {
                window.location.href = '/mobile-pos/login';
                return;
            }
            
            // Set up axios defaults
            axios.defaults.headers.common['Authorization'] = `Bearer ${apiToken}`;
            axios.defaults.headers.common['Content-Type'] = 'application/json';
        }

        // Setup event listeners
        function setupEventListeners() {
            // Search input
            document.getElementById('searchInput').addEventListener('input', debounce(handleSearch, 300));
            
            // Cash received input
            document.getElementById('cashReceived').addEventListener('input', calculateChange);
            
            // Customer search input
            document.getElementById('customerSearchInput').addEventListener('input', debounce(searchCustomers, 300));
            
            // Network status
            window.addEventListener('online', updateConnectionStatus);
            window.addEventListener('offline', updateConnectionStatus);
        }

        // Initialize app
        async function initializeApp() {
            try {
                await loadUserData();
                await loadProducts();
                updateConnectionStatus();
            } catch (error) {
                console.error('Initialization error:', error);
                showMessage('Failed to initialize app', 'error');
            }
        }

        // Load user data
        async function loadUserData() {
            try {
                const response = await axios.get('/api/auth/user');
                currentUser = response.data.data;
                currentStore = currentUser.store;
                
                document.getElementById('userName').textContent = currentUser.name;
            } catch (error) {
                console.error('Failed to load user data:', error);
                if (error.response?.status === 401) {
                    logout();
                }
            }
        }

        // Load products
        async function loadProducts() {
            try {
                showLoading(true);
                const response = await axios.get('/api/products', {
                    params: {
                        store_id: currentStore?.id,
                        in_stock: true,
                        per_page: 100
                    }
                });
                
                products = response.data.data.products;
                renderProducts(products);
            } catch (error) {
                console.error('Failed to load products:', error);
                showMessage('Failed to load products', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Render products
        function renderProducts(productsToRender) {
            const container = document.getElementById('productsContainer');
            
            if (productsToRender.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full text-center py-8">
                        <i class="fas fa-box-open text-4xl text-gray-400 mb-2"></i>
                        <p class="text-gray-600">No products found</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = productsToRender.map(product => `
                <div class="product-card bg-white rounded-lg shadow-md p-4 cursor-pointer hover:shadow-lg" onclick="addToCart(${product.id})">
                    <div class="aspect-square bg-gray-100 rounded-lg mb-3 flex items-center justify-center">
                        ${product.image ? 
                            `<img src="${product.image}" alt="${product.name}" class="w-full h-full object-cover rounded-lg">` :
                            `<i class="fas fa-box text-3xl text-gray-400"></i>`
                        }
                    </div>
                    <h3 class="font-semibold text-sm mb-1 truncate">${product.name}</h3>
                    <p class="text-blue-600 font-bold">₹${product.price.toFixed(2)}</p>
                    <div class="text-xs text-gray-500 mt-1">
                        Stock: ${product.stock.quantity}
                    </div>
                </div>
            `).join('');
        }

        // Handle search
        function handleSearch(event) {
            const query = event.target.value.toLowerCase();
            
            if (query.trim() === '') {
                renderProducts(products);
                return;
            }
            
            const filtered = products.filter(product => 
                product.name.toLowerCase().includes(query) ||
                product.barcode?.toLowerCase().includes(query) ||
                product.sku?.toLowerCase().includes(query)
            );
            
            renderProducts(filtered);
        }

        // Add to cart
        function addToCart(productId) {
            const product = products.find(p => p.id === productId);
            if (!product) return;
            
            // Check stock availability
            if (product.stock.quantity <= 0) {
                showMessage('Product is out of stock', 'error');
                return;
            }
            
            const existingItem = cart.find(item => item.product_id === productId);
            
            if (existingItem) {
                if (existingItem.quantity >= product.stock.quantity) {
                    showMessage('Insufficient stock', 'error');
                    return;
                }
                existingItem.quantity++;
            } else {
                cart.push({
                    product_id: productId,
                    name: product.name,
                    price: product.price,
                    tax: product.tax || 0,
                    quantity: 1,
                    max_quantity: product.stock.quantity
                });
            }
            
            updateCart();
            showMessage('Added to cart', 'success');
        }

        // Update cart display
        function updateCart() {
            const cartItems = document.getElementById('cartItems');
            const cartCount = document.getElementById('cartCount');
            const subtotal = document.getElementById('subtotal');
            const tax = document.getElementById('tax');
            const discount = document.getElementById('discount');
            const total = document.getElementById('total');
            const checkoutButton = document.getElementById('checkoutButton');
            
            if (cart.length === 0) {
                cartItems.innerHTML = `
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                        <p>Cart is empty</p>
                    </div>
                `;
                cartCount.textContent = '0';
                subtotal.textContent = '₹0.00';
                tax.textContent = '₹0.00';
                discount.textContent = '-₹0.00';
                total.textContent = '₹0.00';
                checkoutButton.disabled = true;
                return;
            }
            
            let subtotalAmount = 0;
            let taxAmount = 0;
            
            cartItems.innerHTML = cart.map((item, index) => {
                const itemTotal = item.price * item.quantity;
                const itemTax = item.tax * item.quantity;
                
                subtotalAmount += itemTotal;
                taxAmount += itemTax;
                
                return `
                    <div class="cart-item bg-gray-50 rounded-lg p-3 mb-3">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex-1">
                                <h4 class="font-medium">${item.name}</h4>
                                <p class="text-sm text-gray-600">₹${item.price.toFixed(2)} each</p>
                            </div>
                            <button onclick="removeFromCart(${index})" class="text-red-500 hover:text-red-700">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <button onclick="updateQuantity(${index}, -1)" class="touch-button bg-gray-200 hover:bg-gray-300 w-8 h-8 rounded">
                                    <i class="fas fa-minus text-xs"></i>
                                </button>
                                <span class="font-medium w-12 text-center">${item.quantity}</span>
                                <button onclick="updateQuantity(${index}, 1)" class="touch-button bg-gray-200 hover:bg-gray-300 w-8 h-8 rounded">
                                    <i class="fas fa-plus text-xs"></i>
                                </button>
                            </div>
                            <span class="font-semibold">₹${itemTotal.toFixed(2)}</span>
                        </div>
                    </div>
                `;
            }).join('');
            
            const totalAmount = subtotalAmount + taxAmount;
            
            cartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
            subtotal.textContent = `₹${subtotalAmount.toFixed(2)}`;
            tax.textContent = `₹${taxAmount.toFixed(2)}`;
            discount.textContent = '-₹0.00';
            total.textContent = `₹${totalAmount.toFixed(2)}`;
            checkoutButton.disabled = false;
        }

        // Update quantity
        function updateQuantity(index, change) {
            const item = cart[index];
            const newQuantity = item.quantity + change;
            
            if (newQuantity <= 0) {
                removeFromCart(index);
                return;
            }
            
            if (newQuantity > item.max_quantity) {
                showMessage('Insufficient stock', 'error');
                return;
            }
            
            item.quantity = newQuantity;
            updateCart();
        }

        // Remove from cart
        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCart();
            showMessage('Removed from cart', 'info');
        }

        // Clear cart
        function clearCart() {
            if (cart.length === 0) return;
            
            if (confirm('Are you sure you want to clear the cart?')) {
                cart = [];
                updateCart();
                showMessage('Cart cleared', 'info');
            }
        }

        // Show payment modal
        function showPaymentModal() {
            if (cart.length === 0) return;
            
            // Check if modal is already open
            const modal = document.getElementById('mobile-pos-payment-modal');
            if (!modal.classList.contains('hidden')) {
                return;
            }
            
            const total = document.getElementById('total').textContent;
            document.getElementById('paymentTotal').textContent = total;
            modal.classList.remove('hidden');
        }

        // Close payment modal
        function closePaymentModal() {
            const modal = document.getElementById('mobile-pos-payment-modal');
            modal.classList.add('hidden');
            // Remove the 'open' attribute to prevent conflicts with Filament modals
            modal.removeAttribute('open');
            
            selectedPaymentMethod = null;
            document.querySelectorAll('.payment-method').forEach(btn => {
                btn.classList.remove('selected');
            });
            document.getElementById('cashAmountSection').classList.add('hidden');
            document.getElementById('processPaymentButton').disabled = true;
        }

        // Select payment method
        function selectPaymentMethod(method) {
            selectedPaymentMethod = method;
            
            document.querySelectorAll('.payment-method').forEach(btn => {
                btn.classList.remove('selected');
            });
            
            document.querySelector(`[data-method="${method}"]`).classList.add('selected');
            
            const cashSection = document.getElementById('cashAmountSection');
            const processButton = document.getElementById('processPaymentButton');
            
            if (method === 'cash') {
                cashSection.classList.remove('hidden');
                processButton.disabled = false;
                document.getElementById('cashReceived').focus();
            } else {
                cashSection.classList.add('hidden');
                processButton.disabled = false;
            }
        }

        // Calculate change
        function calculateChange() {
            const total = parseFloat(document.getElementById('total').textContent.replace('₹', ''));
            const cashReceived = parseFloat(document.getElementById('cashReceived').value) || 0;
            const change = cashReceived - total;
            
            document.getElementById('changeAmount').textContent = `₹${change.toFixed(2)}`;
        }

        // Process payment
        async function processPayment() {
            if (!selectedPaymentMethod) {
                showMessage('Please select a payment method', 'error');
                return;
            }
            
            try {
                const orderData = {
                    customer_id: selectedCustomer?.id,
                    items: cart.map(item => ({
                        product_id: item.product_id,
                        quantity: item.quantity,
                        price: item.price,
                        discount_percentage: 0
                    })),
                    store_id: currentStore?.id,
                    notes: document.getElementById('paymentNotes').value
                };
                
                // Create order
                const orderResponse = await axios.post('/api/orders', orderData);
                const order = orderResponse.data.data;
                
                // Process payment
                const paymentData = {
                    order_id: order.id,
                    amount: parseFloat(document.getElementById('total').textContent.replace('₹', '')),
                    payment_method: selectedPaymentMethod,
                    notes: document.getElementById('paymentNotes').value,
                    store_id: currentStore?.id
                };
                
                await axios.post('/api/payments', paymentData);
                
                // Reset
                cart = [];
                selectedCustomer = null;
                updateCart();
                closePaymentModal();
                updateSelectedCustomer();
                
                showMessage('Payment processed successfully!', 'success');
                
                // Print receipt (optional)
                if (confirm('Would you like to print a receipt?')) {
                    printReceipt(order);
                }
                
            } catch (error) {
                console.error('Payment processing error:', error);
                showMessage('Failed to process payment: ' + (error.response?.data?.message || error.message), 'error');
            }
        }

        // Show customer search modal
        async function showCustomerSearch() {
            document.getElementById('customerModal').classList.remove('hidden');
            document.getElementById('customerSearchInput').value = '';
            document.getElementById('customerSearchInput').focus();
            await loadCustomers();
        }

        // Close customer modal
        function closeCustomerModal() {
            document.getElementById('customerModal').classList.add('hidden');
        }

        // Load customers
        async function loadCustomers() {
            try {
                const response = await axios.get('/api/customers', {
                    params: { per_page: 50 }
                });
                customers = response.data.data.customers;
                renderCustomers(customers);
            } catch (error) {
                console.error('Failed to load customers:', error);
                showMessage('Failed to load customers', 'error');
            }
        }

        // Search customers
        function searchCustomers(event) {
            const query = event.target.value.toLowerCase();
            
            if (query.trim() === '') {
                renderCustomers(customers);
                return;
            }
            
            const filtered = customers.filter(customer => 
                customer.full_name.toLowerCase().includes(query) ||
                customer.email?.toLowerCase().includes(query) ||
                customer.phone?.includes(query)
            );
            
            renderCustomers(filtered);
        }

        // Render customers
        function renderCustomers(customersToRender) {
            const container = document.getElementById('customerResults');
            
            if (customersToRender.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 text-gray-500">
                        <i class="fas fa-user-slash text-2xl mb-2"></i>
                        <p>No customers found</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = customersToRender.map(customer => `
                <div class="border-b p-3 hover:bg-gray-50 cursor-pointer" onclick="selectCustomer(${customer.id})">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-user text-blue-600"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium">${customer.full_name}</p>
                            <p class="text-sm text-gray-600">${customer.email || customer.phone || 'No contact info'}</p>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Select customer
        function selectCustomer(customerId) {
            selectedCustomer = customers.find(c => c.id === customerId);
            updateSelectedCustomer();
            closeCustomerModal();
        }

        // Update selected customer display
        function updateSelectedCustomer() {
            const container = document.getElementById('selectedCustomer');
            
            if (selectedCustomer) {
                container.innerHTML = `
                    <i class="fas fa-user mr-1"></i>
                    ${selectedCustomer.full_name}
                `;
            } else {
                container.innerHTML = `
                    <i class="fas fa-user mr-1"></i>
                    Walk-in Customer
                `;
            }
        }

        // Create new customer
        function createNewCustomer() {
            // This would open a customer creation form
            // For now, just close the modal
            closeCustomerModal();
            showMessage('Customer creation not implemented yet', 'info');
        }

        // Scan barcode
        function scanBarcode() {
            // This would integrate with a barcode scanner
            // For now, just focus on search input
            document.getElementById('searchInput').focus();
            showMessage('Barcode scanning not implemented yet', 'info');
        }

        // Logout
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                localStorage.removeItem('apiToken');
                window.location.href = '/mobile-pos/login';
            }
        }

        // Update connection status
        function updateConnectionStatus() {
            const statusElement = document.getElementById('connectionStatus');
            const isOnline = navigator.onLine;
            
            if (isOnline) {
                statusElement.innerHTML = `
                    <i class="fas fa-wifi text-green-300"></i>
                    <span class="ml-2 text-sm">Online</span>
                `;
            } else {
                statusElement.innerHTML = `
                    <i class="fas fa-wifi-slash text-red-300 offline-indicator"></i>
                    <span class="ml-2 text-sm">Offline</span>
                `;
            }
        }

        // Show loading
        function showLoading(show) {
            const loadingIndicator = document.getElementById('loadingIndicator');
            const productsContainer = document.getElementById('productsContainer');
            
            if (show) {
                loadingIndicator.classList.remove('hidden');
                productsContainer.classList.add('hidden');
            } else {
                loadingIndicator.classList.add('hidden');
                productsContainer.classList.remove('hidden');
            }
        }

        // Show message
        function showMessage(message, type = 'info') {
            const container = document.getElementById('messageContainer');
            const messageId = Date.now();
            
            const bgColor = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500',
                warning: 'bg-yellow-500'
            }[type] || 'bg-gray-500';
            
            const messageElement = document.createElement('div');
            messageElement.id = `message-${messageId}`;
            messageElement.className = `${bgColor} text-white px-4 py-3 rounded-lg shadow-lg flex items-center space-x-2`;
            messageElement.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            
            container.appendChild(messageElement);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                const element = document.getElementById(`message-${messageId}`);
                if (element) {
                    element.remove();
                }
            }, 3000);
        }

        // Print receipt
        function printReceipt(order) {
            // This would integrate with a receipt printer
            // For now, just open a print window
            const receiptContent = `
                <html>
                    <head>
                        <title>Receipt - Order #${order.id}</title>
                        <style>
                            body { font-family: monospace; padding: 20px; }
                            .header { text-align: center; margin-bottom: 20px; }
                            .items { margin: 20px 0; }
                            .item { display: flex; justify-content: space-between; margin: 5px 0; }
                            .total { border-top: 1px solid #000; padding-top: 10px; }
                            .footer { text-align: center; margin-top: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h2>SuperMart</h2>
                            <p>Order #${order.id}</p>
                            <p>${new Date().toLocaleString()}</p>
                        </div>
                        <div class="items">
                            ${order.items.map(item => `
                                <div class="item">
                                    <span>${item.name} x${item.quantity}</span>
                                    <span>₹${(item.price * item.quantity).toFixed(2)}</span>
                                </div>
                            `).join('')}
                        </div>
                        <div class="total">
                            <div class="item">
                                <span>Total:</span>
                                <span>₹${order.total_price.toFixed(2)}</span>
                            </div>
                        </div>
                        <div class="footer">
                            <p>Thank you for shopping!</p>
                        </div>
                    </body>
                </html>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(receiptContent);
            printWindow.document.close();
            printWindow.print();
        }

        // Utility function for debouncing
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    </script>
</body>
</html>