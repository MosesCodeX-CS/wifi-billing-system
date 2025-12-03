<?php
// cron/mpesa_reconcile.php - run via cron every 5 minutes
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/mpesa.php';

// This script reconciles pending payments older than 10 minutes.
// For production, implement transaction status queries to M-Pesa.
mpesa_reconcile_pending();
echo "Reconcile run at " . date('c') . "\n";
