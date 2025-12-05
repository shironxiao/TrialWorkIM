<?php
// Tiyakin na ang file na ito ay makokonekta sa inyong database.
 require_once(__DIR__ . '/api/config/db_config.php');
// --- 2. Check for POST Data ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: Menu.html");
    exit();
}

// I-retrieve ang data mula sa form
$customer_id = $_POST['customer_id'] ?? null;
$total_amount = $_POST['total_amount'] ?? 0.00;
$order_type_raw = $_POST['order_type'] ?? 'PICKUP';
// Inalis na ang $address dahil wala itong column sa orders table
$payment_method_raw = $_POST['payment_method'] ?? 'COD';
$cart_data_json = $_POST['cart_data'] ?? '[]';

// Convert JSON cart data to PHP array
$cart_items = json_decode($cart_data_json, true);

// Basic validation: Dapat may Customer ID at Cart Items
if (empty($customer_id) || !is_numeric($customer_id) || $customer_id <= 0 || !is_array($cart_items) || count($cart_items) === 0) {
    echo "<script>alert('Error: Missing customer ID or empty cart.'); window.location.href='Menu.html';</script>";
    exit();
}

// --- 3. Data Mapping and Sanitization ---

// OrderType Mapping: DELIVERY->Online (Delivery), PICKUP->Online (Pickup)
$order_type_db = ($order_type_raw === 'DELIVERY') ? 'Online' : 'Online'; 
$order_source_db = 'Website'; 

// Payment Method Mapping: Tiyakin na tugma sa ENUM('Cash', 'GCash', 'COD')
$payment_method_db = '';
switch ($payment_method_raw) {
    case 'COD':
        $payment_method_db = 'COD';
        break;
    case 'GCASH':
        $payment_method_db = 'GCash';
        break;
    default:
        $payment_method_db = 'COD'; // Default sa COD kung walang napili
        break;
}

// --- 4. Start Database Transaction ---
$conn->begin_transaction();
$success = true;

try {
    // === A. Insert into 'orders' table (FIXED: Inalis ang DeliveryAddress) ===
    $items_count = count($cart_items);
    $sql_order = "INSERT INTO orders (CustomerID, OrderType, OrderSource, TotalAmount, OrderStatus, OrderDate, OrderTime, ItemsOrderedCount) VALUES (?, ?, ?, ?, 'Preparing', CURDATE(), CURTIME(), ?)";
    $stmt_order = $conn->prepare($sql_order);
    
    // CRITICAL ERROR CHECK 1
    if ($stmt_order === false) {
        $error_message = "SQL Prepare Failed for orders: " . $conn->error . " | Query: " . $sql_order;
        throw new Exception($error_message);
    }

    // Parameters: i (CustomerID), s (OrderType), s (OrderSource), d (TotalAmount), i (ItemsOrderedCount)
    $stmt_order->bind_param("issdi", $customer_id, $order_type_db, $order_source_db, $total_amount, $items_count);
    
    if (!$stmt_order->execute()) {
        $success = false;
        throw new Exception("Order insert failed: " . $stmt_order->error);
    }
    
    $order_id = $conn->insert_id; 
    $stmt_order->close();
    
    // === B. Insert into 'order_items' table ===
    $sql_item = "INSERT INTO order_items (OrderID, ProductName, Quantity, UnitPrice) VALUES (?, ?, ?, ?)";
    $stmt_item = $conn->prepare($sql_item);

    // CRITICAL ERROR CHECK 2
    if ($stmt_item === false) {
        $error_message = "SQL Prepare Failed for order_items: " . $conn->error . " | Query: " . $sql_item;
        throw new Exception($error_message);
    }

    foreach ($cart_items as $item) {
        $name = $item['name'] ?? 'Unknown Product';
        $quantity = $item['quantity'] ?? 1;
        $price = $item['price'] ?? 0.00; // Ito ay UnitPrice
        
        // Parameters: i (OrderID), s (ProductName), i (Quantity), d (UnitPrice)
        $stmt_item->bind_param("isid", $order_id, $name, $quantity, $price);
        
        if (!$stmt_item->execute()) {
            $success = false;
            throw new Exception("Order item insert failed: " . $stmt_item->error);
        }
    }
    $stmt_item->close();
    
    // === C. Insert into 'payments' table (FIXED: Ginamit ang AmountPaid) ===
    // TAMA: Pinalitan ang PaymentAmount ng AmountPaid
    $sql_payment = "INSERT INTO payments (OrderID, PaymentMethod, AmountPaid, PaymentStatus, PaymentSource) VALUES (?, ?, ?, 'Pending', ?)";
    $stmt_payment = $conn->prepare($sql_payment);
    
    // CRITICAL ERROR CHECK 3
    if ($stmt_payment === false) {
        $error_message = "SQL Prepare Failed for payments: " . $conn->error . " | Query: " . $sql_payment;
        throw new Exception($error_message);
    }

    // Parameters: i (OrderID), s (PaymentMethod), d (AmountPaid), s (PaymentSource)
    $stmt_payment->bind_param("isds", $order_id, $payment_method_db, $total_amount, $order_source_db);
    
    if (!$stmt_payment->execute()) {
        $success = false;
        throw new Exception("Payment insert failed: " . $stmt_payment->error);
    }
    $stmt_payment->close();
    
    // Commit the transaction
    $conn->commit();
    
    // --- 5. Final Action: Clear Cart (via JS) and Redirect ---
    // Huwag kalimutang i-call ang Stored Procedure para i-update ang TotalOrdersCount ng Customer
    $stmt_proc = $conn->prepare("CALL IncrementCustomerOrderCount(?)");
    $stmt_proc->bind_param("i", $customer_id);
    $stmt_proc->execute();
    $stmt_proc->close();
    
    echo "<script>
        localStorage.removeItem('tabeyaCart');
        alert('Order placed successfully! Your Order ID is: " . $order_id . "');
        window.location.href = 'index.html'; 
    </script>";
    
} catch (Exception $e) {
    // Rollback the transaction
    $conn->rollback();
    
    error_log("Order processing failed: " . $e->getMessage());
    
    // Show user-friendly error with the exact SQL error message
    echo "<script>
        alert('An error occurred while processing your order.\\n\\nDETAILS: " . addslashes($e->getMessage()) . "');
        window.location.href='Checkout.html'; 
    </script>";
}

$conn->close();
?>