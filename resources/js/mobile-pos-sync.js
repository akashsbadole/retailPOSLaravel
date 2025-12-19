/**
 * Mobile POS Real-time Synchronization
 * Handles real-time updates using Laravel Echo
 */

class MobilePosSync {
    constructor(options = {}) {
        this.options = {
            apiUrl: options.apiUrl || window.location.origin + '/api',
            token: options.token || localStorage.getItem('apiToken'),
            userId: options.userId || null,
            storeId: options.storeId || null,
            debug: options.debug || false,
            ...options
        };

        this.echo = null;
        this.channels = {};
        this.listeners = {};
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;

        this.init();
    }

    /**
     * Initialize the synchronization
     */
    async init() {
        try {
            await this.setupEcho();
            this.setupChannels();
            this.setupEventListeners();
            this.log('Mobile POS Sync initialized');
        } catch (error) {
            this.logError('Failed to initialize sync:', error);
        }
    }

    /**
     * Setup Laravel Echo
     */
    async setupEcho() {
        if (!this.options.token) {
            throw new Error('No authentication token provided');
        }

        // Load Echo if not already loaded
        if (typeof window.Echo === 'undefined') {
            await this.loadEchoScript();
        }

        this.echo = new window.Echo({
            broadcaster: 'pusher',
            key: this.getPusherKey(),
            cluster: this.getPusherCluster(),
            wsHost: this.getWebSocketHost(),
            wsPort: this.getWebSocketPort(),
            forceTLS: this.getForceTLS(),
            authEndpoint: `${this.options.apiUrl}/broadcasting/auth`,
            auth: {
                headers: {
                    Authorization: `Bearer ${this.options.token}`,
                    Accept: 'application/json',
                }
            },
            enabledTransports: ['ws', 'wss'],
        });

        // Setup connection event handlers
        this.echo.connector.pusher.connection.bind('connected', () => {
            this.log('Connected to real-time server');
            this.reconnectAttempts = 0;
            this.onConnected();
        });

        this.echo.connector.pusher.connection.bind('disconnected', () => {
            this.log('Disconnected from real-time server');
            this.onDisconnected();
        });

        this.echo.connector.pusher.connection.bind('error', (error) => {
            this.logError('Connection error:', error);
            this.onConnectionError(error);
        });
    }

    /**
     * Setup channels
     */
    setupChannels() {
        if (!this.echo) return;

        // User channel
        if (this.options.userId) {
            this.channels.user = this.echo.private(`user.${this.options.userId}`);
            this.setupChannelListeners(this.channels.user, 'user');
        }

        // Store channels
        if (this.options.storeId) {
            this.channels.orders = this.echo.private(`store.${this.options.storeId}.orders`);
            this.setupChannelListeners(this.channels.orders, 'orders');

            this.channels.payments = this.echo.private(`store.${this.options.storeId}.payments`);
            this.setupChannelListeners(this.channels.payments, 'payments');

            this.channels.stock = this.echo.private(`store.${this.options.storeId}.stock`);
            this.setupChannelListeners(this.channels.stock, 'stock');

            this.channels.shifts = this.echo.private(`store.${this.options.storeId}.shifts`);
            this.setupChannelListeners(this.channels.shifts, 'shifts');
        }

        // Global channels
        this.channels.customers = this.echo.private('customers');
        this.setupChannelListeners(this.channels.customers, 'customers');
    }

    /**
     * Setup channel listeners
     */
    setupChannelListeners(channel, type) {
        if (!channel) return;

        // Order created
        channel.listen('.order.created', (data) => {
            this.log(`Order created event received on ${type} channel`, data);
            this.trigger('order.created', data);
        });

        // Order updated
        channel.listen('.order.updated', (data) => {
            this.log(`Order updated event received on ${type} channel`, data);
            this.trigger('order.updated', data);
        });

        // Payment processed
        channel.listen('.payment.processed', (data) => {
            this.log(`Payment processed event received on ${type} channel`, data);
            this.trigger('payment.processed', data);
        });

        // Stock updated
        if (type === 'stock') {
            channel.listen('.stock.updated', (data) => {
                this.log(`Stock updated event received on ${type} channel`, data);
                this.trigger('stock.updated', data);
            });
        }

        // Customer created
        if (type === 'customers') {
            channel.listen('.customer.created', (data) => {
                this.log(`Customer created event received on ${type} channel`, data);
                this.trigger('customer.created', data);
            });
        }

        // Shift events
        if (type === 'shifts') {
            channel.listen('.shift.started', (data) => {
                this.log(`Shift started event received on ${type} channel`, data);
                this.trigger('shift.started', data);
            });

            channel.listen('.shift.ended', (data) => {
                this.log(`Shift ended event received on ${type} channel`, data);
                this.trigger('shift.ended', data);
            });
        }
    }

    /**
     * Setup browser event listeners
     */
    setupEventListeners() {
        // Online/Offline events
        window.addEventListener('online', () => {
            this.log('Browser went online');
            this.reconnect();
        });

        window.addEventListener('offline', () => {
            this.log('Browser went offline');
            this.trigger('connection.offline');
        });

        // Visibility change events
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.log('Page became visible');
                this.syncData();
            }
        });

        // Focus events
        window.addEventListener('focus', () => {
            this.log('Page gained focus');
            this.syncData();
        });
    }

    /**
     * Add event listener
     */
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }

    /**
     * Remove event listener
     */
    off(event, callback) {
        if (this.listeners[event]) {
            this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
        }
    }

    /**
     * Trigger event
     */
    trigger(event, data) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    this.logError(`Error in ${event} listener:`, error);
                }
            });
        }
    }

    /**
     * Handle connection established
     */
    onConnected() {
        this.trigger('connection.connected');
        this.syncData();
    }

    /**
     * Handle connection lost
     */
    onDisconnected() {
        this.trigger('connection.disconnected');
        
        // Attempt to reconnect
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            setTimeout(() => {
                this.reconnect();
            }, this.reconnectDelay);
        }
    }

    /**
     * Handle connection error
     */
    onConnectionError(error) {
        this.trigger('connection.error', error);
        
        // Attempt to reconnect
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            setTimeout(() => {
                this.reconnect();
            }, this.reconnectDelay * (this.reconnectAttempts + 1));
        }
    }

    /**
     * Reconnect to the server
     */
    async reconnect() {
        this.reconnectAttempts++;
        this.log(`Reconnection attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts}`);

        try {
            if (this.echo) {
                this.echo.disconnect();
            }
            await this.setupEcho();
            this.setupChannels();
        } catch (error) {
            this.logError('Reconnection failed:', error);
        }
    }

    /**
     * Sync data when reconnecting
     */
    async syncData() {
        try {
            // Sync products
            await this.syncProducts();
            
            // Sync orders
            await this.syncOrders();
            
            // Sync customers
            await this.syncCustomers();
            
            this.trigger('sync.completed');
        } catch (error) {
            this.logError('Data sync failed:', error);
        }
    }

    /**
     * Sync products
     */
    async syncProducts() {
        if (!this.options.storeId) return;

        try {
            const response = await axios.get(`${this.options.apiUrl}/products`, {
                params: {
                    store_id: this.options.storeId,
                    in_stock: true,
                    per_page: 100
                }
            });

            this.trigger('products.synced', response.data.data.products);
        } catch (error) {
            this.logError('Failed to sync products:', error);
        }
    }

    /**
     * Sync orders
     */
    async syncOrders() {
        try {
            const params = {};
            if (this.options.storeId) {
                params.store_id = this.options.storeId;
            }

            const response = await axios.get(`${this.options.apiUrl}/orders`, {
                params: {
                    ...params,
                    per_page: 50
                }
            });

            this.trigger('orders.synced', response.data.data.orders);
        } catch (error) {
            this.logError('Failed to sync orders:', error);
        }
    }

    /**
     * Sync customers
     */
    async syncCustomers() {
        try {
            const response = await axios.get(`${this.options.apiUrl}/customers`, {
                params: {
                    per_page: 50
                }
            });

            this.trigger('customers.synced', response.data.data.customers);
        } catch (error) {
            this.logError('Failed to sync customers:', error);
        }
    }

    /**
     * Load Echo script
     */
    async loadEchoScript() {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/laravel-echo@1.15.0/dist/echo.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * Get Pusher key
     */
    getPusherKey() {
        return window.pusherKey || 'your-pusher-key';
    }

    /**
     * Get Pusher cluster
     */
    getPusherCluster() {
        return window.pusherCluster || 'mt1';
    }

    /**
     * Get WebSocket host
     */
    getWebSocketHost() {
        return window.wsHost || 'ws-' + this.getPusherCluster() + '.pusher.com';
    }

    /**
     * Get WebSocket port
     */
    getWebSocketPort() {
        return window.wsPort || 443;
    }

    /**
     * Get force TLS setting
     */
    getForceTLS() {
        return window.forceTLS !== false;
    }

    /**
     * Log message
     */
    log(message, data = null) {
        if (this.options.debug) {
            console.log(`[Mobile POS Sync] ${message}`, data);
        }
    }

    /**
     * Log error
     */
    logError(message, error = null) {
        console.error(`[Mobile POS Sync] ${message}`, error);
    }

    /**
     * Disconnect
     */
    disconnect() {
        if (this.echo) {
            this.echo.disconnect();
            this.echo = null;
        }
        this.channels = {};
        this.listeners = {};
    }

    /**
     * Update user and store context
     */
    updateContext(userId, storeId) {
        this.options.userId = userId;
        this.options.storeId = storeId;
        
        // Reconnect with new context
        if (this.echo) {
            this.disconnect();
            this.init();
        }
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MobilePosSync;
} else if (typeof window !== 'undefined') {
    window.MobilePosSync = MobilePosSync;
}