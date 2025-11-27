## 🎉 پروژه نسخه پرو، اوپن‌سورس شد!
نسخه‌ی **Pro** این پروژه اکنون به‌صورت **اوپن‌سورس** در دسترس است.  
از مشارکت، پیشنهادها و همکاری شما برای توسعه‌ی بهتر پروژه استقبال می‌کنیم 🤝

---

### 💖 حمایت از پروژه
اگر این پروژه برای شما مفید بوده،  
می‌توانید از طریق لینک زیر کمک کنید:

👉 [حمایت از پروژه در NowPayments](https://nowpayments.io/donation/permiumbotmirza)

---

⭐ لطفاً پروژه را **Star** کنید تا دیگران هم آن را پیدا کنند!

---

## 🎉 The Pro Version of the Project is Now Open Source!
The **Pro** version of this project is now officially **open-sourced**!  
We welcome your contributions, suggestions, and collaboration to help it grow 🤝

---

### 💖 Support the Project
If you find this project useful,  
you can support it through the link below:

👉 [Support the Project on NowPayments](https://nowpayments.io/donation/permiumbotmirza)

---

⭐ Don’t forget to **Star** the repository to help others discover it!

---

## 📋 Supported Panel Types

This project supports the following VPN panel types:

| Panel Type | Description |
|------------|-------------|
| `marzban` | Marzban panel |
| `marzneshin` | Marzneshin panel |
| `x-ui_single` | X-UI Single panel |
| `alireza_single` | Alireza Single panel |
| `hiddify` | Hiddify panel |
| `WGDashboard` | WireGuard Dashboard |
| `s_ui` | S-UI panel |
| `ibsng` | IBSng panel |
| `mikrotik` | Mikrotik router |
| `falco` | Falco panel (NEW) |

---

## 🦅 Falco Panel Integration

The Falco panel type (`falco`) allows resellers to manage VPN configs through the Falco API.

### Configuration

When adding a Falco panel, use the following settings:
- **Panel Type**: `falco`
- **URL Panel**: Your Falco API base URL (e.g., `https://falco.all-engines.com`)
- **Secret Code**: Your Falco API key
- **Inbound ID**: The default panel_id for creating configs

### API Endpoints Used

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/panels` | List accessible panels |
| GET | `/api/v1/configs` | List configs (paginated) |
| GET | `/api/v1/configs/{name}` | Get config details |
| POST | `/api/v1/configs` | Create new config |
| PUT | `/api/v1/configs/{name}` | Update config |
| DELETE | `/api/v1/configs/{name}` | Delete config |

### Creating a Config via cURL

```bash
curl -X POST https://falco.all-engines.com/api/v1/configs \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "panel_id": 1,
    "traffic_limit_gb": 10,
    "expires_days": 30,
    "comment": "کانفیگ تست"
  }'
```

### Required Fields for Create Config

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `panel_id` | integer | Yes | Panel ID to create config on |
| `traffic_limit_gb` | integer | Yes | Traffic limit in gigabytes |
| `expires_days` | integer | Yes | Number of days until expiration |
| `comment` | string | No | Optional comment/note |

### Response Fields

The API returns configs with these fields:

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Config name/username |
| `panel_id` | integer | Associated panel ID |
| `panel_type` | string | Type of panel |
| `traffic_limit_gb` | integer | Traffic limit in GB |
| `traffic_limit_bytes` | integer | Traffic limit in bytes |
| `usage_bytes` | integer | Current usage in bytes |
| `expires_at` | string | Expiration date (ISO8601) |
| `status` | string | Config status (active/disabled/expired/limited) |
| `subscription_url` | string | Subscription URL for the config |

### PHP Usage Example

```php
<?php
require_once 'falco.php';

// List all panels
$panels = listFalcoPanels('my_falco_panel');

// Create a new config
$result = createFalcoConfig(
    'my_falco_panel',  // panel name in database
    1,                  // panel_id
    10,                 // traffic_limit_gb
    30,                 // expires_days
    'New config'        // comment (optional)
);

// Get config details
$config = getFalcoConfig('my_falco_panel', 'user_abc123');

// Update config
$updated = updateFalcoConfig('my_falco_panel', 'user_abc123', [
    'traffic_limit_gb' => 20,
    'status' => 'active'
]);

// Delete config
$deleted = deleteFalcoConfig('my_falco_panel', 'user_abc123');
```

---

## 🧪 Running Tests

To run the Falco panel tests:

```bash
php tests/test_falco.php
```

To run the API Failure Logger tests:

```bash
php tests/test_api_failure_logger.php
```

---

## 🔔 API Failure Logging and Admin Alerts

This project includes a centralized logging system for all external API failures. When any panel API request fails (non-2xx responses or network errors), the system:

1. **Logs detailed error information** to the Apache error log (via `error_log()`)
2. **Sends admin alerts** via Telegram to notify administrators immediately

### Log Location

API failure logs appear in your Apache error log file. Common locations:
- Ubuntu/Debian: `/var/log/apache2/error.log`
- CentOS/RHEL: `/var/log/httpd/error_log`
- Custom: Check `ErrorLog` directive in your Apache configuration

Logs are prefixed with `[API_FAILURE]` and contain JSON-formatted details:
```json
{
  "type": "API_FAILURE",
  "timestamp": "2024-01-15 10:30:45",
  "method": "POST",
  "url": "https://panel.example.com/api/users",
  "http_status": 500,
  "panel_name": "my_panel",
  "user_id": 12345
}
```

### Admin Alert Configuration

Admin alerts are sent via Telegram to:
1. The main admin number configured in `config.php` (`$adminnumber`)
2. The Channel_Report configured in database settings
3. Error reports go to the `errorreport` topic if configured in `topicid` table

### Throttling

To prevent alert spam during outages, alerts for the same endpoint are throttled to one per minute by default.

### Sensitive Data Protection

The logger automatically sanitizes sensitive fields before logging:
- Passwords (`password`, `passwd`, `pass`)
- Tokens (`token`, `access_token`, `refresh_token`, `bearer`)
- API keys (`api_key`, `apikey`, `secret`, `secret_code`)
- Authorization headers

### User-Facing Error Messages

When API failures occur, users receive safe, generic error messages:

**English:**
```
There was an error communicating with the external panel. The administrators have been notified.
```

**Persian:**
```
خطایی در ارتباط با پنل خارجی رخ داد. مدیران مطلع شده‌اند.
```

### Usage in Custom Code

You can use the `ApiFailureLogger` directly in your code:

```php
<?php
require_once 'ApiFailureLogger.php';

// Log an API failure
ApiFailureLogger::log([
    'method' => 'POST',
    'url' => 'https://panel.example.com/api/users',
    'request_body' => ['username' => 'test'],
    'http_status' => 500,
    'response_body' => '{"error": "Internal Server Error"}',
    'exception' => $e,  // Optional Throwable
    'panel_name' => 'my_panel',
    'user_id' => 12345,
    'context' => 'Creating user',
]);

// Get a safe error message for users
$message = ApiFailureLogger::getGenericErrorMessage('marzban');
// Or in Persian:
$message = ApiFailureLogger::getGenericErrorMessagePersian('مرزبان');
```

### Disabling Admin Alerts

To disable Telegram alerts (e.g., during maintenance):

```php
ApiFailureLogger::log($params, ['enable_admin_alerts' => false]);
```

### Adjusting Throttle Time

To change the minimum time between alerts for the same endpoint:

```php
ApiFailureLogger::log($params, ['alert_throttle_seconds' => 300]); // 5 minutes
```
