<?php
/**
 * FETCH PRODUCTS WITH INGREDIENT AVAILABILITY CHECK
 * Returns products with real-time inventory status
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Database connection

   require_once(__DIR__ . '/api/config/db_config.php');
   header('Content-Type: application/json; charset=utf-8');

try {
    $conn = new mysqli("localhost", "root", "", "tabeya_system");

    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Database connection failed"
        ]);
        ob_end_flush();
        exit;
    }

    $conn->set_charset("utf8mb4");

    // ============================================================
    // FETCH PRODUCTS WITH INGREDIENT AVAILABILITY
    // ============================================================

    $sql = "SELECT 
                p.ProductID, 
                p.ProductName, 
                p.Category, 
                p.Description, 
                p.Price, 
                p.Availability, 
                p.ServingSize, 
                p.Image, 
                p.PopularityTag,
                -- Check if all ingredients are available
                (SELECT COUNT(DISTINCT pi.IngredientID)
                 FROM product_ingredients pi
                 WHERE pi.ProductID = p.ProductID) as TotalIngredients,
                (SELECT COUNT(DISTINCT pi.IngredientID)
                 FROM product_ingredients pi
                 LEFT JOIN (
                     SELECT IngredientID, SUM(StockQuantity) as TotalStock
                     FROM inventory_batches
                     WHERE BatchStatus = 'Active'
                     GROUP BY IngredientID
                 ) ib ON pi.IngredientID = ib.IngredientID
                 WHERE pi.ProductID = p.ProductID
                 AND COALESCE(ib.TotalStock, 0) >= pi.QuantityUsed
                ) as AvailableIngredients
            FROM products p
            WHERE p.Availability = 'Available'
            ORDER BY p.Category ASC, p.ProductID ASC";

    $result = $conn->query($sql);

    if (!$result) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Query failed: " . $conn->error
        ]);
        $conn->close();
        ob_end_flush();
        exit;
    }

    $products = [];

    while ($row = $result->fetch_assoc()) {
        $row['ProductID'] = intval($row['ProductID']);
        $row['Price'] = floatval($row['Price']);
        
        $totalIngredients = intval($row['TotalIngredients']);
        $availableIngredients = intval($row['AvailableIngredients']);
        
        // Determine ingredient availability
        if ($totalIngredients == 0) {
            // No ingredients defined (drinks, etc.)
            $row['IngredientAvailable'] = true;
            $row['AvailabilityReason'] = 'No ingredients required';
        } elseif ($availableIngredients == $totalIngredients) {
            // All ingredients available
            $row['IngredientAvailable'] = true;
            $row['AvailabilityReason'] = 'All ingredients in stock';
        } elseif ($availableIngredients > 0) {
            // Some ingredients available
            $row['IngredientAvailable'] = true;
            $row['AvailabilityReason'] = 'Low stock';
        } else {
            // No ingredients available
            $row['IngredientAvailable'] = false;
            $row['AvailabilityReason'] = 'Out of stock';
        }
        
        // Remove internal counts from response
        unset($row['TotalIngredients']);
        unset($row['AvailableIngredients']);
        
        $products[] = $row;
    }

    $conn->close();

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Products fetched successfully",
        "count" => count($products),
        "products" => $products
    ]);

    ob_end_flush();
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
    ob_end_flush();
    exit;
}

ob_end_flush();
?>