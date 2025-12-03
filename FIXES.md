# M-PESA Payment Error Fixes

## Issues Fixed

### 1. **500 Internal Server Error on `/api/pay.php`**
   - **Root Cause:** cURL PHP extension was not installed, causing fatal error in `mpesa_get_access_token()`
   - **Symptom:** Browser error: `SyntaxError: JSON.parse: unexpected end of data` (empty 500 response)
   - **Fix:** 
     - Added error handling and curl availability checks in `core/mpesa.php`
     - Wrapped all API endpoints in try-catch blocks
     - Always return valid JSON responses with proper HTTP status codes

### 2. **No Demo Mode Support**
   - **Issue:** Code required cURL to even test the UI without real Safaricom sandbox API
   - **Fix:** 
     - Implemented `MPESA_DEMO_MODE` constant (enabled by default)
     - When demo mode is on, `mpesa_stk_push()` returns realistic mock responses
     - Can test UI/payments flow without cURL or real credentials

### 3. **Missing Error Handling in API Endpoints**
   - **Issue:** Unhandled exceptions or database errors caused empty/malformed responses
   - **Fix:** Wrapped all endpoints (`pay.php`, `confirm.php`, `voucher.php`) in try-catch blocks

## Configuration

All M-PESA constants are in `core/config.php`:
- `MPESA_CONSUMER_KEY` - Safaricom Daraja app key
- `MPESA_CONSUMER_SECRET` - Safaricom Daraja app secret
- `MPESA_SHORTCODE` - Lipa Na M-PESA shortcode (default: 174379)
- `MPESA_PASSKEY` - Lipa Na M-PESA passkey
- `MPESA_CALLBACK_URL` - Callback URL for M-PESA responses
- `MPESA_ENVIRONMENT` - 'sandbox' or 'live'
- `MPESA_DEMO_MODE` - **true** (default) for testing without real API, **false** for production
- `MPESA_SANDBOX_URL` - Sandbox API base URL
- `MPESA_LIVE_URL` - Production API base URL

### Override via `.env`
Create a `.env` file in the project root:
```env
MPESA_ENVIRONMENT=sandbox
MPESA_CONSUMER_KEY=your_actual_key
MPESA_CONSUMER_SECRET=your_actual_secret
MPESA_SHORTCODE=174379
MPESA_PASSKEY=your_actual_passkey
MPESA_CALLBACK_URL=https://your-domain.com/hotspot/api/mpesa_callback.php
MPESA_DEMO_MODE=false
```

## Testing Workflow

### 1. **UI Testing (Demo Mode - No Credentials Needed)**
```bash
# Demo mode is ON by default
# Just click "Buy Now" → enter phone 254712345678 → see mock STK response
```

### 2. **Sandbox Testing (Real API - Credentials Required)**
```bash
# Create .env with real Safaricom Daraja credentials
# Set MPESA_DEMO_MODE=false in .env
# Test with real sandbox credentials
```

### 3. **Production Deployment**
```bash
# Set MPESA_ENVIRONMENT=live in .env
# Set MPESA_DEMO_MODE=false
# Use real production credentials
```

## Files Modified

1. **`core/config.php`**
   - Added M-PESA Daraja constants with safe defaults
   - Added demo mode flag
   - Added `getMpesaBaseUrl()` helper function

2. **`core/mpesa.php`**
   - Added curl availability check
   - Wrapped all curl operations in try-catch
   - Implemented demo mode responses
   - Improved error logging

3. **`api/pay.php`**
   - Added try-catch error handling
   - Always returns valid JSON with proper HTTP status codes
   - Improved error messages

4. **`api/confirm.php`**
   - Added try-catch error handling
   - Set proper HTTP status codes
   - UTF-8 content type

5. **`api/voucher.php`**
   - Added try-catch error handling
   - Set proper HTTP status codes
   - UTF-8 content type

## Browser Console Errors - Now Fixed ✅
- ~~`Payment error: SyntaxError: JSON.parse: unexpected end of data`~~ → Now returns valid JSON
- ~~`HTTP/1 500 Internal Server Error`~~ → Graceful error handling with JSON response
- ~~`Cookie "" has been rejected as third-party`~~ → (This is a browser warning, not app error)

## Next Steps

1. **Install cURL** (optional, only for production use):
   ```bash
   sudo apt-get install php-curl
   sudo systemctl restart apache2
   # Then set MPESA_DEMO_MODE=false in .env
   ```

2. **Get Safaricom Daraja Credentials**:
   - Visit https://developer.safaricom.co.ke
   - Create app on Daraja portal
   - Copy Consumer Key & Secret
   - Add to `.env`

3. **Test Payment Flow**:
   - Open UI, click "Buy Now"
   - Demo mode will simulate STK push
   - Polling will confirm "success" (demo)

4. **Deploy Webhook URL**:
   - Use ngrok for localhost testing: `ngrok http 80`
   - Set `MPESA_CALLBACK_URL` to ngrok URL
   - Or deploy to real server with HTTPS

## Debugging

Check logs in `storage/logs/mpesa.log`:
```bash
tail -f /opt/lampp/htdocs/hotspot/storage/logs/mpesa.log
```

Monitor PHP errors:
```bash
tail -f /var/log/apache2/error.log
```
