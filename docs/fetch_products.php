<?php
/**
 * FETCH PRODUCTS WITH INGREDIENT AVAILABILITY + RATINGS
 * Unified version (no conflicts)
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Database config
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
    // UNIFIED PRODUCT FETCH + RATINGS + INGREDIENT CHECK
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
                p.OrderCount,
                p.PrepTime,

                -- Star Rating based on OrderCount
                CASE 
                    WHEN p.OrderCount >= 100 THEN 5
                    WHEN p.OrderCount >= 75 THEN 4
                    WHEN p.OrderCount >= 50 THEN 3
                    WHEN p.OrderCount >= 25 THEN 2
                    WHEN p.OrderCount > 0 THEN 1
                    ELSE 0
                END AS StarRating,

                -- Ingredient count
                (
                    SELECT COUNT(DISTINCT pi.IngredientID)
                    FROM product_ingredients pi
                    WHERE pi.ProductID = p.ProductID
                ) AS TotalIngredients,

                (
                    SELECT COUNT(DISTINCT pi.IngredientID)
                    FROM product_ingredients pi
                    LEFT JOIN (
                        SELECT IngredientID, SUM(StockQuantity) AS TotalStock
                        FROM inventory_batches
                        WHERE BatchStatus = 'Active'
                        GROUP BY IngredientID
                    ) ib ON pi.IngredientID = ib.IngredientID
                    WHERE pi.ProductID = p.ProductID
                    AND COALESCE(ib.TotalStock, 0) >= pi.QuantityUsed
                ) AS AvailableIngredients

            FROM products p
            WHERE p.Availability = 'Available'
            ORDER BY p.OrderCount DESC, p.Category ASC, p.ProductID ASC";

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

        // Format values
        $row['ProductID'] = intval($row['ProductID']);
        $row['Price'] = floatval($row['Price']);
        $row['OrderCount'] = intval($row['OrderCount']);
        $row['StarRating'] = intval($row['StarRating']);
        $row['PrepTime'] = intval($row['PrepTime']);

        $totalIngredients = intval($row['TotalIngredients']);
        $availableIngredients = intval($row['AvailableIngredients']);

        // ============================================================
        // INGREDIENT AVAILABILITY LOGIC
        // ============================================================

        if ($totalIngredients == 0) {
            $row['IngredientAvailable'] = true;
            $row['StockStatus'] = 'available';
            $row['AvailabilityReason'] = 'No ingredients required';

        } elseif ($availableIngredients == $totalIngredients) {
            $row['IngredientAvailable'] = true;
            $row['StockStatus'] = 'available';
            $row['AvailabilityReason'] = 'All ingredients in stock';

        } elseif ($availableIngredients > 0) {
            $row['IngredientAvailable'] = false;
            $row['StockStatus'] = 'low_stock';
            $row['AvailabilityReason'] = 'Low stock - some ingredients unavailable';

        } else {
            $row['IngredientAvailable'] = false;
            $row['StockStatus'] = 'out_of_stock';
            $row['AvailabilityReason'] = 'Out of stock';
        }

        // Remove internal fields
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
