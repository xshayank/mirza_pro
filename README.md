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
