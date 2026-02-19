<?php
/**
 * TEST SCRIPT - Debug Registration
 * Save as: api/auth/test_registration.php
 * Access via: http://localhost/Web%20Dev/TABEYAWEB/IT100webDev-main/Tabeya/api/auth/test_registration.php
 */

// Show ALL errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Registration System</h2>";

// Test 1: Check if files exist
echo "<h3>1. File Check</h3>";
$files = [
    'session.php' => __DIR__ . '/session.php',
    'db_config.php' => __DIR__ . '/../config/db_config.php',
    'validation.php' => __DIR__ . '/../functions/validation.php',
    'security.php' => __DIR__ . '/../functions/security.php',
    'Customer.php' => __DIR__ . '/../functions/Customer.php'
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✓ $name exists<br>";
    } else {
        echo "✗ $name MISSING at $path<br>";
    }
}

// Test 2: Include files
echo "<h3>2. Include Files</h3>";
try {
    require_once(__DIR__ . '/session.php');
    echo "✓ session.php loaded<br>";
    
    require_once(__DIR__ . '/../config/db_config.php');
    echo "✓ db_config.php loaded<br>";
    
    require_once(__DIR__ . '/../functions/validation.php');
    echo "✓ validation.php loaded<br>";
    
    require_once(__DIR__ . '/../functions/security.php');
    echo "✓ security.php loaded<br>";
    
    require_once(__DIR__ . '/../functions/Customer.php');
    echo "✓ Customer.php loaded<br>";
} catch (Exception $e) {
    echo "✗ Error loading files: " . $e->getMessage() . "<br>";
    exit;
}

// Test 3: Database connection
echo "<h3>3. Database Connection</h3>";
if (isset($conn) && $conn->ping()) {
    echo "✓ Database connected: " . DB_NAME . "<br>";
} else {
    echo "✗ Database connection failed<br>";
    exit;
}

// Test 4: Check if customers table exists
echo "<h3>4. Check Tables</h3>";
$result = $conn->query("SHOW TABLES LIKE 'customers'");
if ($result && $result->num_rows > 0) {
    echo "✓ customers table exists<br>";
    
    // Show table structure
    $structure = $conn->query("DESCRIBE customers");
    echo "<pre>";
    while ($row = $structure->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    echo "</pre>";
} else {
    echo "✗ customers table does NOT exist<br>";
    echo "<strong>Run this SQL to create it:</strong><br>";
    echo "<textarea style='width:100%; height:200px;'>";
    echo "CREATE TABLE customers (
    CustomerID INT AUTO_INCREMENT PRIMARY KEY,
    FirstName VARCHAR(100) NOT NULL,
    LastName VARCHAR(100) NOT NULL,
    Email VARCHAR(150) UNIQUE NOT NULL,
    PasswordHash VARCHAR(255) NOT NULL,
    ContactNumber VARCHAR(20),
    CustomerType VARCHAR(50) DEFAULT 'Online',
    CreatedDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    LastLoginDate DATETIME,
    AccountStatus VARCHAR(20) DEFAULT 'Active'
);";
    echo "</textarea>";
    exit;
}

// Test 5: Test Customer class
echo "<h3>5. Test Customer Class</h3>";
try {
    $customer = new Customer($conn);
    echo "✓ Customer class instantiated<br>";
    
    // Test email check
    $testEmail = 'test_' . time() . '@example.com';
    $exists = $customer->emailExists($testEmail);
    echo "✓ emailExists() method works (returned: " . ($exists ? 'true' : 'false') . ")<br>";
} catch (Exception $e) {
    echo "✗ Customer class error: " . $e->getMessage() . "<br>";
    exit;
}

// Test 6: Test validation functions
echo "<h3>6. Test Validation Functions</h3>";
try {
    $emailValid = validateEmail('test@example.com');
    echo "✓ validateEmail() works: " . ($emailValid ? 'true' : 'false') . "<br>";
    
    $contactValid = validateContactNumber('09123456789');
    echo "✓ validateContactNumber() works: " . ($contactValid ? 'true' : 'false') . "<br>";
    
    $passwordCheck = validatePasswordStrength('Test123');
    echo "✓ validatePasswordStrength() works: " . ($passwordCheck['isValid'] ? 'valid' : 'invalid') . "<br>";
} catch (Exception $e) {
    echo "✗ Validation error: " . $e->getMessage() . "<br>";
}

// Test 7: Test actual registration
echo "<h3>7. Test Registration</h3>";
try {
    $testData = [
        'firstName' => 'Test',
        'lastName' => 'User',
        'email' => 'testuser_' . time() . '@example.com',
        'contactNumber' => '09123456789',
        'password' => 'Test123'
    ];
    
    echo "Attempting registration with:<br>";
    echo "Email: " . $testData['email'] . "<br>";
    
    $passwordHash = hashPassword($testData['password']);
    $result = $customer->register(
        $testData['firstName'],
        $testData['lastName'],
        $testData['email'],
        $testData['contactNumber'],
        $passwordHash
    );
    
    if ($result['success']) {
        echo "✓ <strong>Registration SUCCESSFUL!</strong><br>";
        echo "Customer ID: " . $result['customerId'] . "<br>";
    } else {
        echo "✗ Registration failed: " . $result['message'] . "<br>";
    }
} catch (Exception $e) {
    echo "✗ Registration error: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h3>All Tests Complete</h3>";
echo "<p>If all tests passed, your authentication.php should work.</p>";
?>