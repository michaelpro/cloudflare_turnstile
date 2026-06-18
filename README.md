# cloudflare_turnstile
A Roundcube plugin to add CloudFlare Turnstile and optionally an SSL.COM site seal to the login page
# Cloudflare Turnstile + SSL.COM Seal

A clean, upgrade-safe plugin that adds **Cloudflare Turnstile** protection to the Roundcube login page and optionally displays an SSL.COM site seal.

## Features

- Cloudflare Turnstile CAPTCHA on login page
- Light and dark theme support
- IP bypass list
- Optional SSL.COM site seal (fully configurable)
- Improved error handling
- No core modifications required

## Configuration

Copy the config template:

```bash
cp plugins/cloudflare_turnstile/config.inc.php.dist config/cloudflare_turnstile.inc.php
Required Settings
PHP$config['turnstile_sitekey'] = 'your_site_key';
$config['turnstile_secret']  = 'your_secret_key';
Optional Settings
PHP$config['turnstile_theme']      = 'light';        // 'light' or 'dark'
$config['turnstile_bypass_ips'] = ['192.168.1.10'];

$config['sslcom_seal_enabled'] = true;
$config['sslcom_seal_team']    = 'your_team_id';
$config['sslcom_seal_id']      = 'your_seal_id';
Notes

The SSL.COM seal is disabled by default.
If Turnstile validation fails, the user sees the normal "Login failed" message.
The plugin only affects the login page.

## SSL.COM Site Seal (Optional)

This plugin can optionally display an SSL.COM site seal below the login form.

To enable it:

1. Set `sslcom_seal_enabled = true` in the config.
2. Fill in your own `sslcom_seal_team` and `sslcom_seal_id` values from your SSL.COM account.

**Note:** The seal is disabled by default. Do **not** use the example values — they belong to someone else.
