<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $user_id = (int) $_SESSION['user_id'];

    $item_name = $_POST['item_name'];
    $category = $_POST['category'];
    $description = trim($_POST['description'] ?? '');
    $lost_date = $_POST['lost_date'];
    $lost_location = $_POST['lost_location'];
    $contact_information = $_POST['contact_information'];
    $secret_information = $_POST['secret_information'] ?? '';

    // DESCRIPTION VALIDATION

    if ($description === '') {
        echo "<script>
        alert('Description is required!');
        window.history.back();
        </script>";
        exit();
    }

    // ==============================
    // OPTIONAL IMAGE UPLOAD
    // ==============================

    $item_image = NULL;

    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {

        $upload_folder = "uploads/";

        // Create uploads folder if it doesn't exist
        if (!is_dir($upload_folder)) {
            @mkdir($upload_folder, 0755, true);
        }

        $tmp_name = $_FILES['item_image']['tmp_name'];

        // Max 5 MB
        if ($_FILES['item_image']['size'] > 5 * 1024 * 1024) {
            die("Image must be 5 MB or smaller.");
        }

        // Check the real file type, not just the file name
        $allowed_types = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        $finfo     = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($tmp_name);

        if (!isset($allowed_types[$mime_type]) || @getimagesize($tmp_name) === false) {
            die("Only JPG, JPEG, PNG and WEBP images are allowed.");
        }

        // Unique file name; extension comes from the detected type
        $new_file_name = time() . "_" . bin2hex(random_bytes(8)) . "." . $allowed_types[$mime_type];

        $destination = $upload_folder . $new_file_name;

        // Compress and resize image to prevent 4.5MB serverless payload limit
        $compressed = compressAndResizeImage($tmp_name, 800, 75);

        if (@file_put_contents($destination, $compressed) !== false) {
            $item_image = $destination;
        } else {
            // On serverless clouds (like Vercel) where local disk is read-only,
            // store the compressed image as a Data URI directly in the database (~50-90KB)
            if (!empty($compressed)) {
                $item_image = 'data:image/jpeg;base64,' . base64_encode($compressed);
            }
        }
    }


    // ==============================
    // INSERT LOST ITEM
    // ==============================

    $sql = "INSERT INTO lost_items
    (user_id, item_name, category, lost_date, lost_location,
     contact_information, secret_information, description, item_image)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Database Error.");
    }

    $stmt->bind_param(
        "issssssss",
        $user_id, $item_name, $category, $lost_date, $lost_location,
        $contact_information, $secret_information, $description, $item_image
    );

    if ($stmt->execute()) {

        // ==============================
        // ADMIN NOTIFICATION
        // ==============================

        $title = "New Lost Item Post";

        $message = "A new lost item is waiting for approval.";

        $notification_sql = "INSERT INTO notifications
        (user_id, title, message, receiver, is_read)
        VALUES (?, ?, ?, 'admin', 0)";

        $n_stmt = $conn->prepare($notification_sql);
        $n_stmt->bind_param("iss", $user_id, $title, $message);
        $n_stmt->execute();
        $n_stmt->close();

        echo "<script>
        alert('Lost Item Added Successfully!');
        window.location.href='dashboard.php';
        </script>";

        exit();

    } else {

        error_log("Add lost item failed: " . $stmt->error);
        echo "Could not save the item. Please try again.";

    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Lost Item - Campus Lost &amp; Found</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f4f7f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1e293b;
        }

        .clf-form-wrapper {
            max-width: 650px;
            margin: 35px auto 60px;
            padding: 0 16px;
        }

        .clf-form-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 35px 32px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 30px rgba(0, 56, 68, 0.08);
        }

        .clf-form-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .clf-form-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .clf-form-header h1 {
            margin: 0 0 8px;
            color: #003844;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .clf-form-header p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .clf-form-body {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .clf-field-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .clf-field-group label {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .clf-field-group label .clf-req {
            color: #dc2626;
            font-weight: 800;
        }

        .clf-field-group label .clf-opt {
            color: #94a3b8;
            font-size: 11px;
            font-weight: 500;
        }

        .clf-input,
        .clf-select,
        .clf-textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            color: #0f172a;
            background: #ffffff;
            outline: none;
            transition: all 0.2s ease;
            box-sizing: border-box;
            font-family: inherit;
        }

        .clf-input:focus,
        .clf-select:focus,
        .clf-textarea:focus {
            border-color: #0fa4af;
            box-shadow: 0 0 0 3px rgba(15, 164, 175, 0.15);
            background: #ffffff;
        }

        .clf-textarea {
            resize: vertical;
            min-height: 85px;
        }

        .clf-file-box {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            background: #f8fafc;
            transition: 0.2s ease;
        }

        .clf-file-box:hover {
            border-color: #0fa4af;
            background: #f0fdfa;
        }

        .clf-file-input {
            width: 100%;
            font-size: 13px;
            color: #475569;
        }

        .clf-file-hint {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #64748b;
        }

        .clf-submit-btn {
            margin-top: 10px;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 11px;
            background: linear-gradient(135deg, #003844, #0fa4af);
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 56, 68, 0.2);
            transition: all 0.2s ease;
        }

        .clf-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(0, 56, 68, 0.28);
        }

        .clf-submit-btn:active {
            transform: translateY(0);
        }

        .clf-back-link {
            text-align: center;
            margin-top: 18px;
        }

        .clf-back-link a {
            color: #0fa4af;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 700;
            transition: 0.2s;
        }

        .clf-back-link a:hover {
            color: #003844;
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .clf-form-card {
                padding: 24px 18px;
                border-radius: 14px;
            }
            .clf-form-header h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>

<?php include "navbar.php"; ?>

<div class="clf-form-wrapper">
    <div class="clf-form-card">
        <div class="clf-form-header">
            <span class="clf-form-badge">Lost Item</span>
            <h1>Add Lost Item</h1>
            <p>Provide detailed item information to help matching and fast recovery.</p>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="clf-form-body">
            <!-- Item Name -->
            <div class="clf-field-group">
                <label>Item Name <span class="clf-req">*</span></label>
                <input type="text" name="item_name" class="clf-input" placeholder="e.g. Black Leather Wallet, Student ID Card..." required>
            </div>

            <!-- Category -->
            <div class="clf-field-group">
                <label>Category <span class="clf-req">*</span></label>
                <select name="category" class="clf-select" required>
                    <option value="">Select Category</option>
                    <option value="Electronics">Electronics</option>
                    <option value="Documents">Documents</option>
                    <option value="Accessories">Accessories</option>
                    <option value="Books or Stationery">Books or Stationery</option>
                    <option value="Others">Others</option>
                </select>
            </div>

            <!-- Description -->
            <div class="clf-field-group">
                <label>Description <span class="clf-req">*</span></label>
                <textarea name="description" rows="4" class="clf-textarea" placeholder="Describe the item's brand, color, distinguishing marks, etc." required></textarea>
            </div>

            <!-- Lost Date -->
            <div class="clf-field-group">
                <label>Lost Date <span class="clf-req">*</span></label>
                <input type="date" name="lost_date" class="clf-input" required>
            </div>

            <!-- Lost Location -->
            <div class="clf-field-group">
                <label>Lost Location <span class="clf-req">*</span></label>
                <input type="text" name="lost_location" class="clf-input" placeholder="e.g. Central Library 2nd Floor, Cafeteria, Room 302..." required>
            </div>

            <!-- Contact Information -->
            <div class="clf-field-group">
                <label>Contact Information <span class="clf-req">*</span></label>
                <input type="text" name="contact_information" class="clf-input" placeholder="Your phone number, room, or WhatsApp" required>
            </div>

            <!-- Secret Information -->
            <div class="clf-field-group">
                <label>Secret Verification Information <span class="clf-opt">(Only visible during claim approval)</span></label>
                <textarea name="secret_information" rows="3" class="clf-textarea" placeholder="Private proof of ownership (e.g. ID card number inside, engraving, specific lock screen wallpaper, hidden scratch)"></textarea>
            </div>

            <!-- Optional Image Upload -->
            <div class="clf-field-group">
                <label>Item Picture <span class="clf-opt">(Optional)</span></label>
                <div class="clf-file-box">
                    <input type="file" name="item_image" class="clf-file-input" accept="image/jpeg,image/png,image/webp">
                    <span class="clf-file-hint">Upload JPG, PNG or WEBP (Max 5MB). Photo helps identify the item faster.</span>
                </div>
            </div>

            <button type="submit" class="clf-submit-btn">Submit Lost Item</button>

            <div class="clf-back-link">
                <a href="dashboard.php">← Back to Dashboard</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
