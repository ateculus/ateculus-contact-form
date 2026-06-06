# Ateculus Contact Form

A flexible WordPress contact form plugin with Cloudflare Turnstile spam protection, SMTP support, and multi-channel notifications.

## Features

- **AJAX submission** — no page reload, inline success/error messages
- **Cloudflare Turnstile** — privacy-respecting CAPTCHA with server-side token verification
- **SMTP support** — works with Gmail, Mailgun, SendGrid, or any SMTP server via PHPMailer
- **Multi-channel notifications** — Email, Discord webhook, and Slack webhook (all three can run simultaneously)
- **Drag-and-drop field builder** — add, reorder, and remove fields from the WordPress admin
- **Phone auto-formatting** — formats to `(555) 867-5309` as the user types; blocks non-numeric input
- **Server-side validation** — all fields validated on the server regardless of client input
- **Branded email template** — clean HTML emails with site name, submitted fields, and timestamp

## Requirements

- WordPress 6.0+ (tested up to 7.0)
- PHP 8.0+

## Installation

1. Download the latest ZIP from the [Releases](../../releases) page
2. Go to **Plugins → Add New → Upload Plugin** in your WordPress admin
3. Upload the ZIP and click **Activate Plugin**

## Usage

Place the shortcode on any page, post, or widget area:

```
[ateculus_contact_form]
```

### Shortcode attributes

| Attribute | Default | Description |
|-----------|---------|-------------|
| `show_title` | `true` | Show/hide the form heading |
| `show_phone` | `false` | Show/hide the phone number field |
| `show_service` | `false` | Show/hide the service/enquiry type field |

```
[ateculus_contact_form show_title="false" show_phone="true" show_service="true"]
```

## Configuration

Go to **Settings → Contact Form** in your WordPress admin to configure:

- Email provider (PHP mail or SMTP)
- SMTP credentials
- Sender name and address
- Notification recipients
- Discord and Slack webhook URLs
- Cloudflare Turnstile site/secret keys
- Success and error messages

The **Fields** tab lets you build a custom field set with drag-to-reorder support.

## License

Free for personal use. Commercial use requires written authorization — see [LICENSE](LICENSE) for full terms.

## Author

Built by [Ateculus](https://ateculus.com)
