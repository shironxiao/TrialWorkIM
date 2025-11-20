<?php
/**
 * ULTRA SIMPLE TEST
 * Save as: api/auth/test_auth.php
 * Test from JavaScript with: fetch('api/auth/test_auth.php', {method: 'POST'})
 */

// Prevent ANY output before JSON
ob_start();

// Start session
session_start();

// Set JSON header
header('Content-Type: application/json');

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Connect to database
$conn = new mysqli('localhost', 'root', '', 'tabeya_system');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $conn->connect_error]);
    exit;
}

// Simple registration test
if (isset($data['firstName'])) {
    $firstName = $data['firstName'];
    $lastName = $data['lastName'];
    $email = $data['email'];
    $password = password_hash($data['password'], PASSWORD_BCRYPT);
    $contactNumber = $data['contactNumber'];
    
    $sql = "INSERT INTO customers 
           (FirstName, LastName, Email, PasswordHash, ContactNumber, 
            CustomerType, AccountStatus) 
           VALUES (?, ?, ?, ?, ?, 'Online', 'Active')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $firstName, $lastName, $email, $password, $contactNumber);
    
    if ($stmt->execute()) {
        $customerId = $stmt->insert_id;
        
        // Set session
        $_SESSION['customer_id'] = $customerId;
        $_SESSION['customer_email'] = $email;
        
        echo json_encode([
            'success' => true,
            'customerId' => $customerId,
            'message' => 'Registration successful',
            'customer' => [
                'FirstName' => $firstName,
                'LastName' => $lastName,
                'Email' => $email,
                'CustomerID' => $customerId
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $stmt->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No data received']);
}

$conn->close();
ob_end_flush();
?>