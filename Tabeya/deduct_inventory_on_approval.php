<?php
/**
 * DEDUCT INVENTORY ON RESERVATION APPROVAL
 * Call this when admin approves a reservation
 * This deducts ingredients from inventory based on reservation items
 */

ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/inventory_deduction.log');

header('Content-Type: application/json; charset=utf-8');

 require_once(__DIR__ . '/api/config/db_config.php');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die(json_encode(["success" => false, "message" => "POST required"]));
    }

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode(["success" => false, "message" => "Database connection failed"]));
    }
    $conn->set_charset("utf8mb4");

    // Get reservation ID from request
    $input = json_decode(file_get_contents('php://input'), true);
    $reservationId = isset($input['reservation_id']) ? intval($input['reservation_id']) : 0;
    
    if (!$reservationId && isset($_POST['reservation_id'])) {
        $reservationId = intval($_POST['reservation_id']);
    }

    if ($reservationId <= 0) {
        http_response_code(400);
        die(json_encode(["success" => false, "message" => "Invalid reservation ID"]));
    }

    // Check if reservation exists and is being approved
    $checkSql = "SELECT ReservationID, ReservationStatus FROM reservations WHERE ReservationID = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $reservationId);
    $checkStmt->execute();
    $reservation = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$reservation) {
        http_response_code(404);
        die(json_encode(["success" => false, "message" => "Reservation not found"]));
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Get all items in this reservation
        $itemsSql = "SELECT ri.ProductName, ri.Quantity 
                     FROM reservation_items ri 
                     WHERE ri.ReservationID = ?";
        $itemsStmt = $conn->prepare($itemsSql);
        $itemsStmt->bind_param("i", $reservationId);
        $itemsStmt->execute();
        $items = $itemsStmt->get_result();

        $deductedIngredients = [];
        $errors = [];

        while ($item = $items->fetch_assoc()) {
            $productName = $item['ProductName'];
            $orderQty = intval($item['Quantity']);

            // Get ProductID from product name
            $prodSql = "SELECT ProductID FROM products WHERE ProductName = ?";
            $prodStmt = $conn->prepare($prodSql);
            $prodStmt->bind_param("s", $productName);
            $prodStmt->execute();
            $prodResult = $prodStmt->get_result()->fetch_assoc();
            $prodStmt->close();

            if (!$prodResult) {
                $errors[] = "Product not found: $productName";
                continue;
            }

            $productId = $prodResult['ProductID'];

            // Get all ingredients for this product
            $ingredientsSql = "SELECT 
                    pi.IngredientID,
                    pi.QuantityUsed,
                    i.IngredientName,
                    inv.InventoryID,
                    inv.StockQuantity
                FROM product_ingredients pi
                JOIN ingredients i ON pi.IngredientID = i.IngredientID
                JOIN inventory inv ON i.IngredientID = inv.IngredientID
                WHERE pi.ProductID = ?";
            
            $ingStmt = $conn->prepare($ingredientsSql);
            $ingStmt->bind_param("i", $productId);
            $ingStmt->execute();
            $ingredients = $ingStmt->get_result();

            while ($ing = $ingredients->fetch_assoc()) {
                $inventoryId = $ing['InventoryID'];
                $ingredientName = $ing['IngredientName'];
                $qtyPerServing = floatval($ing['QuantityUsed']);
                $currentStock = floatval($ing['StockQuantity']);
                
                // Calculate total quantity to deduct
                $totalDeduction = $qtyPerServing * $orderQty;
                
                // Use the stored procedure to log and deduct
                $deductSql = "CALL RecordIngredientUsage(?, ?, ?)";
                $deductStmt = $conn->prepare($deductSql);
                $referenceId = "RES-" . $reservationId;
                $deductStmt->bind_param("ids", $inventoryId, $totalDeduction, $referenceId);
                
                if ($deductStmt->execute()) {
                    $deductedIngredients[] = [
                        'ingredient' => $ingredientName,
                        'deducted' => $totalDeduction,
                        'for_product' => $productName
                    ];
                } else {
                    $errors[] = "Failed to deduct $ingredientName for $productName";
                }
                $deductStmt->close();
            }
            $ingStmt->close();
        }
        $itemsStmt->close();

        // Update reservation status to Confirmed
        $updateSql = "UPDATE reservations SET ReservationStatus = 'Confirmed' WHERE ReservationID = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $reservationId);
        $updateStmt->execute();
        $updateStmt->close();

        $conn->commit();

        error_log("SUCCESS: Inventory deducted for reservation #$reservationId");

        ob_clean();
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Reservation approved and inventory deducted",
            "reservation_id" => $reservationId,
            "deductions" => $deductedIngredients,
            "errors" => $errors
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("TRANSACTION ERROR: " . $e->getMessage());
        
        ob_clean();
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Failed to process: " . $e->getMessage()
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