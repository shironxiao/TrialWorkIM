<?php
/**
 * Check if all required fields are present
 */
function validateRequiredFields($data, $requiredFields) {
    $missing = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }
    
    return [
        'isValid' => count($missing) === 0,
        'missing' => $missing
    ];
}

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate Philippine contact number (09XXXXXXXXX)
 */
function validateContactNumber($contactNumber) {
    $cleanNumber = preg_replace('/\D/', '', $contactNumber);
    return strlen($cleanNumber) === 11 && strpos($cleanNumber, '09') === 0;
}

/**
 * Validate password strength
 */
function validatePasswordStrength($password) {
    if (strlen($password) < 6) {
        return [
            'isValid' => false,
            'message' => 'Password must be at least 6 characters long.'
        ];
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return [
            'isValid' => false,
            'message' => 'Password must contain at least one uppercase letter.'
        ];
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return [
            'isValid' => false,
            'message' => 'Password must contain at least one number.'
        ];
    }
    
    return [
        'isValid' => true,
        'message' => 'Password is strong.'
    ];
}

?>