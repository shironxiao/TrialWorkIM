<?php
/**
 * SAVE PRODUCT RESERVATION - UNIFIED PAYMENT TABLE
 * Now uses the unified 'payments' table instead of 'reservation_payments'
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/reservation_error.log');

ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die(json_encode(["status" => "error", "message" => "POST required"]));
    }

    // Database connection
    require_once(__DIR__ . '/api/config/db_config.php');
    require_once(__DIR__ . '/api/functions/activity_logger.php');

    // Parse input data
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

    // Parse and validate products
    $products = @json_decode($products_json, true);
    if (!is_array($products))
        $products = [];

    // Validation
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

    // Build ProductSelection string for database
    $product_selection_array = [];
    foreach ($products as $p) {
        $product_selection_array[] = $p['name'] . ' x' . $p['quantity'];
    }
    $product_selection_text = implode(', ', $product_selection_array);

    // Handle file upload (GCash receipt)
    $receipt_path = null;
    $receipt_filename = null;

    if ($payment_method === 'GCash' && isset($_FILES['gcash_receipt']) && $_FILES['gcash_receipt']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['gcash_receipt'];
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

        $upload_dir = __DIR__ . '/uploads/gcash_receipts/' . date('Y') . '/' . date('m') . '/';
        if (!is_dir($upload_dir))
            @mkdir($upload_dir, 0755, true);

        $receipt_filename = 'receipt_' . $customer_id . '_' . time() . '_' . rand(1000, 9999) . '.jpg';
        $full_path = $upload_dir . $receipt_filename;

        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            $receipt_path = 'uploads/gcash_receipts/' . date('Y') . '/' . date('m') . '/' . $receipt_filename;
        }
    }

    // START TRANSACTION
    $conn->begin_transaction();

    try {
        // =====================================================
        // STEP 1: INSERT RESERVATION
        // =====================================================
        $sql = "INSERT INTO reservations 
                (CustomerID, ReservationType, EventType, EventDate, EventTime, 
                 NumberOfGuests, ProductSelection, SpecialRequests, 
                 ContactNumber, ReservationStatus, DeliveryOption, DeliveryAddress)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception("Prepare reservation error: " . $conn->error);

        $reservation_type = 'Online';
        $status = 'Pending';

        $stmt->bind_param(
            "issssissssss",
            $customer_id,
            $reservation_type,
            $event_type,
            $event_date,
            $event_time,
            $guests,
            $product_selection_text,
            $special_requests,
            $customer_phone,
            $status,
            $delivery_option,
            $delivery_address
        );

        if (!$stmt->execute())
            throw new Exception("Insert reservation error: " . $stmt->error);

        $reservation_id = $conn->insert_id;
        $stmt->close();

        // =====================================================
        // STEP 2: INSERT RESERVATION ITEMS
        // =====================================================
        $sql = "INSERT INTO reservation_items (ReservationID, ProductName, Quantity, UnitPrice, TotalPrice) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception("Prepare items error: " . $conn->error);

        foreach ($products as $product) {
            $name = isset($product['name']) ? trim($product['name']) : '';
            $qty = isset($product['quantity']) ? intval($product['quantity']) : 0;
            $price = isset($product['price']) ? floatval($product['price']) : 0;
            $item_total = $qty * $price;

            if (empty($name) || $qty <= 0)
                continue;

            $stmt->bind_param("isidd", $reservation_id, $name, $qty, $price, $item_total);
            if (!$stmt->execute())
                throw new Exception("Insert item error: " . $stmt->error);
        }
        $stmt->close();

        // =====================================================
        // STEP 3: INSERT INTO UNIFIED PAYMENTS TABLE
        // =====================================================
        // Using the unified 'payments' table structure:
        // PaymentID, OrderID, ReservationID, PaymentDate, PaymentMethod, 
        // PaymentStatus, AmountPaid, PaymentSource, ProofOfPayment, 
        // ReceiptFileName, TransactionID, Notes

        $sql = "INSERT INTO payments 
                (ReservationID, PaymentMethod, PaymentStatus, AmountPaid, 
                 PaymentSource, ProofOfPayment, ReceiptFileName, Notes, PaymentDate)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception("Prepare payment error: " . $conn->error);

        // Map payment method to database enum ('Cash','GCash','COD')
        $payment_method_db = ($payment_method === 'GCash') ? 'GCash' : 'Cash';

        // Payment status enum: 'Pending','Completed','Refunded','Failed'
        $payment_status = 'Pending';

        // Payment source enum: 'POS','Website'
        $payment_source = 'Website';

        // Create notes based on payment method
        $payment_notes = ($payment_method === 'GCash')
            ? 'Catering reservation - GCash receipt uploaded, awaiting verification'
            : 'Catering reservation - Payment pending';

        $stmt->bind_param(
            "issdssss",
            $reservation_id,      // ReservationID
            $payment_method_db,   // PaymentMethod (GCash/Cash)
            $payment_status,      // PaymentStatus (Pending)
            $total_price,         // AmountPaid
            $payment_source,      // PaymentSource (Website)
            $receipt_path,        // ProofOfPayment (path to image)
            $receipt_filename,    // ReceiptFileName
            $payment_notes        // Notes
        );

        if (!$stmt->execute())
            throw new Exception("Insert payment error: " . $stmt->error);

        $payment_id = $conn->insert_id;
        $stmt->close();

        // =====================================================
        // STEP 4: UPDATE CUSTOMER RESERVATION COUNT
        // =====================================================
        $sql = "UPDATE customers 
                SET ReservationCount = ReservationCount + 1, 
                    LastTransactionDate = NOW() 
                WHERE CustomerID = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $stmt->close();
        }

        // COMMIT TRANSACTION
        $conn->commit();

        // Log reservation activity
        logCustomerReservation(
            $conn,
            $customer_id,
            $customer_name,
            $reservation_id,
            $event_type,
            $event_date,
            $guests
        );

        error_log("SUCCESS: Reservation #$reservation_id created with Payment #$payment_id for customer $customer_id");

        ob_clean();
        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Reservation saved successfully!",
            "reservation_id" => $reservation_id,
            "payment_id" => $payment_id,
            "total_amount" => $total_price,
            "payment_method" => $payment_method_db,
            "payment_status" => $payment_status
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("TRANSACTION ERROR: " . $e->getMessage());

        ob_clean();
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $e->getMessage()
        ]);
    }

    $conn->close();

} catch (Exception $e) {
    error_log("FATAL ERROR: " . $e->getMessage());

    ob_clean();
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
}

ob_end_flush();
?>