# Changelog

All notable changes to Royal Carrybee will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [26.07.10] - 2026-07-10

### Added
- **One-Click Send from Orders Table**: Added "Sent to Carrybee" button directly in the WooCommerce Orders List table (`edit.php?post_type=shop_order` and `admin.php?page=wc-orders`).
- **Server-Side Smart Auto-Matching**: Automatically matches order city and zone from customer shipping/billing address when clicking the 1-click button or quick action icon.
- **Metabox Address Preview & Auto-Fill**: Added a modern address preview box and cascading auto-fill with visual "✨ Auto-filled" badge in the single order meta box.
- **Custom Order Table Column**: Displays consignment ID and tracking status alongside a quick "Sync" button directly inside the order list rows.

## [1.0.0] - 2026-01-18

### Added
- Initial release
- Carrybee API v2.0 integration
- WooCommerce shipping method with flat rate and free shipping threshold
- City/Zone/Area cascading dropdowns at checkout
- Automatic Carrybee order creation on WooCommerce order
- Webhook endpoint for real-time status updates
- Admin settings page with tabs (General, Stores, Orders, About)
- SweetAlert2 for save feedback
- DataTables for stores and orders listing
- Order meta box with tracking info, sync and cancel buttons
- Tracking info in customer emails
- Multi-store support
- Sandbox/Production environment toggle
- Webhook signature verification
