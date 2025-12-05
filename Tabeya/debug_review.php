<?php
/**
 * DEBUG: Test Review Insert - WORKING VERSION
 */

header('Content-Type: text/plain; charset=utf-8');

 require_once(__DIR__ . '/api/config/db_config.php');

echo "=== TESTING REVIEW INSERT ===\n\n";

$sql = "INSERT INTO customer_reviews (
            CustomerID, 
            OverallRating, 
            FoodTasteRating, 
            PortionSizeRating, 
            CustomerServiceRating, 
            AmbienceRating, 
            CleanlinessRating,
            FoodTasteComment, 
            PortionSizeComment, 
            CustomerServiceComment,
            AmbienceComment, 
            CleanlinessComment, 
            GeneralComment,
            Status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// ✅ CRITICAL: Assign to individual variables
$customerId = 7;  // Change to your customer ID
$overallRating = 4.5;
$foodRating = 5;
$portionRating = 4;
$serviceRating = 5;
$ambienceRating = 4;
$cleanlinessRating = 5;
$foodComment = 'Delicious food!';
$portionComment = 'Good portion size';
$serviceComment = 'Excellent service';
$ambienceComment = 'Nice atmosphere';
$cleanlinessComment = 'Very clean';
$generalComment = 'Highly recommend!';

echo "Test Data:\n";
echo "----------\n";
echo "CustomerID: $customerId\n";
echo "OverallRating: $overallRating\n";
echo "FoodTasteRating: $foodRating\n";
echo "PortionSizeRating: $portionRating\n";
echo "CustomerServiceRating: $serviceRating\n";
echo "AmbienceRating: $ambienceRating\n";
echo "CleanlinessRating: $cleanlinessRating\n";
echo "\n";

try {
    // ✅ FIXED: Type string must have 13 characters for 13 parameters
    // i d i i i i i s s s s s s = 13 types
    $stmt->bind_param(
        "idiiiiissssss",  // ← FIXED: Added extra 'i' (was 12, now 13)
        $customerId,         // 1. i
        $overallRating,      // 2. d
        $foodRating,         // 3. i
        $portionRating,      // 4. i
        $serviceRating,      // 5. i
        $ambienceRating,     // 6. i
        $cleanlinessRating,  // 7. i
        $foodComment,        // 8. s
        $portionComment,     // 9. s
        $serviceComment,     // 10. s
        $ambienceComment,    // 11. s
        $cleanlinessComment, // 12. s
        $generalComment      // 13. s
    );
    
    echo "✅ bind_param SUCCESS!\n\n";
    
    if ($stmt->execute()) {
        echo "✅ INSERT SUCCESS!\n";
        echo "Review ID: " . $stmt->insert_id . "\n\n";
        
        // Verify it was inserted
        $verifyId = $stmt->insert_id;
        $verify = $conn->query("SELECT * FROM customer_reviews WHERE ReviewID = $verifyId");
        if ($verify && $verify->num_rows > 0) {
            echo "✅ VERIFICATION: Review found in database!\n";
            $row = $verify->fetch_assoc();
            echo "CustomerID: {$row['CustomerID']}\n";
            echo "OverallRating: {$row['OverallRating']}\n";
            echo "Status: {$row['Status']}\n";
            echo "GeneralComment: {$row['GeneralComment']}\n";
        }
    } else {
        echo "❌ execute FAILED: " . $stmt->error . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

$stmt->close();
$conn->close();

echo "\n=== TEST COMPLETE ===\n";
?>