# Google Sign-In setup for Karossy

Karossy uses Google's server-side OAuth authorization-code flow with state and PKCE. The Google client secret remains on the Laravel server and is never rendered in the browser.

## 1. Create the Google OAuth client

In Google Cloud / Google Auth Platform, create an OAuth client of type **Web application**.

Add the exact callback URLs you will use under **Authorized redirect URIs**.

Examples:

- Local: `http://127.0.0.1:8000/auth/google/callback`
- Production: `https://karossytravels.com/auth/google/callback`

Google requires production redirect URIs to use HTTPS, and the configured URI must match exactly.

## 2. Add the server environment variables

Add these values to the local and production `.env` files separately:

```env
GOOGLE_CLIENT_ID=your-google-web-client-id
GOOGLE_CLIENT_SECRET=your-google-web-client-secret
GOOGLE_REDIRECT_URI=https://karossytravels.com/auth/google/callback
GOOGLE_AUTH_TIMEOUT=15
```

For local development, set `GOOGLE_REDIRECT_URI` to the exact local URL registered in Google Cloud.

Do not commit the client secret to Git.

## 3. Apply configuration and database changes

The patch includes a migration for `social_accounts`. After deployment run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

The project watcher normally handles the migration and Laravel cache steps automatically when it merges this patch.

## How account matching works

- Returning Google users are matched by Google's stable `sub` identifier.
- If the Google identity is new but its verified email already belongs to a Karossy user, Google is linked to that Karossy account.
- If no Karossy account exists, a normal B2C user and customer profile are created.
- Karossy does not store Google's access token because Sign-In is the only Google capability being used.
