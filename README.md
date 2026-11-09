# K.O.D Café — XAMPP / PHP / MySQL

K.O.D is a premium, responsive café website and operating dashboard built with PHP, MySQL, HTML, CSS and JavaScript. This package includes the existing public café experience, procedural 3D showroom, checkout, customer accounts, loyalty rewards, order tracking and the Phase 7 business analytics dashboard.

## Technology and requirements

- XAMPP with Apache and MySQL/MariaDB.
- PHP 8.2 or newer.
- PDO MySQL (`pdo_mysql`), `mbstring` and JSON extensions enabled.
- Modern browser with WebGL for the 3D scenes.
- Internet access for Three.js, Google Fonts and remote product photography.

## Current feature set

### Public café website

- Responsive editorial-style homepage and premium brown, cream and grey identity.
- Procedural 3D coffee cup and dessert scenes, including scroll-triggered coffee splash motion.
- Coffee Colour Studio with selectable coffee shades and drink recommendations.
- MySQL-driven menu with product descriptions, prices and images.
- Dedicated 3D product showroom at `showroom.php`, with product selection, rotate, zoom, reset and add-to-bag actions.
- Shopping bag, quantity controls and server-side price validation at checkout.
- Pickup and delivery checkout. No online card-payment gateway is connected; customers pay at pickup or delivery.
- Customer registration, sign-in, profile management, password changes and order history.
- K.O.D Club welcome reward, loyalty points, redemption and transaction history.
- Private order tracking links and a customer-facing order progress timeline.
- Contact form, newsletter signup and coffee recommendation records.

### Admin workspace

- Administrator authentication using password hashing and PHP sessions.
- Menu management: add, edit, hide, publish and delete menu items.
- Order desk: view customer orders, update fulfilment stage and estimated ready/delivery time.
- Payment recording: manually mark an order Paid after receiving payment, or Refunded after returning money. The website does not process payment itself.
- Customer directory and audited loyalty point adjustments.
- Contact enquiry management.
- Business analytics dashboard at `admin/analytics.php`.

## Phase 7 — Business analytics dashboard

Open `http://localhost/KOD_Cafe/admin/analytics.php` after signing in as an administrator. The Analytics link is also available in the admin sidebar and on the admin overview page.

The report includes:

- Paid revenue: order totals marked `paid`, excluding cancelled orders.
- Fulfilled order value: totals for orders marked `completed`; this is not necessarily money collected.
- Valid order count, average order value, unique buyers and cancellation rate.
- Daily order-value and paid-revenue trend chart.
- Repeat buyers: email addresses with at least two non-cancelled orders in the selected date range.
- Category performance and best-selling products by units and order-item value.
- New K.O.D Club member sign-ups and loyalty points earned/redeemed.
- Order-status and payment-status summaries.
- Top customers for the selected period.
- CSV export of order records for the selected period.

### Analytics filters

Choose the last 7 days, last 30 days, last 90 days, year to date or custom dates. Custom reports are limited to 365 days. The date range is based on each order's creation date.

### Important reporting definitions

- **Paid revenue** counts only records whose `payment_status` is `paid`. Mark cash or transfer orders as paid in the admin order desk only after money has actually been received.
- **Fulfilled order value** shows completed orders even when their payment record is still unpaid.
- **Order value** is not profit. K.O.D does not currently store ingredient costs, labour, tax, delivery costs or other expenses, so the dashboard does not claim to calculate profit.
- Category and product values come from order-item price snapshots before any order-level loyalty discount. Paid revenue uses the final order total after discount.
- Repeat-buyer percentage is calculated within the selected period, not across the customer's entire lifetime.
- CSV exports include customer contact details. Keep exported files private.

## Install or update K.O.D in XAMPP

### Update an existing K.O.D installation (the current owner's recommended path)

1. Back up `C:\xampp\htdocs\KOD_Cafe` and export the full `kod_cafe` database from phpMyAdmin.
2. Extract this release ZIP. Copy the contents of its `KOD_Cafe` folder into the existing `C:\xampp\htdocs\KOD_Cafe` folder and allow application files to be replaced.
3. **Keep your existing `.env` file.** This release does not include a live `.env` file or any credentials.
4. No SQL migration is needed when updating the current Phase 9 installation. Do not re-import the base schema or old upgrade migrations.
5. Start Apache and MySQL in XAMPP, then check the homepage, admin login, menu, order desk, account, favourites and showroom.

### Fresh local installation

1. Copy the project folder into `C:\xampp\htdocs\KOD_Cafe`.
2. Create a database named `kod_cafe` and import `database/database.sql` in phpMyAdmin. This consolidated base SQL already includes the menu, customer accounts, orders, order items, loyalty and Phase 9 personalization tables.
3. Import `database/add_order_tracking.sql` once. It adds the `out_for_delivery` status, `estimated_ready_at` field and `order_status_history` table.
4. Do **not** also import `add_online_orders.sql`, `add_customer_loyalty.sql` or `add_customer_personalization.sql` on top of the consolidated base SQL. Those are historical upgrade migrations for older database versions and may conflict with tables or columns already present.
5. Create `.env` using `.env.example` for local XAMPP, or use `.env.production.example` as a template on a public host. Set your database credentials and the correct `APP_URL`.
6. This package intentionally does not include a web-accessible first-admin setup script. For a fresh deployment, create the first administrator through a trusted private/CLI process. For this existing K.O.D site, migrate the already-created `admins` table with the database backup instead.
7. Visit `http://localhost/KOD_Cafe/`.

### Production deployment

Use `.env.production.example` as a template, create a private `.env` on the host, set `APP_ENV=production` and `APP_DEBUG=false`, use HTTPS and a dedicated database user (not MySQL `root`). Follow `LAUNCH_CHECKLIST.md` and `PRODUCTION_DEPLOYMENT.md` before accepting public orders.

## Database tables used

- `admins` — admin users and password hashes.
- `menu_items` — drinks, desserts, pastries and tea.
- `contact_messages` — customer enquiries.
- `newsletter_subscribers` — email sign-ups.
- `coffee_recommendations` — coffee-colour recommendations.
- `customers` — customer accounts and current loyalty balance.
- `orders` — order, fulfilment, payment, amount and customer details.
- `order_items` — product name/price snapshots for each order.
- `order_status_history` — order progress timeline.
- `loyalty_transactions` — points earned, redeemed, refunded or adjusted.

## Configuration and security

- `.env` contains local database credentials and private setup values. Never publish it, email it or commit it to a public repository.
- `.htaccess` blocks direct web requests to `config/`, `database/` and `storage/` when Apache overrides are enabled.
- PHP database operations use PDO prepared statements. State-changing forms and API requests use CSRF tokens.
- Customer passwords use `password_hash()` and `password_verify()`.
- Customer tracking uses a random 256-bit token. Treat the tracking URL as private because anyone with it can view that order's tracking details.
- Payment status is manually recorded by an administrator. No payment card details are collected or stored by this local release.
- Online payment, email sending, automated password reset and live delivery-provider integrations are not configured.

## 3D showroom notes

The coffee cup and dessert forms are generated procedurally with Three.js geometry in the browser; separate model files are not required. Three.js and OrbitControls load from jsDelivr. The 3D view therefore requires internet access and WebGL support. If the CDN or WebGL is unavailable, standard PHP/database features continue to work, although the 3D scene may not render.

## Troubleshooting

- **Apache will not start:** check whether another application is using port 80 or 443.
- **MySQL will not start:** check whether port 3306 is occupied and inspect the MySQL error log.
- **Unknown database:** check that the database is named `kod_cafe` and `.env` contains `DB_NAME=kod_cafe`.
- **Database connection refused:** verify MySQL is running and that the host, port, username and password in `.env` match XAMPP.
- **PDO MySQL driver missing:** enable `extension=pdo_mysql` in the active `php.ini`, then restart Apache.
- **mbstring missing:** enable `extension=mbstring` in `php.ini`, then restart Apache.
- **Analytics says its database upgrades are missing:** confirm the online-ordering, order-tracking and customer-loyalty tables are present in `kod_cafe`. Phase 7 itself requires no new migration.
- **Paid revenue is zero:** open the order desk and record orders as Paid after receiving payment. Completed status alone does not mean paid.
- **3D model is blank:** check internet/CDN access, WebGL support and the browser console.
- **Images do not load:** replace demonstration image URLs through the admin menu manager.
- **Wrong local path:** update `APP_URL` in `.env` to match the folder under `htdocs`.

## Verification performed for Phase 7

- All PHP files passed `php -l` syntax checks.
- JavaScript files passed `node --check` syntax checks.
- ZIP integrity and required project files were checked.
- The Phase 7 dashboard and CSS are part of this package; live report queries and payment updates still need to be exercised against the user's own XAMPP MySQL database because the build environment used to prepare this package does not have the MySQL PDO driver.

## Phase 8 — K.O.D AI Barista

The AI Barista page is available at `http://localhost/KOD_Cafe/ai-barista.php`.

### Enable the live AI assistant (optional)

1. Open the project's `.env` file (do not edit `.env.example` for a live installation).
2. Add these lines:

   ```ini
   OPENAI_API_KEY=your_server_side_api_key
   OPENAI_MODEL=gpt-6-astra
   ```

3. Save `.env`, ensure PHP cURL is enabled in XAMPP (`php_curl.dll`), and restart Apache.
4. Reload the AI Barista page and send a test message.

The API key is read only by PHP on the server. It is never sent to the browser. Do not paste it into chat, HTML, JavaScript or screenshots. The integration uses the OpenAI Responses API; see https://developers.openai.com/api/docs/guides/text.

If `OPENAI_API_KEY` is blank, cURL is unavailable, or the API request fails, K.O.D falls back to local recommendations based on the live menu. Local guide mode does not call OpenAI and has no API usage cost. The fallback is a simple keyword-and-budget matcher, not an AI model.

### What it does

- Reads only currently available products from the existing `menu_items` table.
- Suggests drinks and desserts based on taste, strength, preferred colour, budget and dessert-pairing preferences.
- Displays matched menu items with their current database price and an Add to Bag button connected to the existing cart.
- Keeps the last few conversation turns in the current browser page only; conversations are not saved to the database.
- Applies a session-based limit of 12 requests per minute.
- Does not take orders, process payments, invent menu inventory, or make unsupported allergy/dietary claims.

No SQL migration is required for Phase 8.

---

## Phase 9 — Customer Personalization

This upgrade adds saved favourites, a customer taste profile, birthday-month rewards and personalized menu recommendations.

### Upgrade an existing K.O.D installation

1. Back up `C:\xampp\htdocs\KOD_Cafe` and your `kod_cafe` database.
2. Extract the Phase 9 ZIP and copy the contents of its `KOD_Cafe` folder into `C:\xampp\htdocs\KOD_Cafe`, replacing files when prompted.
3. Keep your existing `.env` file unchanged.
4. In phpMyAdmin, select the existing `kod_cafe` database, choose **Import**, select `database/add_customer_personalization.sql` (or the separately supplied `KOD_Cafe_Personalization_Migration.sql`) and click **Go**.
5. Import the migration once only. Do not re-import earlier database migrations or delete the existing database.
6. Visit `http://localhost/KOD_Cafe/account.php` while signed in.

### New customer features

- Save and remove menu items using the heart button on the homepage or 3D showroom.
- View saved items at `http://localhost/KOD_Cafe/favorites.php`.
- Save sweetness, coffee strength, milk preference, preferred flavour and usual budget in **My account → Your taste profile**.
- Personalized picks use the current live menu, saved preferences, favourites and completed-order history.
- Add a birthday to receive 100 bonus points once per calendar year, on the first visit to the account page during the birthday month. This is a birthday-month reward, not an email notification.
- The signed-in AI Barista uses saved taste settings to fill preference fields that the customer has not specified in the current chat.

### Notes

- The birthday gift is recorded in `birthday_rewards` and `loyalty_transactions`; a unique customer/year constraint prevents duplicate awards in the same year.
- Existing guest orders, registered accounts, points, products and administrators are not deleted or rewritten by the Phase 9 migration.
- Customer preferences and favourite products are removed automatically if the customer account is deleted. Favourites are removed if a menu item is deleted.
- The AI Barista still requires a valid server-side OpenAI API key and an available API billing arrangement for live AI replies. Local menu recommendations do not require API credits.


## Final launch preparation

See `LAUNCH_CHECKLIST.md` for the production deployment, database restore, HTTPS, security, customer smoke tests, backups and rollback steps. See `PRODUCTION_DEPLOYMENT.md` for the production environment template and current feature boundaries. Do not treat a local syntax check as proof that production hosting, MySQL, DNS, TLS or third-party integrations work.

The final release includes a showroom favourites-scope fix, PHP production error-display control via `APP_DEBUG`, session strict mode, cookie-only sessions, and additional deny rules for dotfiles and internal directories.
