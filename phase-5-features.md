# Phase 5 Features Documentation

## Overview

Phase 5 introduces advanced features designed for scaling and monetization of the POS system. These features include supplier and purchase management, barcode scanner support, multi-store capabilities, and a comprehensive mobile POS with RESTful API.

## 1. Supplier & Purchase Management

The Supplier & Purchase module provides complete supply chain management capabilities, allowing you to track suppliers, manage purchase orders, and handle supplier payments.

### Features

#### Supplier Management
- **Supplier Registration**: Add and manage supplier information
- **Contact Management**: Store multiple contact details per supplier
- **Supplier Categories**: Organize suppliers by type or category
- **Performance Tracking**: Track supplier reliability and delivery times
- **Credit Management**: Manage supplier credit limits and payment terms

#### Purchase Order Management
- **Purchase Order Creation**: Create detailed purchase orders
- **Order Tracking**: Track order status from creation to delivery
- **Multiple Suppliers**: Compare prices from different suppliers
- **Order Templates**: Create templates for recurring orders
- **Approval Workflow**: Multi-level approval for large orders

#### Supplier Payment Management
- **Payment Tracking**: Track all supplier payments
- **Payment Methods**: Support for multiple payment methods
- **Outstanding Balances**: View outstanding payment balances
- **Payment History**: Complete payment history per supplier
- **Automated Reminders**: Set up payment due reminders

### Usage

#### Adding a New Supplier
1. Navigate to **Suppliers** in the admin panel
2. Click **Add Supplier**
3. Fill in supplier details:
   - Supplier name and code
   - Contact information
   - Address details
   - Payment terms
   - Credit limit
4. Save the supplier

#### Creating a Purchase Order
1. Go to **Purchases** > **Create Purchase**
2. Select the supplier
3. Add products to the order:
   - Select products from inventory
   - Enter quantities and expected prices
   - Set delivery date
4. Review and submit the order
5. Track order status in the purchase list

#### Managing Supplier Payments
1. Navigate to **Supplier Payments**
2. Select the supplier
3. Enter payment details:
   - Payment amount
   - Payment method
   - Reference number
   - Payment date
4. Apply to specific invoices or as advance payment

### Reports
- **Supplier Performance**: Delivery times and quality metrics
- **Purchase History**: Historical purchase data analysis
- **Payment Reports**: Supplier payment tracking
- **Price Comparison**: Compare product prices across suppliers

## 2. Barcode Scanner Support

The barcode scanner feature enables fast and accurate product identification using barcode scanning devices, improving checkout speed and reducing errors.

### Features

#### Barcode Configuration
- **Barcode Generation**: Automatic barcode generation for products
- **Custom Barcodes**: Assign custom barcode numbers
- **Multiple Formats**: Support for EAN-13, UPC-A, Code 128, QR codes
- **Barcode Printing**: Print barcode labels for products

#### Scanner Integration
- **Hardware Support**: Compatible with most USB barcode scanners
- **Scanner Settings**: Configure scanner behavior and settings
- **Mobile Scanning**: Use device camera for barcode scanning
- **Batch Scanning**: Scan multiple items at once

#### Barcode Management
- **Barcode Lookup**: Search products by barcode
- **Duplicate Detection**: Prevent duplicate barcode assignments
- **Barcode History**: Track barcode changes
- **Import/Export**: Bulk import/export barcode data

### Setup

#### Hardware Setup
1. Connect barcode scanner to USB port
2. Configure scanner settings in **Settings > Barcode**
3. Test scanner functionality
4. Configure scanner prefix/suffix if needed

#### Software Configuration
1. Navigate to **Settings > Barcode**
2. Configure scanner settings:
   - Scanner type
   - Input method
   - Auto-submit behavior
   - Error handling
3. Save settings

#### Product Barcode Assignment
1. Go to **Products** > **Edit Product**
2. Enter barcode number manually
3. Or use scanner to assign barcode
4. Generate barcode label if needed

### Usage in POS
1. In the POS screen, focus on the product search field
2. Scan product barcode using scanner
3. Product is automatically added to cart
4. Quantity can be adjusted after scanning

### Troubleshooting
- **Scanner Not Working**: Check USB connection and drivers
- **Incorrect Product**: Verify barcode assignment
- **Slow Response**: Check scanner configuration

## 3. Multi-Store Support

Multi-store support enables managing multiple store locations from a single system, with centralized management and store-specific operations.

### Features

#### Store Management
- **Multiple Stores**: Create and manage unlimited store locations
- **Store Information**: Store details, address, contact information
- **Store Settings**: Configure individual store settings
- **Store Hierarchy**: Parent-child store relationships

#### Inventory Management
- **Store-Specific Stock**: Track inventory per store
- **Stock Transfer**: Move inventory between stores
- **Centralized Purchasing**: Purchase for multiple stores
- **Store-Level Reports**: Individual store performance

#### User Management
- **Store Assignment**: Assign users to specific stores
- **Multi-Store Access**: Grant access to multiple stores
- **Store-Specific Roles**: Different roles per store
- **Cross-Store Reporting**: Consolidated reporting

### Setup

#### Creating Multiple Stores
1. Navigate to **Stores** in admin panel
2. Click **Add Store**
3. Fill in store details:
   - Store name and code
   - Address and contact
   - Business hours
   - Tax settings
4. Save the store

#### Configuring Users for Multi-Store
1. Go to **Users** > **Edit User**
2. Assign stores to the user
3. Set store-specific permissions
4. Configure default store

#### Setting Up Store Inventory
1. Select store from store selector
2. Add products to store inventory
3. Set initial stock levels
4. Configure store-specific pricing

### Operations

#### Switching Between Stores
1. Use store selector in top navigation
2. Select desired store
3. System switches to store context
4. All operations now apply to selected store

#### Stock Transfer
1. Go to **Stock Transfer**
2. Select source and destination stores
3. Add products to transfer
4. Confirm and execute transfer

### Reporting
- **Store Performance**: Individual store metrics
- **Comparative Reports**: Compare store performance
- **Consolidated Reports**: Combined view of all stores
- **Transfer Reports**: Inventory transfer tracking

## 4. Mobile POS / API

The Mobile POS feature provides a mobile-optimized interface and comprehensive RESTful API for mobile devices and third-party integrations.

### Mobile POS Interface

#### Features
- **Responsive Design**: Optimized for smartphones and tablets
- **Touch-Friendly UI**: Large touch targets for easy operation
- **Offline Support**: Basic functionality without internet
- **Real-time Sync**: Synchronize data when online
- **Mobile Payments**: Support for mobile payment methods

#### Access
1. Navigate to `/mobile-pos` on any device
2. Login with existing credentials
3. System detects mobile device
4. Mobile interface loads automatically

#### Mobile-Specific Features
- **Swipe Gestures**: Navigate products with swipes
- **Camera Integration**: Use camera for barcode scanning
- **Geolocation**: Track sales location
- **Push Notifications**: Receive order updates

### RESTful API

#### Authentication
- **Laravel Sanctum**: Token-based authentication
- **Bearer Tokens**: Secure API access
- **Device Management**: Manage multiple device tokens
- **Token Refresh**: Automatic token refresh

#### API Endpoints

##### Authentication
```
POST /api/auth/login      - User login
POST /api/auth/logout     - User logout
GET  /api/auth/user       - Get current user
```

##### Products
```
GET    /api/products           - List products
GET    /api/products/{id}      - Get product details
GET    /api/products/search    - Search products
POST   /api/products           - Create product
PUT    /api/products/{id}      - Update product
DELETE /api/products/{id}      - Delete product
```

##### Orders
```
GET    /api/orders             - List orders
POST   /api/orders             - Create order
GET    /api/orders/{id}        - Get order details
PUT    /api/orders/{id}        - Update order
DELETE /api/orders/{id}        - Delete order
```

##### Customers
```
GET    /api/customers          - List customers
POST   /api/customers          - Create customer
GET    /api/customers/{id}     - Get customer details
PUT    /api/customers/{id}     - Update customer
DELETE /api/customers/{id}     - Delete customer
```

##### Stores
```
GET    /api/stores             - List stores
GET    /api/stores/{id}        - Get store details
GET    /api/stores/{id}/stock  - Get store inventory
```

#### API Usage

##### Authentication Example
```bash
# Login to get token
curl -X POST http://your-domain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password",
    "device_name": "Mobile POS"
  }'

# Use token for authenticated requests
curl -X GET http://your-domain.com/api/products \
  -H "Authorization: Bearer your-token-here"
```

##### Creating an Order
```bash
curl -X POST http://your-domain.com/api/orders \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "items": [
      {
        "product_id": 1,
        "quantity": 2,
        "price": 19.99
      }
    ],
    "payment_method": "cash"
  }'
```

### Real-time Synchronization

#### Features
- **WebSocket Integration**: Real-time data sync
- **Event-Driven Updates**: Automatic updates on data changes
- **Offline Queue**: Queue changes when offline
- **Conflict Resolution**: Handle data conflicts

#### Implementation
- **Laravel Echo**: Real-time event broadcasting
- **Pusher Integration**: WebSocket service
- **Offline Storage**: Local storage for offline data
- **Sync Status**: Visual sync status indicators

## Configuration

### System Requirements

#### Minimum Requirements
- PHP 8.2 or higher
- MySQL 8.0 or higher
- Laravel 11.0 or higher
- Redis for real-time features
- Pusher account for WebSocket

#### Recommended Setup
- Dedicated server or VPS
- SSL certificate
- Backup solution
- Monitoring system

### Environment Configuration

#### Required Environment Variables
```env
# Multi-store support
MULTI_STORE_ENABLED=true
DEFAULT_STORE_ID=1

# Barcode scanner
BARCODE_SCANNER_ENABLED=true
BARCODE_PREFIX=BAR

# Mobile POS
MOBILE_POS_ENABLED=true
API_RATE_LIMIT=60

# Real-time features
PUSHER_APP_KEY=your-pusher-key
PUSHER_APP_SECRET=your-pusher-secret
PUSHER_APP_ID=your-pusher-id
PUSHER_APP_CLUSTER=mt1

# Supplier management
SUPPLIER_MODULE_ENABLED=true
PURCHASE_APPROVAL_REQUIRED=true
```

## Security Considerations

### API Security
- **Rate Limiting**: Prevent API abuse
- **Input Validation**: Validate all API inputs
- **CORS Configuration**: Proper CORS setup
- **HTTPS Only**: Enforce HTTPS in production

### Data Security
- **Store Isolation**: Separate data per store
- **User Permissions**: Strict access control
- **Audit Trail**: Track all data changes
- **Data Encryption**: Encrypt sensitive data

## Performance Optimization

### Database Optimization
- **Indexing**: Proper database indexes
- **Query Optimization**: Efficient database queries
- **Caching**: Redis caching for frequent data
- **Connection Pooling**: Database connection management

### API Performance
- **Response Caching**: Cache API responses
- **Pagination**: Paginate large datasets
- **Compression**: Enable response compression
- **CDN**: Use CDN for static assets

## Troubleshooting

### Common Issues

#### Multi-Store Problems
- **Store Switching**: Clear browser cache
- **Inventory Sync**: Check cron jobs
- **User Access**: Verify user permissions

#### Barcode Scanner Issues
- **Scanner Detection**: Check USB connection
- **Barcode Recognition**: Verify barcode format
- **Product Lookup**: Check barcode assignments

#### Mobile POS Issues
- **Login Problems**: Verify API credentials
- **Sync Issues**: Check network connection
- **Performance**: Optimize database queries

### Debug Tools
- **API Logs**: Detailed API request logs
- **Performance Monitoring**: Track system performance
- **Error Tracking**: Comprehensive error logging
- **Health Checks**: System health monitoring

## Future Enhancements

### Planned Features
- **Advanced Analytics**: AI-powered insights
- **Inventory Forecasting**: Predictive inventory management
- **Supplier Portal**: Self-service supplier portal
- **Mobile App**: Native mobile applications
- **Integration Hub**: Third-party integrations

### Roadmap
1. **Q1 2025**: Advanced analytics dashboard
2. **Q2 2025**: Supplier portal implementation
3. **Q3 2025**: Native mobile apps
4. **Q4 2025**: AI-powered features

## Support

For technical support:
- **Documentation**: Complete API documentation at `/api/documentation`
- **Email**: support@supermart.com
- **Issues**: Report via project issue tracker
- **Community**: Join our Discord community

---

This documentation covers all Phase 5 features. For more information about other features, refer to the appropriate documentation files.