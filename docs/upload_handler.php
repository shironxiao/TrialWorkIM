<?php

// --- 1. CONFIGURATION & DATABASE CONNECTION ---
// Adjust these to match your XAMPP setup
 require_once(__DIR__ . '/api/config/db_config.php');

// The directory relative to this PHP script where files will be saved
// Ensure this folder exists: C:\xampp\htdocs\TrialWorkIM-main\Tabeya\uploads\products\
$target_dir = "uploads/products/";

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    // Stop execution if the database connection fails
    die("Database Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// --- 2. FILE UPLOAD CHECK ---
if (!isset($_FILES["product_image"]) || $_FILES["product_image"]["error"] !== UPLOAD_ERR_OK) {
    die("Error: No file uploaded or upload failed with code " . (isset($_FILES["product_image"]) ? $_FILES["product_image"]["error"] : 'N/A'));
}

// Get the product name from the form submission
$product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';

if (empty($product_name)) {
    die("Error: Product name is required.");
}

// Sanitize and create a unique file name
$file_name = basename($_FILES["product_image"]["name"]);
// Use a timestamp to ensure the file name is unique
$unique_filename = time() . '_' . uniqid() . '_' . $file_name;
$target_file = $target_dir . $unique_filename;


// --- 3. MOVE FILE TO PERMANENT LOCATION ---
if (!move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
    // If the file cannot be moved (often a permissions issue)
    die("Error moving uploaded file. Check folder permissions on: " . $target_dir);
}


// --- 4. DETERMINE RELATIVE PATH & STORE IN DB (The Core Logic) ---

// **STEP 2: Determine the Relative Path**
// This is the clean, portable path saved to the database.
$relative_path = $target_dir . $unique_filename;

// **STEP 3: Prepare the SQL Statement**
// IMPORTANT: Ensure your table is named 'products' and has columns 'product_name' and 'image_path'.
$sql = "INSERT INTO products (product_name, image_path) VALUES (?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    // If SQL preparation fails, log the error and remove the file that was just saved
    unlink($target_file);
    die("SQL Prepare Error: " . $conn->error);
}

// Bind parameters: 'ss' indicates two strings
$stmt->bind_param("ss", $product_name, $relative_path);

// **STEP 4: Execute the Insertion**
if ($stmt->execute()) {
    echo "<h1>✅ Success!</h1>";
    echo "<p>Product **{$product_name}** added successfully.</p>";
    echo "<p>Path saved in database: **{$relative_path}**</p>";
} else {
    // If DB insertion fails, remove the file to prevent orphans
    unlink($target_file);
    echo "<h1>❌ Database Error</h1>";
    echo "<p>The file was saved but failed to insert into the database. The file has been deleted.</p>";
    echo "<p>Error: " . $stmt->error . "</p>";
}

// Close the statement and connection
$stmt->close();
$conn->close();

?>