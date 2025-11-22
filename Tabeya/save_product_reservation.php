<?php
/**
 * SAVE PRODUCT RESERVATION WITH GCASH PAYMENT
 * Handles: Products, GCash Receipt Upload, Special Requests, Delivery Options
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tabeya_system";

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid request"]);
        ob_end_flush();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Only POST requests allowed"]);
        ob_end_flush();
        exit;
    }

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database connection failed"]);
        ob_end_flush();
        exit;
    }

    $conn->set_charset("utf8mb4");

    // ============================================================
    // PARSE INPUT DATA
    // ============================================================

    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $total_price = isset($_POST['total_price']) ? floatval($_POST['total_price']) : 0;
    $products_json = isset($_POST['selected_products']) ? $_POST['selected_products'] : '[]';
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Cash';
    $special_requests = isset($_POST['special_requests']) ? trim($_POST['special_requests']) : '';
    $delivery_option = isset($_POST['delivery_option']) ? $_POST['delivery_option'] : 'Pickup';
    $delivery_address = isset($_POST['delivery_address']) ? trim($_POST['delivery_address']) : '';
    
    $products = json_decode($products_json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid product data"]);
        $conn->close();
        ob_end_flush();
        exit;
    }

    // ============================================================
    // VALIDATE INPUT
    // ============================================================

    if (empty($customer_id) || empty($products) || count($products) === 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing required data"]);
        $conn->close();
        ob_end_flush();
        exit;
    }

    if ($delivery_option === 'Delivery' && empty($delivery_address)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Delivery address required"]);
        $conn->close();
        ob_end_flush();
        exit;
    }

    // ============================================================
    // HANDLE GCASH RECEIPT UPLOAD
    // ============================================================

    $receipt_path = null;
    $receipt_filename = null;

    if ($payment_method === 'GCash' && isset($_FILES['gcash_receipt'])) {
        $file = $_FILES['gcash_receipt'];

        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid file type"]);
            $conn->close();
            ob_end_flush();
            exit;
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "File too large"]);
            $conn->close();
            ob_end_flush();
            exit;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Upload error"]);
            $conn->close();
            ob_end_flush();
            exit;
        }

        // Create upload directory
        $upload_dir = __DIR__ . '/uploads/gcash_receipts/' . date('Y') . '/' . date('m') . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate unique filename
        $receipt_filename = 'receipt_' . $customer_id . '_' . time() . '_' . md5_file($file['tmp_name']) . '.jpg';
        $receipt_path = $upload_dir . $receipt_filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $receipt_path)) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to save receipt"]);
            $conn->close();
            ob_end_flush();
            exit;
        }

        // Store relative path for database
        $receipt_path = 'uploads/gcash_receipts/' . date('Y') . '/' . date('m') . '/' . $receipt_filename;
    }

    // ============================================================
    // START TRANSACTION
    // ============================================================

    $conn->begin_transaction();

    // ============================================================
    // FIND LATEST RESERVATION
    // ============================================================

    $find_res_sql = "SELECT ReservationID FROM reservations 
                     WHERE CustomerID = ? 
                     ORDER BY ReservationID DESC LIMIT 1";
    
    $find_res_stmt = $conn->prepare($find_res_sql);
    
    if (!$find_res_stmt) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error"]);
        $conn->close();
        ob_end_flush();
        exit;
    }

    $find_res_stmt->bind_param("i", $customer_id);
    $find_res_stmt->execute();
    $res_result = $find_res_stmt->get_result();

    if ($res_result->num_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Reservation not found"]);
        $find_res_stmt->close();
        $conn->close();
        ob_end_flush();
        exit;
    }

    $reservation_row = $res_result->fetch_assoc();
    $reservation_id = intval($reservation_row['ReservationID']);
    $find_res_stmt->close();

    // ============================================================
    // UPDATE RESERVATION WITH DELIVERY & SPECIAL REQUESTS
    // ============================================================

    $update_reservation_sql = "UPDATE reservations 
                               SET DeliveryOption = ?,
                                   DeliveryAddress = ?,
                                   SpecialRequests = ?
                               WHERE ReservationID = ?";
    
    $update_res_stmt = $conn->prepare($update_reservation_sql);
    
    if (!$update_res_stmt) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error"]);
        $conn->close();
        ob_end_flush();
        exit;
    }

    $update_res_stmt->bind_param(
        "sssi",
        $delivery_option,
        $delivery_address,
        $special_requests,
        $reservation_id
    );

    if (!$update_res_stmt->execute()) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to update reservation"]);
        $update_res_stmt->close();
        $conn->close();
        ob_end_flush();
        exit;
    }

    $update_res_stmt->close();

    // ============================================================
    // DELETE PLACEHOLDER ITEMS
    // ============================================================

    $delete_sql = "DELETE FROM reservation_items 
                   WHERE ReservationID = ? AND ProductName = 'Menu Selection Pending'";
    
    $delete_stmt = $conn->prepare($delete_sql);
    
    if ($delete_stmt) {
        $delete_stmt->bind_param("i", $reservation_id);
        $delete_stmt->execute();
        $delete_stmt->close();
    }

    // ============================================================
    // INSERT SELECTED PRODUCTS
    // ============================================================

    $insert_sql = "INSERT INTO reservation_items 
                   (ReservationID, ProductName, Quantity, UnitPrice, TotalPrice) 
                   VALUES (?, ?, ?, ?, ?)";

    $insert_stmt = $conn->prepare($insert_sql);

    if (!$insert_stmt) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error"]);
        $conn->close();
        ob_end_flush();
        exit;
    }

    foreach ($products as $product) {
        $product_name = isset($product['name']) ? $product['name'] : '';
        $quantity = isset($product['quantity']) ? intval($product['quantity']) : 0;
        $unit_price = isset($product['price']) ? floatval($product['price']) : 0;
        $item_total = $quantity * $unit_price;

        if (empty($product_name) || $quantity <= 0) {
            continue;
        }

        $insert_stmt->bind_param(
            "isidi",
            $reservation_id,
            $product_name,
            $quantity,
            $unit_price,
            $item_total
        );

        if (!$insert_stmt->execute()) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to save products"]);
            $insert_stmt->close();
            $conn->close();
            ob_end_flush();
            exit;
        }
    }

    $insert_stmt->close();

    // ============================================================
    // UPDATE PAYMENT RECORD WITH GCASH RECEIPT
    // ============================================================

    $update_payment_sql = "UPDATE reservation_payments 
                           SET PaymentMethod = ?,
                               PaymentStatus = ?,
                               AmountPaid = ?,
                               ProofOfPayment = ?,
                               ReceiptFileName = ?
                           WHERE ReservationID = ?";

    $update_payment_stmt = $conn->prepare($update_payment_sql);

    if ($update_payment_stmt) {
        $payment_status = ($payment_method === 'GCash' && $receipt_path) ? 'Pending' : 'Pending';
        
        $update_payment_stmt->bind_param(
            "ssdssi",
            $payment_method,
            $payment_status,
            $total_price,
            $receipt_path,
            $receipt_filename,
            $reservation_id
        );
        
        $update_payment_stmt->execute();
        $update_payment_stmt->close();
    }

    // ============================================================
    // LOG TRANSACTION
    // ============================================================

    $log_sql = "INSERT INTO customer_logs 
                (CustomerID, TransactionType, Details) 
                VALUES (?, ?, ?)";

    $log_stmt = $conn->prepare($log_sql);

    if ($log_stmt) {
        $transaction_type = 'RESERVATION_COMPLETED';
        $details = "GCash Payment received. Reservation #" . $reservation_id . " with " . count($products) . " items. " . 
                   "Delivery: " . $delivery_option . ". Special requests: " . substr($special_requests, 0, 50);

        $log_stmt->bind_param(
            "iss",
            $customer_id,
            $transaction_type,
            $details
        );

        $log_stmt->execute();
        $log_stmt->close();
    }

    // ============================================================
    // COMMIT TRANSACTION
    // ============================================================

    $conn->commit();
    $conn->close();

    // ============================================================
    // SUCCESS RESPONSE
    // ============================================================

    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "Reservation saved successfully!",
        "reservation_id" => $reservation_id,
        "total_amount" => $total_price,
        "product_count" => count($products),
        "payment_method" => $payment_method,
        "delivery_option" => $delivery_option
    ]);

    ob_end_flush();
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "An error occurred: " . $e->getMessage()
    ]);
    ob_end_flush();
    exit;
}

ob_end_flush();
?>