# Changelog

All notable changes to the WHMCS Kaza Wallet Payment Gateway will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-06-28

### Added
- Initial release of WHMCS Kaza Wallet Payment Gateway
- Complete payment processing integration with Kaza Wallet API
- Support for both sandbox and live environments
- Secure webhook callback handling with hash verification
- Admin panel configuration with automatic callback URL generation
- Copy-to-clipboard functionality for callback URL
- Comprehensive transaction logging and error handling
- Refund processing support
- Multi-currency support
- Payment status verification and validation
- Responsive admin interface with status indicators
- Helper functions for common operations
- API credential validation
- Installation script for easy setup
- API testing script for troubleshooting
- Comprehensive documentation and setup instructions

### Security
- Hash-based webhook verification using SHA-256
- SSL/TLS encryption for all API communications
- Secure credential storage in WHMCS database
- Input validation and sanitization
- Transaction amount verification
- Invoice validation before payment processing

### Features
- **Payment Processing**: Create secure payment links via Kaza Wallet API
- **Webhook Handling**: Process payment callbacks with status updates
- **Admin Interface**: Enhanced configuration panel with status display
- **Multi-currency**: Support for 30+ currencies
- **Refunds**: Process refunds directly through WHMCS admin
- **Logging**: Comprehensive transaction and error logging
- **Testing**: Sandbox mode for safe testing
- **Validation**: API credential and webhook validation

### Technical Details
- Compatible with WHMCS 7.0+
- Requires PHP 7.0+
- Uses cURL for API communications
- Follows WHMCS payment gateway standards
- PSR-compliant code structure
- Comprehensive error handling and logging

### Files Included
- `kazawallet.php` - Main gateway module
- `callback/kazawallet.php` - Webhook callback handler
- `hooks/kazawallet_admin.php` - Admin interface enhancements  
- `config.php` - Configuration constants
- `helpers.php` - Helper functions
- `install.php` - Installation script
- `test-api.php` - API testing utility
- `README.md` - Comprehensive documentation
- `CHANGELOG.md` - Version history
- `LICENSE` - Mozilla Public License Version 2.0

### Configuration Options
- API Key (required)
- Secret Key (required) 
- Sandbox Mode (optional)
- Payment Description (optional)
- Callback URL (auto-generated)

### Supported Operations
- Create payment links
- Process payment callbacks
- Validate webhook signatures
- Handle payment status updates
- Process refunds
- Log transactions
- Validate API credentials

### Known Issues
- None at initial release

### Migration Notes
- First release, no migration needed

---

## Development Notes

### API Endpoints Used
- `POST /v1/payment-links` - Create payment link
- `POST /v1/refunds` - Process refund
- `GET /v1/verify` - Validate credentials

### Webhook Events Handled
- Payment completed
- Payment failed
- Payment cancelled
- Payment pending

### Future Enhancements
- Subscription/recurring payment support
- Enhanced reporting dashboard
- Multi-language support
- Advanced fraud detection
- Custom payment forms
- Mobile-optimized payment pages
