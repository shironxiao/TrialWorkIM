<?php
require_once(__DIR__ . '/../auth/session.php');
require_once(__DIR__ . '/../config/db_config.php');
require_once(__DIR__ . '/../functions/security.php');

setJsonHeaders();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$firstName = trim($input['firstName'] ?? '');
$lastName = trim($input['lastName'] ?? '');
$email = trim($input['email'] ?? '');
$contactNumber = trim($input['contactNumber'] ?? '');

if ($firstName === '' || $email === '' || $contactNumber === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'First name, email, and contact number are required.'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please provide a valid email address.'
    ]);
    exit;
}

if (!preg_match('/^09\d{9}$/', $contactNumber)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Contact number must follow the format 09XXXXXXXXX.'
    ]);
    exit;
}

$customerId = getCurrentUserId();
$lastName = $lastName !== '' ? $lastName : $firstName;

$updateSql = "UPDATE customers SET FirstName = ?, LastName = ?, Email = ?, ContactNumber = ? WHERE CustomerID = ?";
$stmt = $conn->prepare($updateSql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to prepare statement: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param("ssssi", $firstName, $lastName, $email, $contactNumber, $customerId);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update profile: ' . $stmt->error
    ]);
    $stmt->close();
    exit;
}
$stmt->close();

$selectSql = "SELECT CustomerID, FirstName, LastName, Email, ContactNumber FROM customers WHERE CustomerID = ?";
$selectStmt = $conn->prepare($selectSql);

if (!$selectStmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to prepare fetch statement: ' . $conn->error
    ]);
    exit;
}

$selectStmt->bind_param("i", $customerId);
$selectStmt->execute();
$result = $selectStmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Updated profile could not be retrieved.'
    ]);
    $selectStmt->close();
    exit;
}

$customerData = $result->fetch_assoc();
$selectStmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Profile updated successfully.',
    'customer' => $customerData
]);

