# General Settings

Open **Settings → General Settings** in the admin. Access requires the existing
`settings.manage` permission.

The form stores the site name, contact email, phone numbers (one per line),
WhatsApp number and social profile URLs. In this release, only social links are
connected to the public website; existing public names and contact information
remain unchanged.

Paste the full `https://` profile URL for each social platform. Saved links appear
in the footer on every public page. Leave a link blank to hide its icon. No
placeholder profiles are supplied, and changes take effect without a cache clear.

After deploying, apply the additive settings migration:

```sh
php artisan migrate --path=database/migrations/2026_10_15_100000_create_general_settings_table.php --force
```

The homepage remains available before the migration is applied, but settings
cannot be saved until the table exists. Admin saves are recorded in the audit log.
