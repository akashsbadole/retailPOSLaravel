# Mobile POS / API Documentation

## Overview

This document describes the Mobile Point of Sale (POS) API implementation for SuperMart, which provides a complete RESTful API and mobile-optimized interface for managing sales, products, customers, orders, and payments.

## Features Implemented

### 1. RESTful API Endpoints

The API provides comprehensive endpoints for all POS operations:

#### Authentication
- `POST /api/auth/login` - User authentication with token generation
- `POST /api/auth/logout` - User logout and token revocation
- `POST /api/auth/logout-all` - Logout from all devices
- `POST /api/auth/refresh` - Token refresh functionality
- `GET /api/auth/user` - Get current user information

#### Products
- `GET /api/products` - List products with search and filtering
- `GET /api/products/{id}` - Get product details
- `GET /api/products/barcode/search` - Search products by barcode or SKU

#### Customers
- `GET /api/customers` - List customers
- `POST /api/customers` - Create new customer
- `GET /api/customers/{id}` - Get customer details
- `PUT /api/customers/{id}` - Update customer information
- `GET /api/customers/search/query` - Search customers

#### Orders
- `GET /api/orders` - List orders with filtering
- `POST /api/orders` - Create new order
- `GET /api/orders/{id}` - Get order details
- `PUT /api/orders/{id}` - Update order status

#### Payments
- `GET /api/payments` - List payments
- `POST /api/payments` - Process payment
- `GET /api/payments/{id}` - Get payment details
- `GET /api/payments/summary` - Get payment method summary

#### Stores
- `GET /api/stores` - List stores
- `GET /api/stores/{id}` - Get store information
- `GET /api/stores/{id}/statistics` - Get store statistics
- `GET /api/stores/{id}/stock` - Get store inventory

#### Users
- `GET /api/user/profile` - Get user profile
- `PUT /api/user/profile` - Update user profile
- `GET /api/user/current-shift` - Get current shift
- `POST /api/user/start-shift` - Start new shift
- `POST /api/user/end-shift` - End current shift
- `GET /api/user/shift-history` - Get shift history

### 2. Authentication System

**Laravel Sanctum Integration:**
- Token-based authentication using Laravel Sanctum
- Bearer token authentication for API access
- Token management with device-specific tokens
- Automatic token refresh capability
- Secure token storage and revocation

**Security Features:**
- Password hashing with Laravel's built-in security
- Token expiration management
- Rate limiting (60 requests per minute)
- CORS configuration for mobile access
- Input validation and sanitization

### 3. Mobile-Optimized POS Interface

**Responsive Design:**
- Touch-friendly UI with minimum 44px touch targets
- Mobile-first responsive layout
- Optimized for smartphones and tablets
- Swipe gestures support for product navigation

**Key Features:**
- Real-time product search and barcode scanning
- Visual product grid with images
- Shopping cart with quantity management
- Customer selection and search
- Multiple payment methods (Cash, Card, UPI, Bank Transfer)
- Order summary and receipt printing
- Offline capability with sync when online

**User Experience:**
- Fast loading with skeleton screens
- Smooth animations and transitions
- Error handling with user-friendly messages
- Network status indicators
- Auto-save functionality

### 4. Real-time Synchronization

**Laravel Echo Integration:**
- WebSocket-based real-time updates
- Event-driven architecture for live updates
- Automatic reconnection on connection loss
- Offline queueing with sync on reconnection

**Real-time Features:**
- Live order updates across multiple devices
- Synchronized inventory management
- Real-time payment processing notifications
- Shift management synchronization
- Customer data synchronization

**Channels Implemented:**
- `user.{userId}` - User-specific updates
- `store.{storeId}.orders` - Store order updates
- `store.{storeId}.payments` - Store payment updates
- `store.{storeId}.stock` - Store inventory updates
- `store.{storeId}.shifts` - Store shift management
- `customers` - Global customer updates

### 5. API Documentation

**OpenAPI 3.0 Specification:**
- Complete API specification in OpenAPI format
- Interactive Swagger UI for testing endpoints
- Request/response examples for all endpoints
- Authentication documentation
- Error response documentation

**Documentation Access:**
- `/api/documentation` - Interactive API documentation
- `/api/documentation/openapi` - Raw OpenAPI specification
- Comprehensive endpoint descriptions
- Code examples in multiple formats

### 6. Security Measures

**Rate Limiting:**
- 60 requests per minute per IP address
- Rate limit headers in responses
- Different limits for different endpoint types
- Configurable throttling rules

**Input Validation:**
- Comprehensive request validation
- Custom error messages for validation failures
- Protection against SQL injection and XSS
- File upload security (if applicable)

**Authorization:**
- Role-based access control
- Permission-based endpoint protection
- Store-specific data isolation
- User context validation

### 7. Testing

**Unit Tests:**
- Comprehensive test coverage for all endpoints
- Authentication flow testing
- Input validation testing
- Error handling testing
- Performance testing

**Test Categories:**
- Authentication tests
- CRUD operations tests
- Business logic tests
- Security tests
- Integration tests

## File Structure

```
app/
├── Http/Controllers/
│   ├── Api/
│   │   ├── AuthController.php          # Authentication endpoints
│   │   ├── ProductController.php       # Product management
│   │   ├── CustomerController.php      # Customer management
│   │   ├── OrderController.php         # Order management
│   │   ├── PaymentController.php       # Payment processing
│   │   ├── StoreController.php         # Store management
│   │   ├── UserController.php          # User management
│   │   └── DocumentationController.php # API documentation
│   └── MobilePosController.php      # Mobile POS routes
├── Services/
│   └── RealtimeSyncService.php       # Real-time synchronization
├── Events/
│   └── RealtimeSyncEvent.php         # Real-time events
└── Models/ (existing)
    ├── User.php                      # User model with Sanctum
    ├── Product.php                   # Product model
    ├── Customer.php                  # Customer model
    ├── Order.php                     # Order model
    ├── Payment.php                   # Payment model
    └── Store.php                     # Store model

resources/
├── views/
│   ├── mobile-pos/
│   │   ├── index.blade.php          # Main POS interface
│   │   └── login.blade.php          # Login page
│   └── api/
│       └── documentation.blade.php   # API documentation
└── js/
    └── mobile-pos-sync.js           # Real-time sync client

routes/
├── api.php                           # API routes
└── web.php                           # Web routes for mobile POS

tests/Feature/Api/
├── AuthControllerTest.php              # Authentication tests
└── ProductControllerTest.php          # Product tests
```

## Usage

### Mobile POS Access

1. **Login Page**: Access `/mobile-pos/login` to authenticate
2. **Main Interface**: After login, redirected to `/mobile-pos`
3. **API Access**: Use `/api` endpoints with Bearer token

### API Authentication

```bash
# Login
curl -X POST http://your-domain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password",
    "device_name": "Mobile POS - iPhone"
  }'

# Use token
curl -X GET http://your-domain.com/api/products \
  -H "Authorization: Bearer your-token-here"
```

### Real-time Events

```javascript
// Initialize real-time sync
const sync = new MobilePosSync({
    apiUrl: 'http://your-domain.com/api',
    token: 'your-token',
    userId: 1,
    storeId: 1
});

// Listen for events
sync.on('order.created', (data) => {
    console.log('New order:', data);
});
```

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Pusher configuration for real-time features
PUSHER_APP_KEY=your-pusher-key
PUSHER_APP_SECRET=your-pusher-secret
PUSHER_APP_ID=your-pusher-app-id
PUSHER_APP_CLUSTER=mt1

# WebSocket configuration
WS_HOST=ws-mt1.pusher.com
WS_PORT=443
FORCE_TLS=true
```

### CORS Configuration

The API is configured to accept requests from mobile applications with proper CORS headers:

```php
// config/cors.php
'allowed_origins' => ['*'],
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'allowed_headers' => ['*'],
'supports_credentials' => true,
'max_age' => 86400,
```

## Deployment

### Requirements

- PHP 8.2 or higher
- Laravel 11.0 or higher
- MySQL 8.0 or higher
- Redis for real-time features (optional)
- Pusher account for WebSocket support

### Setup Instructions

1. **Install Dependencies**:
   ```bash
   composer install
   npm install
   ```

2. **Configure Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Run Migrations**:
   ```bash
   php artisan migrate
   ```

4. **Seed Data** (optional):
   ```bash
   php artisan db:seed
   ```

5. **Start Development Server**:
   ```bash
   php artisan serve
   ```

## API Response Format

All API responses follow a consistent format:

```json
{
    "success": true,
    "message": "Operation successful",
    "data": {
        // Response data here
    }
}
```

Error responses:
```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        // Validation errors (if applicable)
    }
}
```

## Rate Limiting

API requests are limited to prevent abuse:

- **Standard Limit**: 60 requests per minute per IP
- **Strict Limit**: 30 requests per minute for sensitive operations
- **Headers Included**: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`

## Security Considerations

1. **Always use HTTPS** in production
2. **Validate all inputs** before processing
3. **Implement proper error handling** without exposing sensitive information
4. **Use environment variables** for sensitive configuration
5. **Regularly update dependencies** for security patches
6. **Monitor API usage** for unusual patterns

## Performance Optimization

1. **Database Indexing**: Ensure proper indexes on frequently queried columns
2. **Caching**: Implement Redis caching for frequently accessed data
3. **Pagination**: Use pagination for large datasets
4. **Compression**: Enable gzip compression for API responses
5. **CDN**: Use CDN for static assets in mobile interface

## Troubleshooting

### Common Issues

1. **CORS Errors**: Check CORS configuration
2. **Authentication Failures**: Verify token format and headers
3. **Real-time Connection Issues**: Check WebSocket configuration
4. **Mobile Performance**: Optimize images and reduce bundle size
5. **Database Timeouts**: Check database connection and query performance

### Debug Mode

Enable debug mode in mobile POS:
```javascript
// In browser console
localStorage.setItem('debug', 'true');
```

## Future Enhancements

1. **Offline Mode**: Full offline capability with local storage
2. **Push Notifications**: Mobile app push notifications
3. **Advanced Reporting**: More comprehensive analytics
4. **Multi-language Support**: Internationalization
5. **Barcode Scanner Integration**: Hardware scanner support
6. **Receipt Printer Integration**: Direct printer support
7. **Inventory Management**: Advanced inventory features
8. **Customer Loyalty**: Loyalty program integration
9. **Multi-store Support**: Enhanced multi-store capabilities
10. **Performance Analytics**: Advanced performance metrics

## Support

For technical support and questions:
- Documentation: `/api/documentation`
- API Testing: Interactive Swagger UI
- Email: support@supermart.com
- Issues: Report via project issue tracker

## License

This Mobile POS / API implementation is part of the SuperMart POS system and follows the same licensing terms.