<?php
/**
 * Main Entry Point - Checks if database is configured
 * File: index.php (ROOT OF YOUR PROJECT)
 */

// Prevent any output before redirect
ob_start();

// Path to database configuration file
$configFile = __DIR__ . '/api/config/db_config.php';

// Check if configuration file exists and is valid
$isConfigured = false;

if (file_exists($configFile)) {
    // Try to include and test the configuration
    try {
        // Suppress errors during include
        error_reporting(0);
        include($configFile);
        error_reporting(E_ALL);
        
        // Check if database constants are defined
        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_NAME')) {
            // Try to connect with error suppression
            $testConn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            
            if ($testConn && !$testConn->connect_error) {
                $isConfigured = true;
                $testConn->close();
            }
        }
    } catch (Exception $e) {
        $isConfigured = false;
    }
}

// Clear any output buffer
ob_end_clean();

// Redirect based on configuration status
if (!$isConfigured) {
    // Not configured - go to configuration page
    header('Location: ConfigurationPage.html');
    exit;
} else {
    // Configured - go to home page
    header('Location: Home.html');
    exit;
}
?>