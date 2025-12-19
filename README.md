# SuperMart POS System

A comprehensive Point of Sale (POS) system built with Laravel and FilamentPHP, designed for retail businesses of all sizes. SuperMart POS provides complete inventory management, sales tracking, reporting, and multi-store capabilities with a modern, responsive interface.

## 🌟 Features Overview

SuperMart POS has evolved through 5 development phases, each adding critical business functionality:

### 📊 Phase 0: Foundation
- Basic Laravel application setup
- FilamentPHP admin panel integration
- User authentication system
- Database structure foundation

### 🛍️ Phase 1: Core POS Features
- **Product Management**: Complete CRUD operations for products
- **POS Checkout Screen**: Intuitive sales interface
- **Invoice Generation**: Professional invoice creation
- **Sales Recording**: Transaction history and tracking
- **Basic Reports**: Essential sales analytics

### 👥 Phase 2: Customer & Sales Enhancement
- **Customer Management**: Customer database and history
- **Discount System**: Item-level and bill-level discounts
- **Tax/GST Configuration**: Flexible tax management
- **Multiple Payment Types**: Cash, card, UPI, bank transfer
- **Low Stock Alerts**: Automated inventory notifications

### 🔐 Phase 3: Operations & Security
- **User Roles**: Admin, Manager, and Cashier permissions
- **Stock Adjustment**: Manual inventory corrections
- **Cashier Shift System**: Shift management with handover
- **Sales Return/Refund**: Complete return processing

### 📈 Phase 4: Business Intelligence & Reporting
- **Product Sales Report**: Top/low selling products, trends, category analysis
- **Cashier Performance Report**: Staff productivity metrics and KPIs
- **Profit Report**: Comprehensive profit analysis with COGS tracking
- **Advanced Filtering**: Date ranges, stores, categories, products
- **Data Visualization**: Charts, graphs, and heat maps
- **Export Capabilities**: PDF, Excel, CSV formats

### 🚀 Phase 5: Advanced Enterprise Features
- **Supplier & Purchase Management**: Complete supply chain management
- **Barcode Scanner Support**: Hardware integration and mobile scanning
- **Multi-Store Support**: Unlimited locations with centralized management
- **Mobile POS & API**: RESTful API with mobile-optimized interface
- **Real-time Synchronization**: WebSocket-based live updates

## 🏗️ Technical Architecture

### Backend Technologies
- **Framework**: Laravel 11.x
- **Admin Panel**: FilamentPHP 4.x
- **Database**: MySQL 8.0+ / MariaDB 10.6+
- **Authentication**: Laravel Sanctum (API)
- **Real-time**: WebSocket with Pusher integration
- **Caching**: Redis for performance optimization

### Frontend Technologies
- **UI Framework**: FilamentPHP components
- **Mobile Interface**: Responsive touch-friendly design
- **Real-time Updates**: Laravel Echo & WebSocket
- **Barcode Processing**: Multiple format support

### Key Libraries
- `laravel/sanctum`: API authentication
- `mpdf/mpdf`: PDF generation
- `picqer/php-barcode-generator`: Barcode creation
- `pxlrbt/filament-excel`: Excel exports
- `spatie/laravel-permission`: Role-based access control

## 📱 Mobile POS & API

The system includes a comprehensive RESTful API and mobile-optimized interface:

### API Endpoints
- **Authentication**: Login, logout, user management
- **Products**: CRUD operations, search, barcode lookup
- **Orders**: Create, update, track orders
- **Customers**: Management and search
- **Stores**: Details, inventory, statistics
- **Payments**: Processing and tracking

### Mobile Features
- Touch-friendly interface optimized for smartphones and tablets
- Offline capability with automatic synchronization
- Camera-based barcode scanning
- Real-time inventory updates
- Multiple payment method support

### API Documentation
Interactive API documentation available at `/api/documentation` with complete OpenAPI 3.0 specification.

## 🏪 Multi-Store Management

SuperMart POS supports unlimited store locations from a single installation:

### Store Features
- Individual store configuration and settings
- Store-specific inventory tracking
- Stock transfer between locations
- Store assignment for users
- Cross-store reporting and analytics
- Consolidated performance metrics

### User Management
- Store-specific user assignments
- Multi-store access permissions
- Role-based access control per store
- Centralized user administration

## 📊 Business Reports

### Product Sales Report
- Top and low selling products identification
- Sales trends over time periods
- Category-based sales breakdown
- Stock correlation analysis
- Export to PDF, Excel, and CSV

### Cashier Performance Report
- Individual cashier sales tracking
- Transaction analysis and metrics
- Shift performance evaluation
- Error tracking (refunds, voids)
- Productivity metrics and benchmarks

### Profit Report
- Overall profit analysis and margins
- Product-level profitability tracking
- Category profit breakdown
- Cost of goods sold (COGS) tracking
- Profit trend analysis over time

## 🔧 Installation & Setup

### System Requirements
- **PHP**: 8.2 or higher
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Database**: MySQL 8.0+ or MariaDB 10.6+
- **Memory**: 2GB RAM minimum (4GB+ recommended)
- **Storage**: 10GB available space
- **SSL**: Valid SSL certificate (required for mobile POS)

### Quick Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/ak/laravel-easy-pos.git
   cd laravel-easy-pos
   ```

2. **Install dependencies**
   ```bash
   composer install --optimize-autoloader --no-dev
   npm install
   npm run build
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Set up database**
   ```sql
   CREATE DATABASE supermart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

5. **Run migrations**
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=DatabaseSeeder --force
   ```

6. **Start the application**
   ```bash
   php artisan serve
   ```

### Detailed Setup Instructions

For comprehensive installation, configuration, and deployment instructions, please refer to our [Setup & Configuration Guide](setup-configuration.md).

## 🔐 Security Features

- **Role-based Access Control**: Granular permissions for all user roles
- **API Authentication**: Laravel Sanctum token-based authentication
- **Rate Limiting**: Prevent API abuse with configurable limits
- **Data Encryption**: Sensitive data encryption at rest
- **Audit Trail**: Complete logging of all system activities
- **CORS Configuration**: Secure cross-origin resource sharing
- **Input Validation**: Comprehensive request validation and sanitization

## 📈 Performance Optimizations

- **Database Indexing**: Strategic indexes for optimal query performance
- **Caching**: Redis implementation for frequently accessed data
- **Lazy Loading**: Efficient data loading for large datasets
- **Pagination**: Paginated lists and reports
- **Image Optimization**: Compressed images for mobile performance
- **Response Compression**: Gzip compression for API responses

## 🔄 Recent Updates & Fixes

### StoreSelector Component Fix (v2.0.1)
- Fixed store selection persistence across page refreshes
- Improved error handling for invalid store selections
- Enhanced performance for stores with large inventories
- Added visual indicators for active store context

### Version 2.0.0 Highlights
- Complete Phase 4 Reports implementation
- Full Phase 5 Advanced Features deployment
- Enhanced security with Laravel Sanctum
- Improved mobile POS performance
- Comprehensive API documentation

## 📚 Documentation

- [Setup & Configuration Guide](setup-configuration.md) - Complete installation and configuration
- [Phase 4 Reports Documentation](phase-4-reports.md) - Detailed reporting features guide
- [Phase 5 Features Documentation](phase-5-features.md) - Advanced features overview
- [Mobile POS API Documentation](mobile-pos-api-documentation.md) - API reference and usage
- [CHANGELOG.md](CHANGELOG.md) - Version history and updates

## 🗂️ Project Structure

```
app/
├── Console/                    # Artisan commands
├── Events/                     # Event definitions
├── Exceptions/                 # Exception handlers
├── Filament/                   # FilamentPHP components
│   ├── Components/            # Custom Filament components
│   ├── Pages/                 # Custom admin pages
│   ├── Resources/             # Resource definitions
│   └── Widgets/               # Dashboard widgets
├── Http/                      # HTTP layer
│   ├── Controllers/           # Web and API controllers
│   └── Middleware/            # Custom middleware
└── Models/                    # Eloquent models

database/
├── factories/                 # Model factories
├── migrations/                # Database migrations
└── seeders/                   # Database seeders

resources/
├── views/                     # Blade templates
│   ├── mobile-pos/           # Mobile POS interface
│   └── api/                  # API documentation
└── js/                       # JavaScript assets

routes/
├── api.php                    # API routes
└── web.php                    # Web routes
```

## 🧪 Testing

The system includes comprehensive test coverage:

- **Unit Tests**: Model and service layer testing
- **Feature Tests**: Complete workflow testing
- **API Tests**: All endpoints with authentication
- **Integration Tests**: Cross-component functionality

Run tests with:
```bash
php artisan test
```

## 🤝 Contributing

We welcome contributions to the SuperMart POS system! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For technical support and questions:

- **Documentation**: Check our comprehensive documentation files
- **API Reference**: Interactive documentation at `/api/documentation`
- **Issues**: Report via project issue tracker
- **Email**: support@supermart.com
- **Community**: Join our Discord community

## 🗺️ Roadmap

### Version 2.1.0 (Planned)
- Advanced analytics dashboard
- AI-powered inventory forecasting
- Enhanced mobile app features
- Supplier portal implementation

### Version 2.2.0 (Planned)
- Integration with accounting systems
- Loyalty program management
- Advanced customer management
- Enhanced barcode features

## 🏆 Acknowledgments

- [Laravel](https://laravel.com/) - The PHP framework for web artisans
- [FilamentPHP](https://filamentphp.com/) - Elegant admin panels for Laravel
- [Laravel Sanctum](https://laravel.com/docs/sanctum) - API authentication package
- All contributors who have helped make this project better

---

**SuperMart POS** - Empowering businesses with powerful, scalable, and user-friendly point of sale solutions.