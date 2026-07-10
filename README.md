# Royal Carrybee - WooCommerce Shipping Integration

![WordPress](https://img.shields.io/badge/WordPress-5.6+-blue.svg)
![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0+-purple.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4.svg)
![License](https://img.shields.io/badge/License-GPL--2.0-green.svg)

A professional WooCommerce shipping integration plugin for [Carrybee](https://carrybee.com) courier service in Bangladesh.

## 🚀 Features

- **One-Click Order Dispatch** - "Sent to Carrybee" button in WooCommerce Orders list table (`edit.php?post_type=shop_order` & HPOS `wc-orders`) for instant 1-click booking without opening the order
- **Smart Address Auto-Fill** - Intelligent city and zone matching from customer shipping/billing address on order edit page
- **Address Preview & Badge** - Clean address preview summary and visual `✨ Auto-filled` indicator inside order metabox
- **WooCommerce Shipping Method** - Adds Carrybee as a shipping option
- **Cascading Location Dropdowns** - City → Zone → Area selection at checkout
- **Auto Order Creation** - Automatically creates Carrybee parcel on order status change
- **Bulk Actions** - Send multiple orders to Carrybee at once & Bulk Sync Status
- **Real-time Tracking** - Consignment ID saved and displayed in custom table column
- **Webhook Support** - Automatic order status updates
- **Multi-Store Support** - Manage multiple pickup stores
- **Sandbox Mode** - Test before going live
- **Admin Meta Box** - Tracking info and instant management on order edit page
- **Email Integration** - Tracking info in customer emails

## 📋 Requirements

- WordPress 5.6 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Carrybee API credentials

## 📦 Installation

1. Download the plugin zip file
2. Go to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin** and select the zip file
4. Click **Install Now** and then **Activate**

Or clone directly to your plugins folder:

```bash
cd wp-content/plugins/
git clone https://github.com/yourusername/royal-carrybee.git
```

## ⚙️ Configuration

### 1. API Credentials

1. Go to **WooCommerce → Carrybee**
2. Select your environment (Sandbox/Production)
3. Enter your API credentials:
   - Client ID
   - Client Secret
   - Client Context
4. Click **Test Connection** to verify

### 2. Shipping Zone Setup

1. Go to **WooCommerce → Settings → Shipping**
2. Add or edit a shipping zone
3. Add **Carrybee** shipping method
4. Configure:
   - Flat rate amount
   - Free shipping threshold
   - Delivery type (Normal/Express)

### 3. Default Store

1. In Carrybee settings, go to **Stores** tab
2. Add your pickup store(s)
3. Select a default store in **General Settings**

## 🔗 Webhook Setup

To receive real-time order updates:

1. Copy the webhook URL from settings
2. Provide it to Carrybee along with your webhook secret
3. Carrybee will send status updates automatically

Webhook URL format:
```
https://yourdomain.com/wp-json/royal-carrybee/v1/webhook
```

## 📍 Checkout Fields

The plugin adds these fields to checkout:
- **City** - Dropdown of all Carrybee cities
- **Zone** - Zones within selected city
- **Area** - Areas within selected zone

## 🧪 Sandbox Testing

Use these credentials for testing:

| Field | Value |
|-------|-------|
| Base URL | https://stage-sandbox.carrybee.com |
| Client ID | `1a89c1a6-fc68-4395-9c09-628e0d3eaafc` |
| Client Secret | `1d7152c9-5b2d-4e4e-9c20-652b93333704` |
| Client Context | `DzJwPsx31WaTbS745XZoBjmQLcNqwK` |

## 📸 Screenshots

### Settings Page
Modern card-based settings with tabs for General, Stores, Orders, and About.

### Checkout
Cascading City/Zone/Area dropdowns for accurate delivery location.

### Order Tracking
Carrybee meta box on order page showing tracking status, fees, and delivery attempts.

## 🔄 Webhook Events

The plugin handles these Carrybee events:

| Event | Action |
|-------|--------|
| `order.created` | Save fees to order |
| `order.picked` | Add order note |
| `order.in-transit` | Add order note |
| `order.delivered` | Mark order completed |
| `order.delivery-failed` | Add note with reason |
| `order.returned-to-merchant` | Cancel order |
| `order.paid` | Save invoice ID |

## 🗂️ File Structure

```
royal-carrybee/
├── royal-carrybee.php          # Main plugin file
├── includes/
│   ├── class-carrybee-api.php          # API handler
│   ├── class-carrybee-settings.php     # Settings page
│   ├── class-carrybee-shipping-method.php
│   ├── class-carrybee-checkout.php     # Checkout fields
│   ├── class-carrybee-order.php        # Order processing
│   ├── class-carrybee-webhook.php      # Webhook handler
│   └── class-carrybee-admin.php        # Admin enhancements
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── checkout.js
└── languages/                   # Translation files
```

## 🌐 Translations

The plugin is translation-ready with the `royal-carrybee` text domain.

To translate:
1. Use a tool like Poedit
2. Create translation files in `/languages/`
3. Name format: `royal-carrybee-{locale}.po`

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📄 License

This plugin is licensed under the [GPL-2.0](LICENSE) license.

## 👨‍💻 Developer

**Royal Technologies**

- Website: [royaltechbd.com](https://royaltechbd.com)
- Email: info@royaltechbd.com
- Phone: +880 1552-333272

## 📝 Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

---

Made with ❤️ by [Royal Technologies](https://royaltechbd.com)
