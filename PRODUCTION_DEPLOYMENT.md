# K.O.D Production Deployment Notes

## Preserve the existing working site

For this launch, deploy the current Phase 9 site as a single release and restore the latest local database export. Avoid applying historical upgrade ZIPs one at a time. The final package includes the showroom favourites fix and production-oriented bootstrap/security settings.

## Environment configuration

Use `.env.production.example` as a template and create a real `.env` file on the server. Set:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-real-domain.example
DB_HOST=your-production-db-host
DB_PORT=3306
DB_NAME=your-production-database
DB_USER=your-dedicated-app-user
DB_PASS=your-unique-database-password
DB_CHARSET=utf8mb4
OPENAI_API_KEY=your-server-side-key-if-used
OPENAI_MODEL=gpt-6-luna
```

Do not copy the example placeholders unchanged. Do not commit or publicly upload `.env`.

The application bootstrap now honours `APP_DEBUG`, disables displayed PHP errors when it is false, enables PHP error logging and enables PHP session strict mode and cookie-only sessions. HTTPS is required for the secure session-cookie flag to be set; terminate TLS at the web server and make sure PHP sees the request as HTTPS.

## Database upgrade logic

The current `database/database.sql` is a consolidated schema for a fresh install and already includes the core menu, customer, order, loyalty and Phase 9 personalization tables. It does not include the latest order tracking status history and estimated-ready-time alteration, so a fresh database needs `database/add_order_tracking.sql` after the base schema.

For the existing K.O.D database, export the working `kod_cafe` database in full and import that backup on the production host. Do not import the consolidated base schema or old upgrade migrations over the restored database.

## AI Barista model

The production example uses `gpt-6-luna`, a cost-efficient current model for focused, high-volume text tasks. The API key stays in the server-side `.env` file. Live AI replies require an enabled API project and available API billing; local recommendations remain available as fallback. See the official [OpenAI API model catalogue](https://developers.openai.com/api/docs/models) for current model availability and pricing.

## Current feature boundaries

The release has a café menu, 3D product showroom, cart/checkout, account and loyalty features, order tracking, analytics and AI Barista integration. It does not currently process online payments, send transactional email, run password-reset emails, connect a delivery fleet/GPS service, or calculate business profit. Those need separate integrations and acceptance tests before being marketed as available.
