<?php
// core/config.php
require_once __DIR__ . '/env.php';
date_default_timezone_set('Africa/Nairobi');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'bellamy_hotspot');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4');

// M-Pesa Daraja configuration (defaults can be overridden via environment or .env)
if (!defined('MPESA_ENV')) {
	define('MPESA_ENV', getenv('MPESA_ENV') ?: 'sandbox'); // 'sandbox' or 'production'
}

if (!defined('MPESA_CONSUMER_KEY')) {
	define('MPESA_CONSUMER_KEY', getenv('MPESA_CONSUMER_KEY') ?: 'mQPkMa5ytBRLE5HeanxPfgwJyMTYLkPACXTLGXjFeFtE5BPX');
}

if (!defined('MPESA_CONSUMER_SECRET')) {
	define('MPESA_CONSUMER_SECRET', getenv('MPESA_CONSUMER_SECRET') ?: 'xXOg9ZB73iKaAhcTqRAbAAcZrPjdVxRMytjGtUAhVp5xq90qpN7RFvugNhWpaRgl');
}

if (!defined('MPESA_SHORTCODE')) {
	define('MPESA_SHORTCODE', getenv('MPESA_SHORTCODE') ?: '174379');
}

if (!defined('MPESA_PASSKEY')) {
	define('MPESA_PASSKEY', getenv('MPESA_PASSKEY') ?: 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');
}

// Callback URL: prefer explicit MPESA_CALLBACK_URL, fall back to older MPESA_CALLBACK
if (!defined('MPESA_CALLBACK_URL')) {
	$cb = getenv('MPESA_CALLBACK_URL') ?: getenv('MPESA_CALLBACK');
	if (!$cb) $cb = 'https://yourdomain.com/api/mpesa_callback.php';
	define('MPESA_CALLBACK_URL', $cb);
}

// Backwards-compatible alias for older code that expects MPESA_CALLBACK
if (!defined('MPESA_CALLBACK')) {
	define('MPESA_CALLBACK', MPESA_CALLBACK_URL);
}

// Environment name used by some code (alias)
if (!defined('MPESA_ENVIRONMENT')) {
	define('MPESA_ENVIRONMENT', getenv('MPESA_ENVIRONMENT') ?: MPESA_ENV);
}

// Demo mode for UI/testing (no real API calls when true)
if (!defined('MPESA_DEMO_MODE')) {
    $demo = getenv('MPESA_DEMO_MODE');
    if ($demo === false || $demo === null) {
        $demo = true; // ✅ ENABLED by default for testing (cURL may not be installed)
    } else {
        // Accept '1','true','yes' as truthy
        $demo = in_array(strtolower($demo), ['1','true','yes'], true);
    }
    define('MPESA_DEMO_MODE', $demo);
}if (!defined('MPESA_SANDBOX_URL')) {
	define('MPESA_SANDBOX_URL', getenv('MPESA_SANDBOX_URL') ?: 'https://sandbox.safaricom.co.ke');
}
if (!defined('MPESA_LIVE_URL')) {
	define('MPESA_LIVE_URL', getenv('MPESA_LIVE_URL') ?: 'https://api.safaricom.co.ke');
}

// Helper to get base URL depending on environment
if (!function_exists('getMpesaBaseUrl')) {
	function getMpesaBaseUrl()
	{
		$env = defined('MPESA_ENVIRONMENT') ? MPESA_ENVIRONMENT : (defined('MPESA_ENV') ? MPESA_ENV : 'sandbox');
		return ($env === 'live' || $env === 'production') ? MPESA_LIVE_URL : MPESA_SANDBOX_URL;
	}
}

// Phone number normalization and validation
if (!function_exists('normalize_phone')) {
	function normalize_phone($phone)
	{
		// Remove all non-digits and spaces, but keep the original structure first
		$original = $phone;
		$phone = preg_replace('/\D/', '', trim($phone));
		
		// Handle 07XXXXXXXX (10 digits starting with 0)
		if (strlen($phone) === 10 && $phone[0] === '0') {
			$phone = '254' . substr($phone, 1); // Convert 07... to 2547...
		}
		// Handle 7XXXXXXXX (9 digits starting with 7)
		elseif (strlen($phone) === 9 && $phone[0] === '7') {
			$phone = '254' . $phone; // Prepend 254 to get 2547...
		}
		
		// If already starts with 254 and has exactly 12 digits, return
		if (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
			return $phone;
		}
		
		return null; // Invalid format
	}
}

if (!function_exists('is_valid_phone')) {
	function is_valid_phone($phone)
	{
		$normalized = normalize_phone($phone);
		// Valid if: starts with 254 and has exactly 12 digits total
		return $normalized && preg_match('/^254[0-9]{9}$/', $normalized);
	}
}

define('ROUTER_HOST', getenv('ROUTER_HOST') ?: '192.168.88.1');
define('ROUTER_USER', getenv('ROUTER_USER') ?: 'apiUser');
define('ROUTER_PASS', getenv('ROUTER_PASS') ?: 'StrongPass');

define('APP_BASE_URL', getenv('APP_BASE_URL') ?: 'https://yourdomain.com');

// Admin credentials (store strong password in .env)
define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASS', getenv('ADMIN_PASS') ?: 'adminpass'); 
