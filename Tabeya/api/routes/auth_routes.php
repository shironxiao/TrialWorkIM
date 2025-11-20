<?php
/**
 * API Routes Configuration
 * Centralized routing for authentication endpoints
 */

// Base URL for API
define('API_URL', 'http://localhost/Tabeya/api/');

/**
 * Get authentication endpoint URL
 */
function getAuthEndpoint($action) {
    return API_URL . 'auth/authentication.php?action=' . $action;
}

/**
 * Example usage in JavaScript:
 * 
 * const registerUrl = '<?php echo getAuthEndpoint('register'); ?>';
 * const loginUrl = '<?php echo getAuthEndpoint('login'); ?>';
 * 
 * fetch(registerUrl, {
 *     method: 'POST',
 *     headers: { 'Content-Type': 'application/json' },
 *     body: JSON.stringify({
 *         firstName: 'Juan',
 *         lastName: 'Dela Cruz',
 *         email: 'juan@example.com',
 *         contactNumber: '09123456789',
 *         password: 'Password123'
 *     })
 * });
 */

?>