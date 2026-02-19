<?php
/**
 * SIMPLE TEST - Save as: api/auth/simple_test.php
 * Access: http://localhost/Web%20Dev/TABEYAWEB/IT100webDev-main/Tabeya/api/auth/simple_test.php
 */

// Show ALL errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== AUTHENTICATION TEST ===<br><br>";

// Step 1: Test basic PHP
echo "1. PHP is working ✓<br>";

// Step 2: Test session start
echo "2. Testing session...<br>";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo "   Session started ✓<br>";
} catch (Exception $e) {
    echo "   Session ERROR: " . $e->getMessage() . "<br>";
}

// Step 3: Test database connection
echo "3. Testing database...<br>";
$conn = new mysqli('localhost', 'root', '', 'tabeya_system');
if ($conn->connect_error) {
    die("   Database ERROR: " . $conn->connect_error . "<br>");
}
echo "   Database connected ✓<br>";

// Step 4: Check if customers table exists
echo "4. Checking customers table...<br>";
$result = $conn->query("SHOW TABLES LIKE 'customers'");
if ($result && $result->num_rows > 0) {
    echo "   Table exists ✓<br>";
} else {
    die("   Table DOES NOT EXIST ✗<br>");
}

// Step 5: Test INSERT
echo "5. Testing registration...<br>";
$testEmail = 'test_' . time() . '@example.com';
$testPassword = password_hash('Test123', PASSWORD_BCRYPT);

$sql = "INSERT INTO customers 
       (FirstName, LastName, Email, PasswordHash, ContactNumber, 
        CustomerType, FeedbackCount, TotalOrdersCount, ReservationCount, 
        AccountStatus, SatisfactionRating, CreatedDate) 
       VALUES (?, ?, ?, ?, ?, 'Online', 0, 0, 0, 'Active', 0.00, NOW())";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("   Prepare ERROR: " . $conn->error . "<br>");
}

$firstName = 'Test';
$lastName = 'User';
$contactNumber = '09123456789';

$stmt->bind_param("sssss", $firstName, $lastName, $testEmail, $testPassword, $contactNumber);

if (!$stmt->execute()) {
    die("   Execute ERROR: " . $stmt->error . "<br>");
}

$customerId = $stmt->insert_id;
$stmt->close();

echo "   Registration successful ✓<br>";
echo "   Customer ID: $customerId<br>";
echo "   Email: $testEmail<br>";

// Step 6: Test JSON response
echo "<br>6. Testing JSON response...<br>";
header('Content-Type: application/json');
$jsonResponse = json_encode([
    'success' => true,
    'customerId' => $customerId,
    'message' => 'All tests passed!'
]);
echo "   JSON: $jsonResponse<br>";

echo "<br>=== ALL TESTS PASSED ===<br>";
echo "<br>Now try the actual registration form!";

$conn->close();
?>