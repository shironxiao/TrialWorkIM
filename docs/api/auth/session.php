<?php
/**
 * Session Management
 * Handles user session verification and security
 */

// ✅ START SESSION AT THE VERY TOP

session_start();

// Set response headers for JSON
function setJsonHeaders() {
    header('Content-Type: application/json; charset=utf-8');
}

/**
 * Check if user is logged in
 */
function isUserLoggedIn() {
    return isset($_SESSION['customer_id']) && 
           isset($_SESSION['customer_email']) && 
           isset($_SESSION['login_time']);
}

/**
 * Get current logged-in user ID
 */
function getCurrentUserId() {
    return isUserLoggedIn() ? $_SESSION['customer_id'] : null;
}

/**
 * Get current logged-in user email
 */
function getCurrentUserEmail() {
    return isUserLoggedIn() ? $_SESSION['customer_email'] : null;
}

/**
 * Set user session after login
 */
function setUserSession($customerId, $email, $firstName, $lastName) {
    $_SESSION['customer_id'] = $customerId;
    $_SESSION['customer_email'] = $email;
    $_SESSION['customer_name'] = $firstName . ' ' . $lastName;
    $_SESSION['customer_firstname'] = $firstName;
    $_SESSION['customer_lastname'] = $lastName;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['timeout'] = 30 * 60;  // 30 minutes
}

/**
 * Destroy user session (logout)
 */
function destroyUserSession() {
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Check session timeout
 */
function checkSessionTimeout() {
    if (isUserLoggedIn()) {
        $inactiveTime = time() - $_SESSION['last_activity'];
        $timeout = $_SESSION['timeout'] ?? (30 * 60);
        
        if ($inactiveTime > $timeout) {
            destroyUserSession();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    return false;
}

/**
 * Require user to be logged in
 */
function requireLogin() {
    if (!isUserLoggedIn()) {
        http_response_code(401);
        setJsonHeaders();
        echo json_encode([
            'success' => false,
            'message' => 'You must be logged in to access this resource.'
        ]);
        exit;
    }
    
    if (!checkSessionTimeout()) {
        http_response_code(401);
        setJsonHeaders();
        echo json_encode([
            'success' => false,
            'message' => 'Your session has expired. Please log in again.'
        ]);
        exit;
    }
}

/**
 * Verify user can access specific resource
 */
function verifyUserAccess($resourceOwnerId) {
    requireLogin();
    
    $currentUserId = getCurrentUserId();
    
    if ((int)$resourceOwnerId !== (int)$currentUserId) {
        http_response_code(403);
        setJsonHeaders();
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. This resource belongs to another user.'
        ]);
        exit;
    }
}

?>