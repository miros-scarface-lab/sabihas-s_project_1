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
    $found_date = $_POST['found_date'];
    $found_location = $_POST['found_location'];
    $finder_contact_information = $_POST['finder_contact_information'];


    // DESCRIPTION VALIDATION

    if ($description === '') {
        echo "<script>
        alert('Description is required!');
        window.history.back();
        </script>";
        exit();
    }



    $sql = "INSERT INTO found_items
    (user_id, item_name, category, found_date, found_location, finder_contact_information, description)
    VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Database Error.");
    }

    $stmt->bind_param(
        "issssss",
        $user_id, $item_name, $category, $found_date, $found_location,
        $finder_contact_information, $description
    );

    if ($stmt->execute()) {



        // Admin Notification

        $title = "New Found Item Post";

        $message = "A new found item is waiting for approval.";


        $notification_sql = "INSERT INTO notifications
        (user_id, title, message, receiver, is_read)
        VALUES (?, ?, ?, 'admin', 0)";

        $n_stmt = $conn->prepare($notification_sql);
        $n_stmt->bind_param("iss", $user_id, $title, $message);
        $n_stmt->execute();
        $n_stmt->close();

        echo "<script>
        alert('Found Item Added Successfully!');
        window.location.href='dashboard.php';
        </script>";

        exit();



    } else {


        error_log("Add found item failed: " . $stmt->error);
        echo "Could not save the item. Please try again.";


    }


}

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Found Item - Campus Lost &amp; Found</title>
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
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
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
            <span class="clf-form-badge">Found Item</span>
            <h1>Add Found Item</h1>
            <p>Thank you for your honesty! Enter details to locate the rightful owner.</p>
        </div>

        <form action="" method="POST" class="clf-form-body">
            <!-- Item Name -->
            <div class="clf-field-group">
                <label>Item Name <span class="clf-req">*</span></label>
                <input type="text" name="item_name" class="clf-input" placeholder="e.g. Set of Keys, Scientific Calculator, Blue Water Bottle..." required>
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
                <textarea name="description" rows="4" class="clf-textarea" placeholder="Describe the item's appearance, color, brand, condition..." required></textarea>
            </div>

            <!-- Found Date -->
            <div class="clf-field-group">
                <label>Found Date <span class="clf-req">*</span></label>
                <input type="date" name="found_date" class="clf-input" required>
            </div>

            <!-- Found Location -->
            <div class="clf-field-group">
                <label>Found Location <span class="clf-req">*</span></label>
                <input type="text" name="found_location" class="clf-input" placeholder="e.g. Science Building Lab 101, Bench near field..." required>
            </div>

            <!-- Finder Contact Information -->
            <div class="clf-field-group">
                <label>Your Contact Information <span class="clf-req">*</span></label>
                <input type="text" name="finder_contact_information" class="clf-input" placeholder="Your phone number, room number, or email" required>
            </div>

            <button type="submit" class="clf-submit-btn">Submit Found Item</button>

            <div class="clf-back-link">
                <a href="dashboard.php">← Back to Dashboard</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
