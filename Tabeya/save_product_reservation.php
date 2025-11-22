<?php
/**
 * SAVE PRODUCT RESERVATION - FIXED VERSION
 * Handles products, GCash receipt, delivery options
 */

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/reservation_error.log');

// Start output buffering
ob_start();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

try {
    // ============================================================
    // REQUEST VALIDATION
    // ============================================================
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die(json_encode(["status" => "error", "message" => "POST required"]));
    }

    // Check if AJAX request
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if (!$is_ajax) {
        // Still accept if data is present
        if (empty($_POST) && empty($_FILES)) {
            http_response_code(400);
            die(json_encode(["status" => "error", "message" => "Invalid request format"]));
        }
    }

    // ============================================================
    // DATABASE CONNECTION
    // ============================================================

    $servername = "localhost";
    $db_username = "root";
    $db_password = "";
    $dbname = "tabeya_system";

    $conn = new mysqli($servername, $db_username, $db_password, $dbname);

    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode([
            "status" => "error", 
            "message" => "Database connection failed: " . $conn->connect_error
        ]));
    }

    $conn->set_charset("utf8mb4");

    // ============================================================
    // PARSE & VALIDATE INPUT DATA
    // ============================================================

    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $customer_name = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
    $customer_email = isset($_POST['customer_email']) ? trim($_POST['customer_email']) : '';
    $customer_phone = isset($_POST['customer_phone']) ? trim($_POST['customer_phone']) : '';
    $event_date = isset($_POST['event_date']) ? $_POST['event_date'] : '';
    $event_time = isset($_POST['event_time']) ? $_POST['event_time'] : '';
    $guests = isset($_POST['guests']) ? intval($_POST['guests']) : 0;
    $event_type = isset($_POST['event_type']) ? trim($_POST['event_type']) : '';
    $total_price = isset($_POST['total_price']) ? floatval($_POST['total_price']) : 0;
    $products_json = isset($_POST['selected_products']) ? $_POST['selected_products'] : '[]';
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Cash';
    $special_requests = isset($_POST['special_requests']) ? trim($_POST['special_requests']) : '';
    $delivery_option = isset($_POST['delivery_option']) ? $_POST['delivery_option'] : 'Pickup';
    $delivery_address = isset($_POST['delivery_address']) ? trim($_POST['delivery_address']) : '';

    // Parse products
    $products = @json_decode($products_json, true);
    if (!is_array($products)) {
        $products = [];
    }

    // Validate required fields
    if ($customer_id <= 0) {
        http_response_code(400);
        die(json_encode(["status" => "error", "message" => "Invalid customer ID"]));
    }

    if (empty($event_date) || empty($event_time)) {
        http_response_code(400);
        die(json_encode(["status" => "error", "message" => "Event date/time required"]));
    }

    if (count($products) === 0) {
        http_response_code(400);
        die(json_encode(["status" => "error", "message" => "No products selected"]));
    }

    if ($delivery_option === 'Delivery' && empty($delivery_address)) {
        http_response_code(400);
        die(json_encode(["status" => "error", "message" => "Delivery address required"]));
    }

    // ============================================================
    // HANDLE FILE UPLOAD
    // ============================================================

    $receipt_path = null;
    $receipt_filename = null;

    if ($payment_method === 'GCash' && isset($_FILES['gcash_receipt'])) {
        $file = $_FILES['gcash_receipt'];

        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime_type, $allowed_types)) {
                http_response_code(400);
                die(json_encode(["status" => "error", "message" => "Invalid image type"]));
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                http_response_code(400);
                die(json_encode(["status" => "error", "message" => "File too large (max 5MB)"]));
            }

            // Create directories
            $upload_base = __DIR__ . '/uploads/gcash_receipts/';
            $year_dir = $upload_base . date('Y') . '/';
            $month_dir = $year_dir . date('m') . '/';

            if (!is_dir($upload_base)) @mkdir($upload_base, 0755, true);
            if (!is_dir($year_dir)) @mkdir($year_dir, 0755, true);
            if (!is_dir($month_dir)) @mkdir($month_dir, 0755, true);

            // Generate filename
            $receipt_filename = 'receipt_' . $customer_id . '_' . time() . '.jpg';
            $receipt_path = $month_dir . $receipt_filename;

            if (!move_uploaded_file($file['tmp_name'], $receipt_path)) {
                http_response_code(500);
                die(json_encode(["status" => "error", "message" => "Failed to upload file"]));
            }

            // Store relative path
            $receipt_path = 'uploads/gcash_receipts/' . date('Y') . '/' . date('m') . '/' . $receipt_filename;
        }
    }

    // ============================================================
    // DATABASE TRANSACTION
    // ============================================================

    $conn->begin_transaction();

    try {
        // Find or create reservation
        $sql = "SELECT ReservationID FROM reservations WHERE CustomerID = ? ORDER BY ReservationID DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare error: " . $conn->error);
        }

        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $reservation_id = intval($row['ReservationID']);
        } else {
            // Create new reservation
            $sql = "INSERT INTO reservations 
                    (CustomerID, ReservationType, EventType, EventDate, EventTime, 
                     NumberOfGuests, ServiceType, ContactNumber, ReservationStatus,
                     DeliveryOption, SpecialRequests)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Insert prepare error: " . $conn->error);
            }

            $reservation_type = 'Online';
            $service_type = 'Catering Only';
            $status = 'Pending';

            $stmt->bind_param(
                "issssssssss",
                $customer_id,
                $reservation_type,
                $event_type,
                $event_date,
                $event_time,
                $guests,
                $service_type,
                $customer_phone,
                $status,
                $delivery_option,
                $special_requests
            );

            if (!$stmt->execute()) {
                throw new Exception("Insert execute error: " . $stmt->error);
            }

            $reservation_id = $conn->insert_id;
        }

        $stmt->close();

        // Update delivery address if delivery selected
        if ($delivery_option === 'Delivery' && !empty($delivery_address)) {
            $sql = "UPDATE reservations SET DeliveryAddress = ? WHERE ReservationID = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("si", $delivery_address, $reservation_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Delete old items
        $sql = "DELETE FROM reservation_items WHERE ReservationID = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $reservation_id);
            $stmt->execute();
            $stmt->close();
        }

        // Insert products
        $sql = "INSERT INTO reservation_items (ReservationID, ProductName, Quantity, UnitPrice, TotalPrice) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Insert items prepare error: " . $conn->error);
        }

        foreach ($products as $product) {
            $product_name = isset($product['name']) ? trim($product['name']) : '';
            $quantity = isset($product['quantity']) ? intval($product['quantity']) : 0;
            $unit_price = isset($product['price']) ? floatval($product['price']) : 0;
            $item_total = $quantity * $unit_price;

            if (empty($product_name) || $quantity <= 0) continue;

            $stmt->bind_param("isidi", $reservation_id, $product_name, $quantity, $unit_price, $item_total);
            
            if (!$stmt->execute()) {
                throw new Exception("Insert items execute error: " . $stmt->error);
            }
        }

        $stmt->close();

        // Check if payment record exists
        $sql = "SELECT ReservationPaymentID FROM reservation_payments WHERE ReservationID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $payment_result = $stmt->get_result();
        $stmt->close();

        if ($payment_result->num_rows === 0) {
            // Create payment record
            $sql = "INSERT INTO reservation_payments 
                    (ReservationID, PaymentMethod, PaymentStatus, AmountPaid, PaymentSource, ProofOfPayment, ReceiptFileName)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Insert payment prepare error: " . $conn->error);
            }

            $status = 'Pending';
            $source = 'Website';

            $stmt->bind_param("isdsiss", $reservation_id, $payment_method, $status, $total_price, $source, $receipt_path, $receipt_filename);
            
            if (!$stmt->execute()) {
                throw new Exception("Insert payment execute error: " . $stmt->error);
            }

            $stmt->close();
        } else {
            // Update payment record
            $sql = "UPDATE reservation_payments 
                    SET PaymentMethod = ?, AmountPaid = ?, ProofOfPayment = ?, ReceiptFileName = ?, PaymentStatus = ?
                    WHERE ReservationID = ?";
            
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $status = 'Pending';
                $stmt->bind_param("sdssi", $payment_method, $total_price, $receipt_path, $receipt_filename, $status, $reservation_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Commit transaction
        $conn->commit();

        // Log success
        error_log("SUCCESS: Reservation $reservation_id created/updated for customer $customer_id");

        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Reservation saved successfully!",
            "reservation_id" => $reservation_id,
            "total_amount" => $total_price
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("ERROR: " . $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $e->getMessage()
        ]);
    }

    $conn->close();

} catch (Exception $e) {
    error_log("FATAL ERROR: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
}

ob_end_flush();
?>