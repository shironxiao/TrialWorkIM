<?php
/**
 * ENHANCED ORDER PROCESSING - FIXED VERSION
 * Properly handles delivery options and order status
 * Uses OrderStatus only (WebsiteStatus column removed)
 */

require_once(__DIR__ . '/api/config/db_config.php');
require_once(__DIR__ . '/api/functions/activity_logger.php');

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/order_error.log');

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        die(json_encode(["success" => false, "message" => "POST required"]));
    }

    // Get form data
    $customer_id = $_POST['customer_id'] ?? null;
    $total_amount = $_POST['total_amount'] ?? 0.00;
    $order_type_raw = $_POST['order_type'] ?? 'PICKUP';
    $payment_method_raw = $_POST['payment_method'] ?? 'COD';
    $cart_data_json = $_POST['cart_data'] ?? '[]';
    $special_requests = $_POST['special_requests'] ?? null;
    $delivery_address = $_POST['address'] ?? null;
    $transaction_id = $_POST['transaction_id'] ?? null;

    // Validate cart data
    $cart_items = json_decode($cart_data_json, true);
    if (empty($customer_id) || !is_numeric($customer_id) || $customer_id <= 0 || !is_array($cart_items) || count($cart_items) === 0) {
        http_response_code(400);
        die(json_encode(["success" => false, "message" => "Missing customer ID or empty cart"]));
    }

    // Map order type to database enum
    // OrderType enum: 'Dine-in','Takeout','Online'
    $order_type_db = 'Online';

    // OrderSource enum: 'POS','Website'
    $order_source_db = 'Website';

    // Set DeliveryOption enum: 'Delivery','Pickup'
    $delivery_option_db = null;
    if (strtoupper($order_type_raw) === 'DELIVERY') {
        $delivery_option_db = 'Delivery';

        // Validate delivery address is provided
        if (empty($delivery_address)) {
            http_response_code(400);
            die(json_encode(["success" => false, "message" => "Delivery address required for delivery orders"]));
        }
    } else {
        $delivery_option_db = 'Pickup';
        $delivery_address = null; // Clear address for pickup orders
    }

    // Map payment method
    $payment_method_db = ($payment_method_raw === 'GCASH') ? 'GCash' : 'COD';

    // Handle GCash receipt upload
    $receipt_path = null;
    $receipt_filename = null;

    if ($payment_method_raw === 'GCASH' && isset($_FILES['gcash_receipt']) && $_FILES['gcash_receipt']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['gcash_receipt'];

        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_types)) {
            http_response_code(400);
            die(json_encode(["success" => false, "message" => "Invalid image type"]));
        }

        // Validate file size (5MB max)
        if ($file['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            die(json_encode(["success" => false, "message" => "File too large (max 5MB)"]));
        }

        // Create upload directory
        $upload_dir = __DIR__ . '/uploads/order_receipts/' . date('Y') . '/' . date('m') . '/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }

        // Generate unique filename
        $receipt_filename = 'order_' . $customer_id . '_' . time() . '_' . rand(1000, 9999) . '.jpg';
        $full_path = $upload_dir . $receipt_filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            $receipt_path = 'uploads/order_receipts/' . date('Y') . '/' . date('m') . '/' . $receipt_filename;
        } else {
            error_log("Failed to move uploaded file");
        }
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Set OrderStatus to 'Pending' for all new orders
        $order_status = 'Pending';

        // Insert into orders table (NO WebsiteStatus column)
        $items_count = count($cart_items);
        $sql_order = "INSERT INTO orders 
                      (CustomerID, OrderType, OrderSource, TotalAmount, OrderStatus,
                       OrderDate, OrderTime, ItemsOrderedCount, Remarks, 
                       DeliveryAddress, SpecialRequests, DeliveryOption) 
                      VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?)";

        $stmt_order = $conn->prepare($sql_order);
        if ($stmt_order === false) {
            throw new Exception("SQL Prepare Failed for orders: " . $conn->error);
        }

        // Create appropriate remarks based on delivery option
        $order_remarks = ($delivery_option_db === 'Delivery') ? 'Delivery Order' : 'Pickup Order';

        $stmt_order->bind_param(
            "issdsissss",
            $customer_id,
            $order_type_db,        // 'Online'
            $order_source_db,      // 'Website'
            $total_amount,
            $order_status,         // 'Pending'
            $items_count,
            $order_remarks,
            $delivery_address,     // NULL for pickup, address for delivery
            $special_requests,
            $delivery_option_db    // 'Delivery' or 'Pickup'
        );

        if (!$stmt_order->execute()) {
            throw new Exception("Order insert failed: " . $stmt_order->error);
        }

        $order_id = $conn->insert_id;
        $stmt_order->close();

        // Insert order items
        $sql_item = "INSERT INTO order_items (OrderID, ProductName, Quantity, UnitPrice, SpecialInstructions) 
                     VALUES (?, ?, ?, ?, ?)";
        $stmt_item = $conn->prepare($sql_item);

        if ($stmt_item === false) {
            throw new Exception("SQL Prepare Failed for order_items: " . $conn->error);
        }

        foreach ($cart_items as $item) {
            $name = $item['name'] ?? 'Unknown Product';
            $quantity = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0.00;

            $stmt_item->bind_param("isids", $order_id, $name, $quantity, $price, $special_requests);

            if (!$stmt_item->execute()) {
                throw new Exception("Order item insert failed: " . $stmt_item->error);
            }
        }
        $stmt_item->close();

        // Insert payment record
        $sql_payment = "INSERT INTO payments 
                        (OrderID, PaymentMethod, AmountPaid, PaymentStatus, PaymentSource, 
                         ProofOfPayment, ReceiptFileName, Notes, TransactionID) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt_payment = $conn->prepare($sql_payment);
        if ($stmt_payment === false) {
            throw new Exception("SQL Prepare Failed for payments: " . $conn->error);
        }

        $payment_status = 'Pending'; // Always Pending for new orders
        $payment_notes = ($payment_method_raw === 'GCASH') ? 'GCash receipt uploaded - awaiting verification' : 'Cash on Delivery';

        $stmt_payment->bind_param(
            "isdssssss",
            $order_id,
            $payment_method_db,
            $total_amount,
            $payment_status,
            $order_source_db,
            $receipt_path,
            $receipt_filename,
            $payment_notes,
            $transaction_id
        );

        if (!$stmt_payment->execute()) {
            throw new Exception("Payment insert failed: " . $stmt_payment->error);
        }
        $stmt_payment->close();

        // Update customer order count
        try {
            $stmt_proc = $conn->prepare("CALL IncrementCustomerOrderCount(?)");
            if ($stmt_proc) {
                $stmt_proc->bind_param("i", $customer_id);
                $stmt_proc->execute();
                $stmt_proc->close();

                while ($conn->more_results()) {
                    $conn->next_result();
                }
            } else {
                // Fallback: Direct update
                $update_sql = "UPDATE customers 
                              SET TotalOrdersCount = TotalOrdersCount + 1,
                                  LastTransactionDate = NOW()
                              WHERE CustomerID = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $customer_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
        } catch (Exception $e) {
            error_log("Customer count update failed: " . $e->getMessage());

            $update_sql = "UPDATE customers 
                          SET TotalOrdersCount = TotalOrdersCount + 1,
                              LastTransactionDate = NOW()
                          WHERE CustomerID = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $customer_id);
            $update_stmt->execute();
            $update_stmt->close();
        }

        // Commit transaction
        $conn->commit();

        // Log order activity
        $customer_name = $_POST['name'] ?? 'Customer';
        logCustomerOrderPlaced(
            $conn,
            $customer_id,
            $customer_name,
            $order_id,
            $total_amount,
            $delivery_option_db
        );

        error_log("SUCCESS: Order #$order_id created - Status: $order_status, Delivery: $delivery_option_db");

        ob_clean();
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Order placed successfully!",
            "order_id" => $order_id,
            "total_amount" => $total_amount,
            "payment_method" => $payment_method_db,
            "order_status" => $order_status,
            "delivery_option" => $delivery_option_db
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("TRANSACTION ERROR: " . $e->getMessage());

        ob_clean();
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Database error: " . $e->getMessage()
        ]);
    }

    $conn->close();

} catch (Exception $e) {
    error_log("FATAL ERROR: " . $e->getMessage());

    ob_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

ob_end_flush();
?>