<?php
header("Content-Type: application/json");
error_reporting(0);

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "tabeya_system";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// ------------------------------------------------------------
// 1. Read Form Data
// ------------------------------------------------------------
$fullName = $_POST['full-name'];
$email = $_POST['email'];
$guests = $_POST['guests'];
$eventDate = $_POST['date'];
$eventTime = $_POST['time'];
$eventType = $_POST['event-type'];
$contactNumber = $_POST['phone'];

// Split name into first/last
$nameParts = explode(" ", $fullName, 2);
$firstName = $nameParts[0];
$lastName = isset($nameParts[1]) ? $nameParts[1] : "";

// ------------------------------------------------------------
// 2. Get CustomerID using email
// ------------------------------------------------------------
$sql = "SELECT CustomerID FROM customers WHERE Email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($customerID);
$stmt->fetch();
$stmt->close();

if (!$customerID) {
    echo json_encode(["status" => "error", "message" => "User not found in database"]);
    exit;
}

// ------------------------------------------------------------
// 3. Insert into reservations table
// ------------------------------------------------------------
$sql = "INSERT INTO reservations 
        (CustomerID, ReservationType, EventType, EventDate, EventTime, NumberOfGuests, ContactNumber, ServiceType)
        VALUES (?, 'Online', ?, ?, ?, ?, ?, 'Catering Only')";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isssis", 
    $customerID, 
    $eventType,
    $eventDate,
    $eventTime,
    $guests,
    $contactNumber
);

if (!$stmt->execute()) {
    echo json_encode(["status" => "error", "message" => "Failed to insert reservation"]);
    exit;
}

$reservationID = $stmt->insert_id;
$stmt->close();

// ------------------------------------------------------------
// 4. Insert into reservation_items (optional placeholder)
// ------------------------------------------------------------
$sql = "INSERT INTO reservation_items (ReservationID, ProductName, Quantity, UnitPrice, TotalPrice)
        VALUES (?, 'No Menu Selected Yet', 0, 0, 0)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reservationID);
$stmt->execute();
$stmt->close();

// ------------------------------------------------------------
// 5. Insert into reservation_payments
// ------------------------------------------------------------
$sql = "INSERT INTO reservation_payments 
        (ReservationID, PaymentMethod, PaymentStatus, AmountPaid, PaymentSource)
        VALUES (?, 'Cash', 'Pending', 0, 'Website')";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reservationID);

if (!$stmt->execute()) {
    echo json_encode(["status" => "error", "message" => "Failed to create payment record"]);
    exit;
}

$stmt->close();

// ------------------------------------------------------------
// SUCCESS
// ------------------------------------------------------------
echo json_encode([
    "status" => "success",
    "message" => "Reservation created successfully!",
    "reservationID" => $reservationID
]);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Information</title>
    <link rel="stylesheet" href="CSS/userInfoDesign.css">
    <style>
        .product-category-nav {
            background-color: rgb(188, 24, 35); 
            position: fixed; 
            top: 0;
            left: 0; 
            right: 0;
            display: flex; 
            justify-content: center; 
            padding: 15px; 
        }

        .product-category-nav a {
            color: white;
            margin: 0 15px;
            text-decoration: none;
            font-weight: bold;
        }
        .product-category-nav a:hover,
        .product-category-nav a.active {
            color: yellow;
        }
    </style>
</head>
<body>
    <!-- Product Selection Section -->
    <div class="background">
        <img src="Photo/Reservation.jpg" alt="Background">
        <h1 class="quote">YOUR<br>SATISFACTION<br>IS OUR<br>PASSION</h1>
        <div class="rectangle-panel">
            <h1 class="product-selection-label">CHOOSE YOUR PREFERRED PRODUCTS</h1>
            <div class="continue-section">
                <button class="continue-btn" onclick="showProductSelection()">Continue to Product Selection</button>
            </div>
            <div id="product-selection-panel" class="product-selection-panel">
                <div class="product-category-nav">
                    <a href="#" data-category="1-32" class="active">Bilao</a>
                    <a href="#" data-category="33-54">Platter</a>
                    <a href="#" data-category="55-70">Rice Meal</a>
                    <a href="#" data-category="71-78">Rice</a>
                    <a href="#" data-category="79-86">Spaghetti Meals</a>
                    <a href="#" data-category="87-90">Sandwiches</a>
                    <a href="#" data-category="91-94">Snacks</a>
                    <a href="#" data-category="95-98">Dessert</a>
                </div>
                <div id="product-groups" class="product-groups">
                    <!-- Products will be dynamically loaded here -->
                </div>
                
                <div class="panel-controls">
                    <div class="total-price-container">
                        Total Price: ₱<span id="total-price">0.00</span>
                    </div>
                    <div>
                        <button class="cancel-btn" onclick="closeProductSelection()">Cancel</button>
                        <button class="submit-btn" onclick="showConfirmationPopup()">Submit Orders</button>
                    </div>
                </div>
            </div>
        </div>
    </div>  

    <script src="products.js"></script>
   <!-- Confirmation Popup -->
    <div class="popup-overlay" id="confirmation-popup">
        <div class="popup-content">
            <p>Are you sure you want to submit your orders?
            Total Orders: ₱<span id="total-reservation"></span>
            </p>
            <button onclick="confirmReservation()">Yes</button>
            <button onclick="closePopup('confirmation-popup')">No</button>
        </div>
    </div>

    <!-- Success Popup -->
    <div class="popup-overlay" id="success-popup">
        <div class="popup-content">
            <p>Orders received! Our staff will contact you shortly to get your delivery address. Thank you for choosing TABEYA!</p>
            <button onclick="redirectToReservation()">Exit to Cater Reservation</button>
        </div>
    </div>

        <script>
        // Show the confirmation popup
        function showConfirmationPopup() {
            // Get the total price from the total-price span
            const totalPrice = document.getElementById('total-price').innerText;

            // Update the total reservation amount in the confirmation popup
            document.getElementById('total-reservation').innerText = totalPrice;

            // Show the confirmation popup
            document.getElementById('confirmation-popup').style.display = 'flex';
        }

        // Close any popup
        function closePopup(popupId) {
            document.getElementById(popupId).style.display = 'none';
        }

        // Confirm reservation and show success popup
        function confirmReservation() {
            closePopup('confirmation-popup');
            document.getElementById('success-popup').style.display = 'flex';
        }

        // Redirect to the Cater Reservation page
        function redirectToReservation() {
            window.location.href = "CaterReservation.html";
        }
    </script>
</body>
</html>