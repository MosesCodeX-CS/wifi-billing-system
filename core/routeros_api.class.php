<?php
// core/routeros_api.class.php - attempts to use composer-installed RouterOS API client.
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// If evilfreelancer/routeros-api-php is installed, the class RouterosAPI will be available.
// Provide fallback stub functions for graceful failure.

if (!class_exists('RouterosAPI')) {
    class RouterosAPI {
        public function connect($host, $user, $pass, $port = 8728, $ssl = false, $timeout = 10) {
            return false;
        }
        public function write() { return false; }
        public function read() { return false; }
        public function disconnect() { return true; }
    }
}

function create_hotspot_user_on_router($username, $password, $profile='24hrs') {
    // If the real RouterosAPI class exists and behaves as expected, use it.
    if (class_exists('RouterosAPI')) {
        try {
            $API = new RouterosAPI();
            // Many RouterOS libraries accept connect($host,$user,$pass)
            if (method_exists($API,'connect')) {
                $connected = $API->connect(ROUTER_HOST, ROUTER_USER, ROUTER_PASS);
                if ($connected) {
                    $API->write('/ip/hotspot/user/add', false);
                    $API->write('=name=' . $username, false);
                    $API->write('=password=' . $password, false);
                    $API->write('=profile=' . $profile, true);
                    $API->disconnect();
                    return true;
                }
            }
        } catch (Exception $e) {
            error_log('RouterOS API error: ' . $e->getMessage());
        }
    }
    error_log('RouterOS API not available or failed to connect.');
    return false;
}
