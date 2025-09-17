# Dynamic Pricing by Date (WooCommerce) v1.6.0

A comprehensive WordPress plugin that dynamically adjusts WooCommerce product prices based on date and time selection. Perfect for tour businesses, event bookings, and time-sensitive pricing.

## Features

### 🎯 Dynamic Pricing Rules
- **Global Rules**: Apply pricing rules across all products
- **Product-Specific Rules**: Override global rules for individual products
- **Day-of-Week Filtering**: Target specific days (e.g., weekend surcharges)
- **Date Range Support**: Set rules for specific date periods
- **Percentage or Fixed Amount**: Choose between percentage increases/decreases or fixed amounts

### ⏰ Frontend Date/Time Selection
- **User-Friendly Interface**: Clean date picker and time dropdown
- **Required Fields**: Both date and time must be selected before adding to cart
- **Real-Time Price Updates**: Prices update instantly as users select dates/times
- **Configurable Time Range**: Admin can set business hours (e.g., 6 AM to 8 PM)
- **30-Minute Intervals**: Time selection in convenient 30-minute increments
- **Transparent Pricing**: Clear explanations in cart showing why prices differ from product page

### 🛠️ Admin Features
- **Separate Settings Forms**: Time range settings and pricing rules have independent forms
- **Diagnostics Page**: Built-in debugging tools for troubleshooting
- **Date Validation**: Prevents selection of past dates
- **Nonce Security**: All forms protected with WordPress nonces
- **Multisite Compatible**: Works with WordPress multisite installations

### 🔧 Technical Features
- **Timezone Aware**: Properly handles WordPress site timezone settings
- **AJAX Price Updates**: Smooth user experience with real-time price changes
- **Complete Cart Integration**: Selected date/time stored in cart and order data with consistent pricing
- **Variable Product Support**: Full support for WooCommerce variable products and variations
- **Transparent Pricing Display**: Clear explanations of price adjustments in cart and orders
- **WooCommerce Compatible**: Works with all WooCommerce product types
- **Translation Ready**: Full internationalization support

## Installation

1. Upload the plugin folder to `/wp-content/plugins/` or install via WordPress admin
2. Activate the plugin in **Plugins → Installed Plugins**
3. Ensure WooCommerce is active (required dependency)

## Configuration

### Global Settings
Navigate to **WooCommerce → Dynamic Pricing by Date**:

1. **Time Range Settings**:
   - Set start time (e.g., 06:00 for 6 AM)
   - Set end time (e.g., 20:00 for 8 PM)
   - Click "Save Time Settings"

2. **Global Pricing Rules**:
   - Enable/disable rules
   - Select day of week (optional)
   - Set date range (optional)
   - Choose percentage or fixed amount
   - Set increase/decrease direction
   - Click "Save Rules"

### Product-Specific Rules
1. Edit any WooCommerce product
2. Click the **Dynamic Pricing by Date** tab in the product data section
3. Configure rules (same options as global rules)
4. Use "Save Pricing Rules" to save only the pricing rules
5. Use "Delete All Rules" to remove all product-specific rules
6. Product rules override global rules

## Usage Examples

### Weekend Surcharge
- **Day of Week**: Sunday (0)
- **Type**: Percentage
- **Direction**: Increase
- **Amount**: 200
- **Result**: 200% of original price (double the price)

### Holiday Premium
- **Date Range**: 2024-12-20 to 2024-12-31
- **Type**: Fixed
- **Direction**: Increase
- **Amount**: 50
- **Result**: Original price + $50

### Off-Peak Discount
- **Day of Week**: Tuesday (2)
- **Type**: Percentage
- **Direction**: Decrease
- **Amount**: 20
- **Result**: 80% of original price (20% discount)

## Cart Transparency

When dynamic pricing is applied, users see clear explanations:

### Cart Display Example
**Product**: Tour Package (Base: $100-$175)
**Selected**: Sunday, 2 people ($125 base price)
**Cart Shows**:
- **Selected Date/Time**: 09/21/2025 @ 08:00
- **Pricing Adjustment**: Price increased by 100% for Sunday (Base: $125.00)
- **Final Price**: $250.00

### General Cart Notice
When any items have dynamic pricing, a blue notice appears:
> **Dynamic Pricing Applied**  
> Prices shown below reflect dynamic pricing adjustments based on your selected dates and times. See individual item details for specific adjustments.

## Diagnostics

Access **WooCommerce → DPD Diagnostics** for:
- System information
- Rule testing
- Price calculation verification
- AJAX functionality testing

## Requirements

- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.4+

## Support

**⚠️ IMPORTANT: This software is provided AS-IS with no warranty or support.**

This plugin is released to the public for educational and commercial use without any guarantee of functionality, compatibility, or ongoing support. Users are responsible for:

- Testing the plugin in their specific environment
- Ensuring compatibility with their WordPress/WooCommerce setup
- Implementing any necessary customizations
- Handling any issues that may arise

**No technical support, bug fixes, or feature requests will be provided.**

For troubleshooting, use the built-in diagnostics page at **WooCommerce → DPD Diagnostics**.

## Version History

- **1.6.0**: Stable release with transparent pricing - Added cart pricing explanations, fixed CSS styling issues, removed interfering icons, enhanced user experience with clear pricing breakdowns
- **1.5.0**: Complete cart integration - Fixed cart pricing for variable products, added comprehensive cart item pricing hooks, resolved frontend-to-cart price consistency, enhanced debugging system
- **1.4.0**: Major stability improvements - Fixed duplicate UI rendering, enhanced per-product rules management, improved debugging, removed debug messages, better multisite compatibility
- **1.3.0**: Enhanced frontend experience - Friendly time dropdowns, required field validation, global time range settings, improved AJAX handling
- **1.2.2**: Stable release with time range settings, improved validation, and enhanced debugging
- **1.2.1**: Fixed rule matching logic and timezone handling
- **1.2.0**: Added frontend date/time selection and AJAX price updates
- **1.1.0**: Initial release with basic pricing rules

