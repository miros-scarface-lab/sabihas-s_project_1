<?php

session_start();
include "db.php";


/* ==============================
   LOGIN CHECK
============================== */

if (!isset($_SESSION['user_id'])) {

    header("Location: login.html");
    exit();

}

$user_id = (int) $_SESSION['user_id'];


/* ==============================
   GET MATCHES
============================== */

$sql = "
    SELECT
        m.match_id,
        m.lost_id,
        m.found_id,
        m.match_percentage,
        m.status,
        m.created_at,
        m.finder_status,
        m.owner_status,
        m.claim_status,

        l.user_id AS lost_user_id,
        l.item_name AS lost_item_name,
        l.category AS lost_category,
        l.description AS lost_description,
        l.lost_date,
        l.lost_location,
        l.item_image AS lost_item_image,

        f.user_id AS found_user_id,
        f.item_name AS found_item_name,
        f.category AS found_category,
        f.description AS found_description,
        f.found_date,
        f.found_location

    FROM matches m

    INNER JOIN lost_items l
        ON m.lost_id = l.lost_id

    INNER JOIN found_items f
        ON m.found_id = f.found_id

    WHERE m.match_percentage >= 80

    AND (
        l.user_id = ?
        OR
        f.user_id = ?
    )

    ORDER BY
        m.created_at DESC,
        m.match_id DESC
";


$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "Database Error: "
        . htmlspecialchars($conn->error)
    );

}

$stmt->bind_param(
    "ii",
    $user_id,
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>My Matches - Campus Lost & Found</title>


<style>

/* ==============================
   GLOBAL
============================== */

* {
    box-sizing: border-box;
}

body {

    margin: 0;
    padding: 0;

    font-family: Arial, sans-serif;

    background: #f4f7f9;

    color: #334155;

}


/* ==============================
   MAIN CONTAINER
============================== */

.matches-container {

    width: 92%;

    max-width: 1100px;

    margin: 35px auto 50px;

}


/* ==============================
   HEADER
============================== */

.matches-header {

    background: white;

    padding: 25px 20px;

    border-radius: 16px;

    text-align: center;

    margin-bottom: 25px;

    border: 1px solid #e5e7eb;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.05);

}


.matches-header h1 {

    margin: 0;

    color: #003844;

    font-size: 30px;

}


.matches-header p {

    margin: 8px 0 0;

    color: #64748b;

    font-size: 14px;

}


/* ==============================
   MATCH CARD
============================== */

.match-card {

    background: white;

    border-radius: 16px;

    margin-bottom: 22px;

    padding: 22px;

    border: 1px solid #e2e8f0;

    box-shadow:
        0 4px 18px rgba(0,0,0,0.06);

}


/* ==============================
   MATCH HEADER
============================== */

.match-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 20px;

    padding-bottom: 15px;

    border-bottom: 1px solid #edf2f7;

}


.match-id {

    color: #64748b;

    font-size: 13px;

}


.match-id strong {

    color: #334155;

}


.match-percentage {

    padding: 7px 13px;

    border-radius: 20px;

    background: #ecfdf5;

    color: #047857;

    font-weight: bold;

    font-size: 14px;

    border: 1px solid #a7f3d0;

}


/* ==============================
   ROLE BADGE
============================== */

.role-badge {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 15px;

    font-size: 11px;

    font-weight: bold;

    margin-left: 7px;

}


.owner-role {

    background: #fff1f2;

    color: #be123c;

}


.finder-role {

    background: #ecfdf5;

    color: #15803d;

}


/* ==============================
   ITEMS GRID
============================== */

.items-grid {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 18px;

    margin-bottom: 20px;

}


/* ==============================
   ITEM BOX
============================== */

.item-box {

    padding: 18px;

    border-radius: 13px;

    background: #f8fafc;

    border: 1px solid #e2e8f0;

}


.lost-box {

    border-top: 3px solid #ef4444;

}


.found-box {

    border-top: 3px solid #10b981;

}


/* ==============================
   ITEM LABEL
============================== */

.item-label {

    font-size: 11px;

    font-weight: bold;

    letter-spacing: 0.5px;

    margin-bottom: 7px;

}


.lost-label {

    color: #dc2626;

}


.found-label {

    color: #059669;

}


/* ==============================
   ITEM TITLE
============================== */

.item-box h2 {

    margin: 0 0 12px;

    color: #003844;

    font-size: 19px;

}


/* ==============================
   LOST ITEM IMAGE
============================== */

.lost-item-image {

    width: 150px;

    height: 120px;

    margin: 0 auto 15px;

    border-radius: 10px;

    overflow: hidden;

    background: #f1f5f9;

    border: 1px solid #e2e8f0;

}


.lost-item-image img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    display: block;

}


/* ==============================
   FALLBACK IMAGE
============================== */

.fallback-image {

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    gap: 5px;

    color: #94a3b8;

}


.fallback-image span {

    font-size: 35px;

}


.fallback-image small {

    font-size: 11px;

}


/* ==============================
   DETAILS
============================== */

.detail {

    margin: 8px 0;

    font-size: 13px;

    color: #64748b;

    line-height: 1.5;

}


.detail strong {

    color: #334155;

}


.description {

    background: white;

    padding: 9px;

    margin-top: 5px;

    border-radius: 7px;

    border: 1px solid #e5e7eb;

    color: #64748b;

}


/* ==============================
   STATUS AREA
============================== */

.status-area {

    display: flex;

    justify-content: space-between;

    align-items: center;

    flex-wrap: wrap;

    gap: 15px;

    padding-top: 16px;

    border-top: 1px solid #edf2f7;

}


.claim-status-title {

    font-size: 12px;

    color: #64748b;

    margin-bottom: 5px;

}


/* ==============================
   STATUS BADGE
============================== */

.status-badge {

    display: inline-block;

    padding: 6px 12px;

    border-radius: 18px;

    font-size: 12px;

    font-weight: bold;

}


.pending {

    background: #fff7ed;

    color: #c2410c;

}


.approved {

    background: #ecfdf5;

    color: #15803d;

}


.rejected {

    background: #fff1f2;

    color: #be123c;

}


.received {

    background: #eff6ff;

    color: #2563eb;

}


.not-claimed {

    background: #f1f5f9;

    color: #64748b;

}


/* ==============================
   ACTION AREA
============================== */

.action-area {

    display: flex;

    align-items: center;

    gap: 9px;

    flex-wrap: wrap;

}


.action-title {

    font-size: 12px;

    color: #64748b;

}


/* ==============================
   BUTTON
============================== */

.action-button {

    border: none;

    padding: 9px 15px;

    border-radius: 8px;

    color: white;

    text-decoration: none;

    font-weight: bold;

    font-size: 12px;

    cursor: pointer;

    transition: 0.2s;

}


.action-button:hover {

    transform: translateY(-1px);

}


/* CLAIM */

.claim-button {

    background: #0d9488;

}


.claim-button:hover {

    background: #0f766e;

}


/* RECEIVE */

.receive-button {

    background: #2563eb;

}


.receive-button:hover {

    background: #1d4ed8;

}


/* GIVE BACK */

.give-button {

    background: #16a34a;

}


.give-button:hover {

    background: #15803d;

}


/* VIEW CLAIM */

.view-claim-button {

    background: #6366f1;

}


.view-claim-button:hover {

    background: #4f46e5;

}


/* APPROVE */

.approve-button {

    background: #16a34a;

}


.approve-button:hover {

    background: #15803d;

}


/* ITEM DETAIL BUTTON */

.item-detail-btn {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    font-size: 11px;

    font-weight: 600;

    color: #475569;

    background: #f1f5f9;

    border: 1px solid #e2e8f0;

    padding: 6px 12px;

    border-radius: 6px;

    text-decoration: none;

    transition: 0.2s;

}


.item-detail-btn:hover {

    background: #e2e8f0;

    color: #0f172a;

}


/* RETURNED BADGE */

.returned-badge {

    background: #dcfce7 !important;

    color: #15803d !important;

    border: 1px solid #bbf7d0 !important;

    font-weight: 700 !important;

}


/* WAITING */

.waiting-text {

    color: #64748b;

    font-weight: bold;

    font-size: 12px;

}


/* ==============================
   NO MATCH
============================== */

.no-match {

    background: white;

    padding: 45px 20px;

    text-align: center;

    border-radius: 15px;

    border: 1px solid #e2e8f0;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.05);

}


.no-match h2 {

    color: #003844;

}


/* ==============================
   BACK BUTTON
============================== */

.back-link {

    display: inline-block;

    margin-top: 8px;

    padding: 10px 18px;

    background: #003844;

    color: white;

    border-radius: 8px;

    text-decoration: none;

    font-weight: bold;

    font-size: 13px;

}


.back-link:hover {

    background: #005263;

}


/* ==============================
   MOBILE
============================== */

@media (max-width: 768px) {

    .matches-container {

        width: 94%;

        margin: 20px auto 35px;

    }


    .matches-header {

        padding: 20px 15px;

    }


    .matches-header h1 {

        font-size: 25px;

    }


    .match-card {

        padding: 16px;

    }


    .items-grid {

        grid-template-columns: 1fr;

    }


    .match-top {

        flex-direction: column;

        align-items: flex-start;

    }


    .match-percentage {

        align-self: flex-start;

    }


    .lost-item-image {

        width: 140px;

        height: 110px;

    }


    .status-area {

        flex-direction: column;

        align-items: flex-start;

    }


    .action-area {

        width: 100%;

    }


    .action-button {

        width: 100%;

        text-align: center;

    }

}

</style>

</head>


<body>

<?php include "navbar.php"; ?>

<div class="matches-container">


<!-- ==============================
     HEADER
============================== -->

<div class="matches-header">

    <h1>
        🔗 My Matches
    </h1>

    <p>
        Possible matches found between
        lost and found items
    </p>

</div>


<?php if ($result->num_rows > 0): ?>


    <?php while ($row = $result->fetch_assoc()): ?>


        <?php

        /* ==============================
           USER ROLE
        ============================== */

        $is_owner =
            (
                (int)$row['lost_user_id']
                === $user_id
            );


        $is_finder =
            (
                (int)$row['found_user_id']
                === $user_id
            );


        /* ==============================
           CLAIM STATUS
        ============================== */

        $claim_status =
            trim(
                $row['claim_status'] ?? ''
            );


        if ($claim_status === '' || strtolower($claim_status) === 'none') {

            $claim_status =
                'Not Claimed';

        }

        $is_returned = (
            strtolower(trim((string)($row['status'] ?? ''))) === 'returned' ||
            (
                strtolower(trim((string)($row['finder_status'] ?? ''))) === 'give back' &&
                strtolower(trim((string)($row['owner_status'] ?? ''))) === 'received'
            )
        );


        /* ==============================
           PERCENTAGE
        ============================== */

        $percentage =
            (float)$row['match_percentage'];

        ?>


        <!-- ==============================
             MATCH CARD
        ============================== -->

        <div class="match-card">


            <!-- ==========================
                 TOP
            ========================== -->

            <div class="match-top">


                <div class="match-id">

                    Match ID:

                    <strong>

                        <?php
                        echo
                            (int)$row['match_id'];
                        ?>

                    </strong>


                    <?php if ($is_owner): ?>


                        <span
                            class="role-badge owner-role"
                        >

                            👤 Owner

                        </span>


                    <?php elseif ($is_finder): ?>


                        <span
                            class="role-badge finder-role"
                        >

                            🔎 Finder

                        </span>


                    <?php endif; ?>

                </div>


                <div class="match-percentage">

                    ⭐

                    <?php

                    echo number_format(
                        $percentage,
                        2
                    );

                    ?>% Match

                </div>


            </div>


            <!-- ==============================
                 ITEMS
            ============================== -->

            <div class="items-grid">


                <!-- ==========================
                     LOST ITEM
                ========================== -->

                <div class="item-box lost-box">


                    <div
                        class="item-label lost-label"
                    >

                        🔴 LOST ITEM

                    </div>


                    <h2>

                        <?php

                        echo htmlspecialchars((string) ($row['lost_item_name'] ?? ''));

                        ?>

                    </h2>


                    <!-- LOST IMAGE -->

                    <?php

                    $lost_image =
                        trim(
                            $row['lost_item_image']
                            ?? ''
                        );

                    $image_file = '';

                    if ($lost_image !== '') {

                        $image_file =
                            __DIR__
                            . '/'
                            . ltrim(
                                $lost_image,
                                '/'
                            );

                    }

                    ?>


                    <?php 
                    $has_lost_img = ($lost_image !== '' && (file_exists($image_file) || str_starts_with($lost_image, 'data:') || str_starts_with($lost_image, 'image.php')));
                    $img_display_src = (str_starts_with($lost_image, 'data:') || !file_exists($image_file)) ? 'image.php?type=lost&id=' . (int)$row['lost_id'] : $lost_image;
                    ?>

                    <?php if ($has_lost_img): ?>

                        <div
                            class="lost-item-image"
                        >

                            <img
                                src="<?php
                                echo htmlspecialchars((string) $img_display_src);
                                ?>"
                                alt="<?php
                                echo htmlspecialchars((string) ($row['lost_item_name'] ?? ''));
                                ?>"
                            >

                        </div>


                    <?php else: ?>


                        <div
                            class="lost-item-image
                                   fallback-image"
                        >

                            <span>📦</span>

                            <small>
                                No Image Available
                            </small>

                        </div>


                    <?php endif; ?>


                    <div class="detail">

                        <strong>
                            Category:
                        </strong>

                        <?php

                        echo htmlspecialchars((string) ($row['lost_category'] ?? ''));

                        ?>

                    </div>


                    <div class="detail">

                        <strong>
                            Lost Date:
                        </strong>

                        <?php

                        echo htmlspecialchars((string) ($row['lost_date'] ?? ''));

                        ?>

                    </div>


                    <div class="detail">

                        <strong>
                            Location:
                        </strong>

                        <?php

                        echo htmlspecialchars((string) ($row['lost_location'] ?? ''));

                        ?>

                    </div>


                    <div class="detail">

                        <strong>
                            Description:
                        </strong>


                        <div class="description">

                            <?php

                            echo htmlspecialchars(
                                $row['lost_description']
                                ?? ''
                            );

                            ?>

                        </div>

                    </div>

                    <div style="margin-top: 14px;">
                        <a href="item-details.php?type=lost&id=<?php echo (int)$row['lost_id']; ?>" class="item-detail-btn">
                            🔍 View Details
                        </a>
                    </div>

                </div>


                <!-- ==========================
                     FOUND ITEM
                ========================== -->

                <div class="item-box found-box">


                    <div
                        class="item-label found-label"
                    >

                        🟢 FOUND ITEM

                    </div>


                    <h2>

                        <?php

                        echo htmlspecialchars((string) ($row['found_item_name'] ?? ''));

                        ?>

                    </h2>


                    <div class="detail">

                        <strong>
                            Category:
                        </strong>

                        <?php

                        echo htmlspecialchars((string) ($row['found_category'] ?? ''));

                        ?>

                    </div>


                    <div class="detail">

                        <strong>
                            Found Date:
                        </strong>

                        <?php

                        echo htmlspecialchars((string) ($row['found_date'] ?? ''));

                        ?>

                    </div>


                    <div class="detail">

                        <strong>
                            Location:
                        </strong>

                        <?php

                        echo htmlspecialchars((string) ($row['found_location'] ?? ''));

                        ?>

                    </div>


                    <div class="detail">

                        <strong>
                            Description:
                        </strong>


                        <div class="description">

                            <?php

                            echo htmlspecialchars(
                                $row['found_description']
                                ?? ''
                            );

                            ?>

                        </div>

                    </div>

                    <div style="margin-top: 14px;">
                        <a href="item-details.php?type=found&id=<?php echo (int)$row['found_id']; ?>" class="item-detail-btn">
                            🔍 View Details
                        </a>
                    </div>

                </div>


            </div>


            <!-- ==============================
                 STATUS + ACTION
            ============================== -->

            <div class="status-area">


                <!-- CLAIM STATUS -->

                <div>

                    <div
                        class="claim-status-title"
                    >

                        Claim Status

                    </div>


                    <?php if ($is_returned): ?>

                        <span
                            class="status-badge returned-badge"
                        >

                            🎉 Successfully Returned

                        </span>

                    <?php elseif (
                        $claim_status
                        === 'Not Claimed'
                    ): ?>


                        <span
                            class="status-badge
                                   not-claimed"
                        >

                            ○ Not Claimed

                        </span>


                    <?php elseif (
                        $claim_status
                        === 'Pending'
                    ): ?>


                        <span
                            class="status-badge pending"
                        >

                            ⏳ Claim Pending

                        </span>


                    <?php elseif (
                        $claim_status
                        === 'Approved'
                    ): ?>


                        <span
                            class="status-badge approved"
                        >

                            ✓ Claim Approved

                        </span>


                    <?php elseif (
                        $claim_status
                        === 'Rejected'
                    ): ?>


                        <span
                            class="status-badge rejected"
                        >

                            ✗ Claim Rejected

                        </span>


                    <?php else: ?>


                        <span
                            class="status-badge pending"
                        >

                            <?php

                            echo htmlspecialchars((string) ($claim_status ?? ''));

                            ?>

                        </span>


                    <?php endif; ?>


                </div>


                <!-- ACTION -->

                <div class="action-area">


                    <span class="action-title">

                        Action:

                    </span>


                    <!-- OWNER -->

                    <?php if ($is_owner): ?>

                        <?php if ($is_returned): ?>

                            <span
                                class="status-badge returned-badge"
                            >

                                🎉 Successfully Returned

                            </span>

                        <?php elseif (
                            $claim_status
                            === 'Not Claimed'
                        ): ?>


                            <form
                                action="match-action.php"
                                method="POST"
                            >

                                <input
                                    type="hidden"
                                    name="match_id"
                                    value="<?php
                                    echo
                                        (int)$row['match_id'];
                                    ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="claim"
                                >

                                <button
                                    type="submit"
                                    class="action-button
                                           claim-button"
                                >

                                    📢 Claim Item

                                </button>

                            </form>


                        <?php elseif (
                            $claim_status
                            === 'Pending'
                        ): ?>


                            <span
                                class="waiting-text"
                            >

                                ⏳ Claim Request Sent

                            </span>


                        <?php elseif (
                            $claim_status
                            === 'Rejected'
                        ): ?>


                            <span
                                class="status-badge rejected"
                            >

                                ✗ Claim Rejected

                            </span>


                        <?php elseif (
                            $claim_status
                            === 'Approved'
                        ): ?>


                            <?php if (
                                strtolower(
                                    trim(
                                        $row['owner_status']
                                        ?? ''
                                    )
                                )
                                === 'received'
                            ): ?>


                                <span
                                    class="status-badge
                                           received"
                                >

                                    ✓ Item Received

                                </span>


                            <?php else: ?>


                                <form
                                    action="match-action.php"
                                    method="POST"
                                >

                                    <input
                                        type="hidden"
                                        name="match_id"
                                        value="<?php
                                        echo
                                            (int)$row[
                                                'match_id'
                                            ];
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="received"
                                    >

                                    <button
                                        type="submit"
                                        class="action-button
                                               receive-button"
                                    >

                                        📦 Receive

                                    </button>

                                </form>


                            <?php endif; ?>


                        <?php endif; ?>

                    <?php endif; ?>

                    <!-- FINDER -->

                    <?php if ($is_finder): ?>

                        <?php if ($is_returned): ?>

                            <?php if (!$is_owner): ?>

                                <span
                                    class="status-badge returned-badge"
                                >

                                    🎉 Successfully Returned

                                </span>

                            <?php endif; ?>

                        <?php elseif (
                            $claim_status
                            === 'Not Claimed'
                        ): ?>

                            <?php if (!$is_owner): ?>

                                <span
                                    class="status-badge
                                           not-claimed"
                                >

                                    ○ Not Claimed

                                </span>

                            <?php endif; ?>

                        <?php elseif (
                            $claim_status
                            === 'Pending'
                        ): ?>

                            <div style="display:inline-flex; gap:8px; align-items:center; flex-wrap:wrap;">

                                <a
                                    href="view-claim.php?match_id=<?php echo (int)$row['match_id']; ?>"
                                    class="action-button view-claim-button"
                                >

                                    👁 View Claim Request

                                </a>

                                <form
                                    action="approve-claim.php"
                                    method="POST"
                                    style="display:inline;"
                                >

                                    <input
                                        type="hidden"
                                        name="match_id"
                                        value="<?php
                                        echo (int)$row['match_id'];
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="action-button approve-button"
                                    >

                                        ✓ Approve Claim

                                    </button>

                                </form>

                            </div>


                        <?php elseif (
                            $claim_status
                            === 'Rejected'
                        ): ?>


                            <span
                                class="status-badge rejected"
                            >

                                ✗ Claim Rejected

                            </span>


                        <?php elseif (
                            $claim_status
                            === 'Approved'
                        ): ?>


                            <?php if (
                                strtolower(
                                    trim(
                                        $row['finder_status']
                                        ?? ''
                                    )
                                )
                                === 'give back'
                            ): ?>


                                <span
                                    class="status-badge
                                           approved"
                                >

                                    ✓ Given Back

                                </span>


                            <?php else: ?>


                                <form
                                    action="match-action.php"
                                    method="POST"
                                >

                                    <input
                                        type="hidden"
                                        name="match_id"
                                        value="<?php
                                        echo
                                            (int)$row[
                                                'match_id'
                                            ];
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="given_back"
                                    >

                                    <button
                                        type="submit"
                                        class="action-button
                                               give-button"
                                    >

                                        🤝 Give Back

                                    </button>

                                </form>


                            <?php endif; ?>


                        <?php endif; ?>


                    <?php endif; ?>


                </div>


            </div>


        </div>


    <?php endwhile; ?>


<?php else: ?>


    <div class="no-match">

        <h2>
            🔍 No Matches Found
        </h2>

        <p>
            No possible matches with
            80% or higher similarity
            were found.
        </p>

    </div>


<?php endif; ?>


<!-- BACK -->

<div style="text-align:center;">

    <a
        href="dashboard.php"
        class="back-link"
    >

        ← Back to Dashboard

    </a>

</div>


</div>


</body>

</html>


<?php

$stmt->close();

$conn->close();

?>