# Hotspot Payment System - Setup Instructions

## Quick Start (Demo Mode)

The system is **pre-configured in demo mode** — you can test the UI and payment flow immediately without any setup!

### Test the Payment UI
1. Open the hotspot page in your browser
2. Click "Buy Now" on any plan
3. Enter phone number: `254712345678`
4. The system will simulate an STK push and payment
5. See the voucher code in the UI

**No credentials, no cURL, no setup required!** ✅

---

## Production Setup (Real M-PESA Payments)

### Step 1: Get Safaricom Daraja Credentials
1. Visit https://developer.safaricom.co.ke
2. Create a developer account
3. Create a new app (type: Ecommerce)
4. Copy:
   - **Consumer Key**
   - **Consumer Secret**
   - **Shortcode** (for Lipa Na M-PESA)
   - **Passkey** (for Lipa Na M-PESA)

### Step 2: Install PHP cURL (if not already installed)

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install php-curl
sudo systemctl restart apache2
```

**Check if installed:**
```bash
php -m | grep curl
```

### Step 3: Create `.env` File

Create `/opt/lampp/htdocs/hotspot/.env`:
```env
# M-PESA Daraja Configuration
MPESA_ENVIRONMENT=sandbox
MPESA_CONSUMER_KEY=your_consumer_key_here
MPESA_CONSUMER_SECRET=your_consumer_secret_here
MPESA_SHORTCODE=174379
MPESA_PASSKEY=your_passkey_here
MPESA_CALLBACK_URL=https://yourdomain.com/hotspot/api/mpesa_callback.php
MPESA_DEMO_MODE=false
```

### Step 4: Set Up Callback URL

M-PESA needs to reach your callback endpoint to notify you of payment status.

**For Local Testing (with ngrok):**
```bash
# Install ngrok if you don't have it
wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.zip
unzip ngrok-v3-stable-linux-amd64.zip
sudo mv ngrok /usr/local/bin/

# Start ngrok
ngrok http 80

# Copy the HTTPS URL (e.g., https://abc123.ngrok.io)
# Set in .env:
# MPESA_CALLBACK_URL=https://abc123.ngrok.io/hotspot/api/mpesa_callback.php
```

**For Production (Real Server):**
```
MPESA_CALLBACK_URL=https://yourdomain.com/hotspot/api/mpesa_callback.php
```

### Step 5: Test Sandbox Integration

```bash
curl -X POST http://localhost/hotspot/api/pay.php \
  -d 'phone=254712345678&plan=2hrs'
```

Expected response:
```json
{
  "status": "pending",
  "checkoutRequestID": "ws1234567890"
}
```

### Step 6: Go Live

When ready for production:
1. Switch credentials to production keys from Daraja
2. Change `.env`:
   ```env
   MPESA_ENVIRONMENT=live
   MPESA_CALLBACK_URL=https://yourdomain.com/hotspot/api/mpesa_callback.php
   MPESA_DEMO_MODE=false
   ```
3. Test with real M-PESA on a small amount
4. Monitor logs in `storage/logs/mpesa.log`

---

## Environment Variables Reference

| Variable | Default | Description |
|----------|---------|-------------|
| `MPESA_ENVIRONMENT` | `sandbox` | Use `sandbox` for testing, `live` for production |
| `MPESA_CONSUMER_KEY` | (from Daraja) | Your app's consumer key |
| `MPESA_CONSUMER_SECRET` | (from Daraja) | Your app's consumer secret |
| `MPESA_SHORTCODE` | `174379` | Lipa Na M-PESA shortcode |
| `MPESA_PASSKEY` | (from Daraja) | Lipa Na M-PESA passkey |
| `MPESA_CALLBACK_URL` | - | Where M-PESA sends payment notifications |
| `MPESA_DEMO_MODE` | `true` | Set to `false` for real payments |
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_NAME` | `bellamy_hotspot` | Database name |
| `DB_USER` | `db_user` | Database user |
| `DB_PASS` | `db_pass` | Database password |
| `ROUTER_HOST` | `192.168.88.1` | MikroTik router IP |
| `ROUTER_USER` | `apiUser` | Router API user |
| `ROUTER_PASS` | `StrongPass` | Router API password |

---

## Troubleshooting

### Payment API returns 500 error
**Check:** PHP error log for exceptions
```bash
tail -f /var/log/apache2/error.log
```
**Or:** Check app logs
```bash
tail -f storage/logs/mpesa.log
```

### Browser shows "JSON.parse: unexpected end of data"
**Fix:** This means the API returned empty content. Check:
- PHP syntax errors: `php -l api/pay.php`
- Database connection: Test in `core/db.php`
- cURL installed: `php -m | grep curl`

### STK push fails with "No access token"
**Causes:**
- cURL not installed (use demo mode or install cURL)
- Invalid credentials in `.env`
- Network blocked to `sandbox.safaricom.co.ke`

**Test credentials:**
```bash
php -r "
require_once 'core/config.php';
echo 'Key: ' . MPESA_CONSUMER_KEY . PHP_EOL;
echo 'Secret: ' . MPESA_CONSUMER_SECRET . PHP_EOL;
"
```

### M-PESA callback not arriving
**Check:**
- `MPESA_CALLBACK_URL` is publicly accessible (test with curl)
- URL is HTTPS (required by M-PESA)
- Firewall allows inbound POST requests
- ngrok tunnel is still active (if using local testing)

**Test callback endpoint:**
```bash
curl -X POST -H "Content-Type: application/json" \
  -d '{"Body":{"stkCallback":{"ResultCode":0}}}' \
  https://yourdomain.com/hotspot/api/mpesa_callback.php
```

---

## File Structure

```
hotspot/
├── .env                    ← Create this with your config
├── api/
│   ├── pay.php            ← STK push initiation
│   ├── confirm.php        ← Payment polling
│   └── voucher.php        ← Voucher activation
├── core/
│   ├── config.php         ← All constants and helpers
│   ├── db.php             ← Database connection
│   ├── mpesa.php          ← M-PESA API functions
│   ├── mikrotik.php       ← Router integration
│   └── env.php            ← .env file loader
├── public/
│   ├── index.html         ← Payment UI
│   ├── script.js          ← Payment flow logic
│   ├── styles.css         ← UI styling
│   └── mpesa-callback.php ← M-PESA callback handler
└── storage/
    └── logs/
        └── mpesa.log      ← Payment API logs
```

---

## API Endpoints

### POST /hotspot/api/pay.php
Initiates STK push

**Request:**
```
phone=254712345678
plan=2hrs
```

**Response (Success):**
```json
{
  "status": "pending",
  "checkoutRequestID": "ws1234567890"
}
```

**Response (Error):**
```json
{
  "status": "error",
  "msg": "Error description"
}
```

### POST /hotspot/api/confirm.php
Polls for payment confirmation

**Request:**
```
checkoutRequestID=ws1234567890
```

**Response:**
```json
{
  "status": "success",
  "voucher": "BELL12345"
}
```

### POST /hotspot/api/voucher.php
Activates a voucher on the hotspot

**Request:**
```
voucher=BELL12345
```

**Response:**
```json
{
  "status": "ok",
  "username": "BELL12345"
}
```

---

## Database Schema

Key tables created during setup:

```sql
-- Payments table
CREATE TABLE payments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  phone VARCHAR(20),
  amount INT,
  status ENUM('PENDING','SUCCESS','FAILED'),
  checkout_request_id VARCHAR(255),
  receipt_number VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vouchers table
CREATE TABLE vouchers (
  id INT PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(20) UNIQUE,
  plan VARCHAR(20),
  used TINYINT DEFAULT 0,
  created_by_payment_id INT,
  expires_at TIMESTAMP
);
```

---

## Support

For issues or questions:
1. Check `storage/logs/mpesa.log` for API errors
2. Test with demo mode first (no credentials)
3. Verify `.env` file syntax
4. Check firewall/network connectivity
5. Review Safaricom Daraja API documentation

Happy payments! 🎉
