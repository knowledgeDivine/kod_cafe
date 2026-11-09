# K.O.D Café — Final Launch Checklist

This checklist is for moving the already-working K.O.D installation from local XAMPP to a public production host. It is not a new design phase.

## A. Before uploading

- [ ] Freeze feature development for this release.
- [ ] Back up the entire local `C:\xampp\htdocs\KOD_Cafe` folder.
- [ ] In phpMyAdmin, export the complete `kod_cafe` database as SQL (Structure and Data).
- [ ] Keep the database export and file backup in two separate, private locations.
- [ ] Confirm the latest local site works: homepage, menu, cart, checkout, admin, orders, tracking, account, favourites, showroom, AI Barista and analytics.
- [ ] Confirm the showroom works while signed in and while signed out. The final release includes the `$showroomFavoriteIds` scope fix.
- [ ] Remove temporary test files such as `test-curl.php`, `phpinfo.php`, and any one-time setup scripts from the web root.

## B. Hosting requirements

Choose PHP hosting that supports:

- PHP 8.2 or newer.
- MySQL 5.7+ or a compatible MariaDB release.
- PHP extensions: `pdo_mysql`, `curl`, `mbstring`, `json`, `openssl`.
- Apache 2.4 with `.htaccess` / `AllowOverride All`, or an equivalent server configuration for denying private files and folders.
- HTTPS with a valid TLS certificate.
- Scheduled backups or a way to export MySQL and site files regularly.

For the 3D showroom and remote product photography, the visitor's browser also needs WebGL and internet access to load the Three.js CDN and remote images.

## C. Prepare the production database

1. Create a new database on the hosting account, for example `kod_cafe` (the host may prepend the account name).
2. Create a dedicated database user, for example `kod_app`, with a long unique password.
3. Grant this application user only the privileges needed at runtime: `SELECT`, `INSERT`, `UPDATE`, and `DELETE`. Run any schema migrations using the hosting control panel's database administrator, then reduce the app user's privileges if the host allows it.
4. Import the SQL export from the current local `kod_cafe` database. This preserves your existing admin, products, customers, orders, loyalty points, favourites and preferences.
5. Do not import the original `database/database.sql` over the production database after restoring your existing SQL export.
6. Do not re-run old upgrade migrations on a database that already contains those tables and columns. The current project has already had its Phase 9 personalization migration applied.
7. Confirm the restored database contains `admins`, `menu_items`, `customers`, `orders`, `order_items`, `order_status_history`, `loyalty_transactions`, `customer_preferences`, `customer_favorites`, and `birthday_rewards`.

For a brand-new database only: import `database/database.sql`, then import `database/add_order_tracking.sql` once. The base `database.sql` already includes the menu, customers, orders, order items, loyalty, and Phase 9 personalization tables. Do not also import `add_online_orders.sql`, `add_customer_loyalty.sql`, or `add_customer_personalization.sql` on top of that base schema. Those files are upgrade migrations for older database versions and may conflict with tables/columns already present.

## D. Upload and configure

1. Upload the contents of the `KOD_Cafe` folder to the domain's document root (or to a dedicated folder if the domain points to that folder).
2. Keep the `.htaccess` file and the internal `.htaccess` files inside `config/`, `database/`, and `storage/`.
3. Create a private `.env` file from `.env.production.example`. Do not name it `.env.production.example` on the server; the application reads `.env` only.
4. Set `APP_ENV=production` and `APP_DEBUG=false`.
5. Set `APP_URL` to the exact public HTTPS URL, such as `https://your-domain.example`.
6. Set the production database host, database name, database username and password from the hosting control panel. Do not use the XAMPP `root` user on a public host.
7. Add `OPENAI_API_KEY` only if you want the live AI Barista. Keep it in `.env` on the server; never put it in HTML, JavaScript, screenshots or public source control.
8. Keep `OPENAI_MODEL=gpt-6-luna` for a lower-cost recommendation workload, or select another model that is enabled for your API project.
9. Configure the domain's HTTPS certificate and force HTTP traffic to HTTPS using the host's control panel or web-server configuration.
10. Confirm the hosting provider allows `.htaccess` overrides. If not, ask the host to apply equivalent deny rules for `.env`, `config/`, `database/`, and `storage/` at the virtual-host level.
11. In the hosting PHP settings, set `display_errors=Off` and `log_errors=On`. The application bootstrap also honours `APP_DEBUG`, but PHP startup/parse errors can happen before the bootstrap is loaded.

## E. Public security checks

Run these checks in a private/incognito browser window after deployment:

- [ ] `https://your-domain.example/.env` returns 403 or 404; it must never show file contents.
- [ ] `https://your-domain.example/.env.production.example` returns 403 or 404.
- [ ] `https://your-domain.example/config/app.php` is denied.
- [ ] `https://your-domain.example/database/database.sql` is denied.
- [ ] `https://your-domain.example/storage/` is denied.
- [ ] Directory listing is disabled.
- [ ] HTTP redirects to HTTPS.
- [ ] PHP errors are not displayed to visitors. They should be written to the host's private PHP/Apache error log.
- [ ] No API key, database password, test script or setup script is present in public source control or the public web root.
- [ ] Admin login requires a password and the original admin account is still accessible.
- [ ] Change any temporary or shared administrator password before opening the site to customers.

## F. Customer and business smoke tests

Use a small test order and mark it clearly as a test in the order note.

- [ ] Homepage loads on desktop and mobile.
- [ ] Menu items and images load; prices match the admin menu.
- [ ] 3D homepage scene and 3D showroom render; controls rotate, zoom and reset.
- [ ] Showroom favourites work for a signed-in customer and the showroom loads for a signed-out visitor.
- [ ] Customer registration, login, logout and account page work.
- [ ] Taste profile saves and favourite products can be added/removed.
- [ ] Birthday reward is awarded only when eligible and never twice in one calendar year.
- [ ] Cart quantities and server-side checkout price validation work.
- [ ] Pickup and delivery checkout both validate correctly.
- [ ] Order confirmation and private tracking link work.
- [ ] Admin can update fulfilment status and estimated ready/delivery time.
- [ ] Admin can mark an order paid only after actually receiving payment.
- [ ] Completed orders grant loyalty points according to the configured rules.
- [ ] Loyalty redemption lowers the final order total correctly.
- [ ] Analytics reports use real production orders and payments.
- [ ] AI Barista returns a live AI response if a valid key and API billing are configured; otherwise local guide mode is clearly understood as a fallback.
- [ ] Contact and newsletter submissions are saved.

## G. Business operations that are NOT yet connected

Do not advertise these as live until separately configured and tested:

- Online card/bank payment processing (Paystack was intentionally deferred). The current flow is pay on pickup or pay on delivery, with payment status recorded manually by an admin.
- Transactional email, email verification, automated password reset and order-email notifications.
- A third-party delivery provider, driver dispatch and GPS/live map tracking.
- Inventory depletion, ingredient costing, profit accounting, tax calculation or payroll.
- Legal pages and business-specific policies. Add and review privacy, terms, refund/cancellation, delivery and allergen information before accepting public customers.

## H. Backups, monitoring and rollback

- [ ] Schedule regular database backups and retain several dated copies.
- [ ] Back up uploaded files and the private `.env` file securely (do not store secrets in a public folder).
- [ ] Restrict who can access hosting control panel, database and admin accounts.
- [ ] Monitor PHP/Apache logs after launch and review failed order/API requests.
- [ ] Before any future upgrade, back up both files and database.
- [ ] If a release fails, restore the previous files and matching database backup together.

## Go-live decision

Go live only after all checks in sections C, D, E and F pass on the production host. A successful local XAMPP test is not proof that DNS, HTTPS, production MySQL permissions, email, payment or hosting configuration are working.
