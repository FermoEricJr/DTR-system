<?php
// upload.php (Modified for DTR-SYSTEM structure)
include '../include/dbcon.php';

// 1. Define the target directory (Up one level to DTR-SYSTEM, then into uploads)
// This ensures it goes to the folder on your laptop regardless of where the script is called from.
$target_dir = dirname(__DIR__) . '/uploads/';

// Create the folder if it doesn't exist
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// Check for the 'photo' data from your JavaScript canvas
if (isset($_POST["photo"]) && !empty($_POST["photo"])) {
    
    $idnumber = $_POST['idnumber'] ?? 'unknown';
    $photo_data = $_POST["photo"];

    // 2. Process the Base64 string
    // Standard camera captures look like: "data:image/jpeg;base64,/9j/4AAQ..."
    $image_parts = explode(";base64,", $photo_data);
    
    if (count($image_parts) == 2) {
        $image_type_aux = explode("image/", $image_parts[0]);
        $image_type = $image_type_aux[1]; // e.g., jpeg
        $image_base64 = base64_decode($image_parts[1]);

        // 3. Generate a unique name using ID and Timestamp
        $new_filename = 'selfie_' . $idnumber . '_' . time() . '.' . $image_type;
        $target_file = $target_dir . $new_filename;

        // 4. Save the file to your laptop's folder
        if (file_put_contents($target_file, $image_base64)) {
            // SUCCESS: The image is now physically on your laptop
            
            // To show this in the dashboard, we save the relative path
            $db_path = 'uploads/' . $new_filename;
            
            // Example of what you'd do next:
            // $stmt = $conn->prepare("INSERT INTO records (idnumber, photo_path) VALUES (?, ?)");
            // $stmt->bind_param("ss", $idnumber, $db_path);
            
            echo "The selfie was saved to your laptop as: " . $new_filename;
        } else {
            echo "Error: Could not write the file to the uploads folder. Check folder permissions.";
        }
    } else {
        echo "Error: Invalid image data received.";
    }
} else {
    echo "Error: No photo data received.";
}
?>