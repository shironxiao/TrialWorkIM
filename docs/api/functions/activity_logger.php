<?php
/**
 * Activity Logger Utility
 * Save as: api/functions/activity_logger.php
 * 
 * Centralized function for logging user activities
 */

/**
 * Log user activity to activity_logs table
 * 
 * @param mysqli $conn Database connection
 * @param string $userType 'Admin', 'Staff', or 'Customer'
 * @param int $userId User ID from respective table
 * @param string $username Username or name of user
 * @param string $action Action performed (e.g., "Order Placed", "Reservation Created")
 * @param string $actionCategory Category from enum
 * @param string $description Detailed description
 * @param string $sourceSystem 'POS', 'Website', or 'Admin Panel'
 * @param string|null $referenceID Order ID, Reservation ID, etc.
 * @param string|null $referenceTable Table name being affected
 * @param string|null $oldValue Previous value (for updates)
 * @param string|null $newValue New value (for updates)
 * @param string $status 'Success', 'Failed', or 'Warning'
 * @return bool Success status
 */
function logActivity(
    $conn,
    $userType,
    $userId,
    $username,
    $action,
    $actionCategory,
    $description = null,
    $sourceSystem = 'Website',
    $referenceID = null,
    $referenceTable = null,
    $oldValue = null,
    $newValue = null,
    $status = 'Success'
) {
    try {
        // Get session ID if available
        $sessionID = session_id() ?: null;

        $sql = "INSERT INTO activity_logs (
            UserType, UserID, Username, Action, ActionCategory,
            Description, SourceSystem, ReferenceID, ReferenceTable,
            OldValue, NewValue, Status, SessionID
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            error_log("Activity Log Error: Failed to prepare statement - " . $conn->error);
            return false;
        }

        $stmt->bind_param(
            "sisssssssssss",
            $userType,
            $userId,
            $username,
            $action,
            $actionCategory,
            $description,
            $sourceSystem,
            $referenceID,
            $referenceTable,
            $oldValue,
            $newValue,
            $status,
            $sessionID
        );

        $result = $stmt->execute();

        if (!$result) {
            error_log("Activity Log Error: Failed to execute - " . $stmt->error);
        }

        $stmt->close();
        return $result;

    } catch (Throwable $e) {
        error_log("Activity Log Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Convenience function for logging customer order placement
 */
function logCustomerOrderPlaced($conn, $customerId, $customerName, $orderId, $totalAmount, $orderType)
{
    return logActivity(
        $conn,
        'Customer',
        $customerId,
        $customerName,
        'Order Placed',
        'Order',
        "Customer placed a $orderType order with total amount: ₱" . number_format($totalAmount, 2),
        'Website',
        $orderId,
        'orders',
        null,
        json_encode([
            'order_id' => $orderId,
            'total_amount' => $totalAmount,
            'order_type' => $orderType
        ]),
        'Success'
    );
}

/**
 * Convenience function for logging customer reservation
 */
function logCustomerReservation($conn, $customerId, $customerName, $reservationId, $eventType, $eventDate, $guests)
{
    return logActivity(
        $conn,
        'Customer',
        $customerId,
        $customerName,
        'Reservation Created',
        'Reservation',
        "Customer created a $eventType reservation for $guests guests on $eventDate",
        'Website',
        $reservationId,
        'reservations',
        null,
        json_encode([
            'reservation_id' => $reservationId,
            'event_type' => $eventType,
            'event_date' => $eventDate,
            'guests' => $guests
        ]),
        'Success'
    );
}

/**
 * Convenience function for logging customer review submission
 */
function logCustomerReview($conn, $customerId, $customerName, $feedbackId, $feedbackType, $overallRating, $isAnonymous)
{
    $displayName = $isAnonymous ? 'Anonymous' : $customerName;

    return logActivity(
        $conn,
        'Customer',
        $customerId,
        $customerName,
        'Review Submitted',
        'System',
        "Customer submitted a $feedbackType review with rating: $overallRating/5" . ($isAnonymous ? ' (Anonymous)' : ''),
        'Website',
        $feedbackId,
        'customer_feedback',
        null,
        json_encode([
            'feedback_id' => $feedbackId,
            'feedback_type' => $feedbackType,
            'rating' => $overallRating,
            'anonymous' => $isAnonymous
        ]),
        'Success'
    );
}

/**
 * Convenience function for logging customer payment
 */
function logCustomerPayment($conn, $customerId, $customerName, $paymentId, $paymentMethod, $amount, $referenceType, $referenceId)
{
    return logActivity(
        $conn,
        'Customer',
        $customerId,
        $customerName,
        'Payment Submitted',
        'Payment',
        "Customer submitted $paymentMethod payment of ₱" . number_format($amount, 2) . " for $referenceType #$referenceId",
        'Website',
        $paymentId,
        'payments',
        null,
        json_encode([
            'payment_id' => $paymentId,
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId
        ]),
        'Success'
    );
}

/**
 * Convenience function for logging failed actions
 */
function logFailedAction($conn, $userType, $userId, $username, $action, $actionCategory, $errorMessage)
{
    return logActivity(
        $conn,
        $userType,
        $userId,
        $username,
        $action,
        $actionCategory,
        "Failed: $errorMessage",
        'Website',
        null,
        null,
        null,
        null,
        'Failed'
    );
}
?>