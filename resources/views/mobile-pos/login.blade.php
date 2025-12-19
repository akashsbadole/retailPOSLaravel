<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - Mobile POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .touch-button {
            min-height: 44px;
            min-width: 44px;
            touch-action: manipulation;
        }
        .login-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .form-input {
            transition: all 0.3s ease;
        }
        .form-input:focus {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .loading-spinner {
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 3px solid white;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .shake {
            animation: shake 0.5s;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
    </style>
</head>
<body class="login-container min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <!-- Header -->
        <div class="bg-blue-600 text-white p-6 text-center">
            <i class="fas fa-cash-register text-4xl mb-3"></i>
            <h1 class="text-2xl font-bold">Mobile POS</h1>
            <p class="text-blue-100 text-sm mt-1">SuperMart Point of Sale</p>
        </div>

        <!-- Login Form -->
        <div class="p-6">
            <form id="loginForm" onsubmit="handleLogin(event)">
                <!-- Email Field -->
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-1"></i>
                        Email Address
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required
                        autocomplete="email"
                        autocapitalize="none"
                        class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Enter your email"
                    >
                </div>

                <!-- Password Field -->
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-1"></i>
                        Password
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            autocomplete="current-password"
                            class="form-input w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter your password"
                        >
                        <button 
                            type="button" 
                            onclick="togglePassword()"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                        >
                            <i id="passwordToggle" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Device Name Field -->
                <div class="mb-6">
                    <label for="device_name" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-mobile-alt mr-1"></i>
                        Device Name
                    </label>
                    <input 
                        type="text" 
                        id="device_name" 
                        name="device_name" 
                        required
                        autocomplete="off"
                        class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Mobile POS - " 
                        value="Mobile POS - "
                    >
                </div>

                <!-- Remember Me -->
                <div class="mb-6 flex items-center">
                    <input 
                        type="checkbox" 
                        id="remember" 
                        name="remember" 
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    >
                    <label for="remember" class="ml-2 block text-sm text-gray-700">
                        Remember me
                    </label>
                </div>

                <!-- Error Message -->
                <div id="errorMessage" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span id="errorText"></span>
                </div>

                <!-- Success Message -->
                <div id="successMessage" class="hidden mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span id="successText"></span>
                </div>

                <!-- Login Button -->
                <button 
                    type="submit" 
                    id="loginButton"
                    class="touch-button w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-lg transition duration-200 flex items-center justify-center"
                >
                    <span id="loginButtonText">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Sign In
                    </span>
                    <div id="loginSpinner" class="hidden loading-spinner"></div>
                </button>

                <!-- Additional Options -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        Please contact your administrator if you need access credentials
                    </p>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-4 border-t">
            <div class="flex items-center justify-between text-xs text-gray-500">
                <span>SuperMart POS v1.0</span>
                <span>© 2024 SuperMart</span>
            </div>
        </div>
    </div>

    <!-- Network Status -->
    <div id="networkStatus" class="fixed top-4 right-4 bg-white rounded-lg shadow-lg p-3 hidden">
        <div class="flex items-center">
            <i id="networkIcon" class="fas fa-wifi mr-2"></i>
            <span id="networkText" class="text-sm font-medium"></span>
        </div>
    </div>

    <script>
        // Global variables
        let isLoading = false;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            checkNetworkStatus();
            focusEmailInput();
            
            // Check for existing token
            const existingToken = localStorage.getItem('apiToken');
            if (existingToken) {
                // Validate token
                validateToken(existingToken);
            }
        });

        // Setup event listeners
        function setupEventListeners() {
            // Network status
            window.addEventListener('online', () => updateNetworkStatus(true));
            window.addEventListener('offline', () => updateNetworkStatus(false));
            
            // Form validation
            document.getElementById('email').addEventListener('input', clearErrors);
            document.getElementById('password').addEventListener('input', clearErrors);
            document.getElementById('device_name').addEventListener('input', clearErrors);
        }

        // Focus email input
        function focusEmailInput() {
            setTimeout(() => {
                document.getElementById('email').focus();
            }, 100);
        }

        // Validate existing token
        async function validateToken(token) {
            try {
                // Set up axios defaults
                axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
                axios.defaults.headers.common['Content-Type'] = 'application/json';
                
                const response = await axios.get('/api/auth/user');
                
                // Token is valid, redirect to POS
                window.location.href = '/mobile-pos';
            } catch (error) {
                // Token is invalid, remove it
                localStorage.removeItem('apiToken');
                console.log('Invalid token, user needs to login');
            }
        }

        // Handle login
        async function handleLogin(event) {
            event.preventDefault();
            
            if (isLoading) return;
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const deviceName = document.getElementById('device_name').value.trim();
            const remember = document.getElementById('remember').checked;
            
            // Basic validation
            if (!email || !password || !deviceName) {
                showError('Please fill in all required fields');
                shakeForm();
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showError('Please enter a valid email address');
                shakeForm();
                return;
            }
            
            setLoading(true);
            clearErrors();
            
            try {
                const response = await axios.post('/api/auth/login', {
                    email,
                    password,
                    device_name: deviceName
                });
                
                const { token } = response.data.data;
                
                // Store token
                if (remember) {
                    localStorage.setItem('apiToken', token);
                } else {
                    sessionStorage.setItem('apiToken', token);
                }
                
                showSuccess('Login successful! Redirecting...');
                
                // Redirect to POS
                setTimeout(() => {
                    window.location.href = '/mobile-pos';
                }, 1000);
                
            } catch (error) {
                console.error('Login error:', error);
                
                let errorMessage = 'Login failed. Please try again.';
                
                if (error.response) {
                    if (error.response.status === 422) {
                        const errors = error.response.data.errors;
                        if (errors.email) {
                            errorMessage = errors.email[0];
                        } else if (errors.password) {
                            errorMessage = errors.password[0];
                        } else if (errors.device_name) {
                            errorMessage = errors.device_name[0];
                        }
                    } else if (error.response.status === 401) {
                        errorMessage = 'Invalid email or password';
                    } else if (error.response.status === 429) {
                        errorMessage = 'Too many login attempts. Please try again later.';
                    } else if (error.response.data?.message) {
                        errorMessage = error.response.data.message;
                    }
                } else if (error.message) {
                    errorMessage = 'Network error. Please check your connection.';
                }
                
                showError(errorMessage);
                shakeForm();
            } finally {
                setLoading(false);
            }
        }

        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('passwordToggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordToggle.classList.remove('fa-eye');
                passwordToggle.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordToggle.classList.remove('fa-eye-slash');
                passwordToggle.classList.add('fa-eye');
            }
        }

        // Show error message
        function showError(message) {
            const errorElement = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            
            errorText.textContent = message;
            errorElement.classList.remove('hidden');
            errorElement.classList.add('shake');
            
            setTimeout(() => {
                errorElement.classList.remove('shake');
            }, 500);
        }

        // Show success message
        function showSuccess(message) {
            const successElement = document.getElementById('successMessage');
            const successText = document.getElementById('successText');
            
            successText.textContent = message;
            successElement.classList.remove('hidden');
        }

        // Clear error messages
        function clearErrors() {
            document.getElementById('errorMessage').classList.add('hidden');
            document.getElementById('successMessage').classList.add('hidden');
        }

        // Shake form
        function shakeForm() {
            const form = document.getElementById('loginForm');
            form.classList.add('shake');
            
            setTimeout(() => {
                form.classList.remove('shake');
            }, 500);
        }

        // Set loading state
        function setLoading(loading) {
            isLoading = loading;
            const loginButton = document.getElementById('loginButton');
            const loginButtonText = document.getElementById('loginButtonText');
            const loginSpinner = document.getElementById('loginSpinner');
            
            if (loading) {
                loginButton.disabled = true;
                loginButtonText.classList.add('hidden');
                loginSpinner.classList.remove('hidden');
            } else {
                loginButton.disabled = false;
                loginButtonText.classList.remove('hidden');
                loginSpinner.classList.add('hidden');
            }
        }

        // Check network status
        function checkNetworkStatus() {
            updateNetworkStatus(navigator.onLine);
        }

        // Update network status
        function updateNetworkStatus(isOnline) {
            const statusElement = document.getElementById('networkStatus');
            const networkIcon = document.getElementById('networkIcon');
            const networkText = document.getElementById('networkText');
            
            if (isOnline) {
                networkIcon.classList.remove('fa-wifi-slash', 'text-red-500');
                networkIcon.classList.add('fa-wifi', 'text-green-500');
                networkText.textContent = 'Online';
                networkElement.classList.add('hidden');
            } else {
                networkIcon.classList.remove('fa-wifi', 'text-green-500');
                networkIcon.classList.add('fa-wifi-slash', 'text-red-500');
                networkText.textContent = 'Offline';
                statusElement.classList.remove('hidden');
                
                // Hide after 3 seconds
                setTimeout(() => {
                    statusElement.classList.add('hidden');
                }, 3000);
            }
        }

        // Auto-generate device name
        document.getElementById('device_name').value = 'Mobile POS - ' + 
            (navigator.userAgent.includes('iPhone') ? 'iPhone' : 
             navigator.userAgent.includes('Android') ? 'Android' : 
             navigator.userAgent.includes('iPad') ? 'iPad' : 'Browser');
    </script>
</body>
</html>