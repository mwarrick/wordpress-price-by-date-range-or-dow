# Dynamic Pricing by Date (WooCommerce)

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

### 🛠️ Admin Features
- **Separate Settings Forms**: Time range settings and pricing rules have independent forms
- **Diagnostics Page**: Built-in debugging tools for troubleshooting
- **Date Validation**: Prevents selection of past dates
- **Nonce Security**: All forms protected with WordPress nonces
- **Multisite Compatible**: Works with WordPress multisite installations

### 🔧 Technical Features
- **Timezone Aware**: Properly handles WordPress site timezone settings
- **AJAX Price Updates**: Smooth user experience with real-time price changes
- **Cart Integration**: Selected date/time stored in cart and order data
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
2. Scroll to **Dynamic Pricing by Date** metabox
3. Configure rules (same options as global rules)
4. Product rules override global rules

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

For issues or feature requests, please check the diagnostics page first, then contact support with:
- WordPress version
- WooCommerce version
- PHP version
- Any error messages from the diagnostics page

## Version History

- **1.2.2**: Stable release with time range settings, improved validation, and enhanced debugging
- **1.2.1**: Fixed rule matching logic and timezone handling
- **1.2.0**: Added frontend date/time selection and AJAX price updates
- **1.1.0**: Initial release with basic pricing rules

