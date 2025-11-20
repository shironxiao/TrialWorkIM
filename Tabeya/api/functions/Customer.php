<?php
/**
 * Customer Class
 * Fixed to match your exact database structure
 */

class Customer {
    private $conn;
    private $table = 'customers';
    
    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }
    
    public function emailExists($email) {
        $sql = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE Email = ?";
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['count'] > 0;
    }
    
    public function register($firstName, $lastName, $email, $contactNumber, $passwordHash) {
        // ✅ FIXED: Match your exact database structure
        // Fields with defaults: FeedbackCount, TotalOrdersCount, ReservationCount, 
        //                       LastTransactionDate, LastLoginDate, CreatedDate, 
        //                       AccountStatus, SatisfactionRating, CustomerType
        
        $sql = "INSERT INTO " . $this->table . " 
               (FirstName, LastName, Email, PasswordHash, ContactNumber, 
                CustomerType, FeedbackCount, TotalOrdersCount, ReservationCount, 
                AccountStatus, SatisfactionRating, CreatedDate) 
               VALUES (?, ?, ?, ?, ?, 'Online', 0, 0, 0, 'Active', 0.00, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            return [
                'success' => false, 
                'message' => 'Prepare failed: ' . $this->conn->error
            ];
        }
        
        $stmt->bind_param("sssss", $firstName, $lastName, $email, $passwordHash, $contactNumber);
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return [
                'success' => false, 
                'message' => 'Execute failed: ' . $error
            ];
        }
        
        $customerId = $stmt->insert_id;
        $stmt->close();
        
        return [
            'success' => true,
            'customerId' => $customerId,
            'message' => 'Registration successful!'
        ];
    }
    
    public function getByEmail($email) {
        $sql = "SELECT * FROM " . $this->table . 
               " WHERE Email = ? AND AccountStatus = 'Active'";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            return null;
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $customer = $result->fetch_assoc();
        $stmt->close();
        
        return $customer;
    }
    
    public function updateLastLogin($customerId) {
        $sql = "UPDATE " . $this->table . " 
                SET LastLoginDate = NOW(), 
                    LastTransactionDate = NOW() 
                WHERE CustomerID = ?";
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("i", $customerId);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    public function logTransaction($customerId, $transactionType, $details) {
        // First check if customer_logs table exists
        $checkTable = $this->conn->query("SHOW TABLES LIKE 'customer_logs'");
        
        if (!$checkTable || $checkTable->num_rows === 0) {
            // Table doesn't exist, skip logging (or create it)
            return true;
        }
        
        $sql = "INSERT INTO customer_logs (CustomerID, TransactionType, Details, LogDate) 
                VALUES (?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("iss", $customerId, $transactionType, $details);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
}

?>