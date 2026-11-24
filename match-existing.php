<?php

session_start();
include "db.php";
requireAdmin();

/*
    Existing Approved Lost Items
*/

$lost_sql = "
    SELECT *
    FROM lost_items
    WHERE status = 'Approved'
";

$lost_result = $conn->query($lost_sql);


/*
    Existing Approved / Available Found Items
*/

$found_sql = "
    SELECT *
    FROM found_items
    WHERE status IN ('Approved', 'Available')
";


if (!$lost_result) {
    die("Lost Items Error: " . $conn->error);
}


// Load found items once instead of re-querying for every lost item
$found_result = $conn->query($found_sql);

if (!$found_result) {
    die("Found Items Error: " . $conn->error);
}

$found_rows = $found_result ? $found_result->fetch_all(MYSQLI_ASSOC) : [];

if ($lost_result && !empty($found_rows)) {

    $check_sql = "
        SELECT match_id
        FROM matches
        WHERE lost_id = ?
        AND found_id = ?
        LIMIT 1
    ";
    $check_stmt = $conn->prepare($check_sql);

    $insert_sql = "
        INSERT INTO matches
        (
            lost_id,
            found_id,
            item_name_score,
            category_score,
            location_score,
            date_score,
            match_percentage,
            status,
            finder_status,
            owner_status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'Pending',
            'Pending',
            'Pending'
        )
    ";
    $insert_stmt = $conn->prepare($insert_sql);

    while ($lost = $lost_result->fetch_assoc()) {

        foreach ($found_rows as $found) {


            /* =========================
               ITEM NAME = 40
               ========================= */

            $lost_name = strtolower(trim((string) ($lost['item_name'] ?? '')));
            $found_name = strtolower(trim((string) ($found['item_name'] ?? '')));

            if ($lost_name !== '' && $lost_name === $found_name) {

                $item_name_score = 100;

            } elseif ($lost_name === '' || $found_name === '') {

                $item_name_score = 0;

            } else {

                similar_text(
                    $lost_name,
                    $found_name,
                    $name_similarity
                );

                $item_name_score = round($name_similarity);
            }


            /* =========================
               CATEGORY = 25
               ========================= */

            $lost_cat = strtolower(trim((string) ($lost['category'] ?? '')));
            $found_cat = strtolower(trim((string) ($found['category'] ?? '')));

            if (
                $lost_cat !== '' &&
                $lost_cat === $found_cat
            ) {

                $category_score = 100;

            } else {

                $category_score = 0;

            }


            /* =========================
               LOCATION = 25
               ========================= */

            $lost_location =
                strtolower(trim((string) ($lost['lost_location'] ?? '')));

            $found_location =
                strtolower(trim((string) ($found['found_location'] ?? '')));


            if ($lost_location !== '' && $lost_location === $found_location) {

                $location_score = 100;

            } elseif ($lost_location === '' || $found_location === '') {

                $location_score = 0;

            } else {

                similar_text(
                    $lost_location,
                    $found_location,
                    $location_similarity
                );

                $location_score = round($location_similarity);
            }


            /* =========================
               DATE = 10
               ========================= */

            $lost_date =
                strtotime((string) ($lost['lost_date'] ?? ''));

            $found_date =
                strtotime((string) ($found['found_date'] ?? ''));

            if ($lost_date === false || $found_date === false) {

                $date_score = 0;

            } else {

                $difference =
                    abs($lost_date - $found_date)
                    / (60 * 60 * 24);


                if ($difference == 0) {

                    $date_score = 100;

                } elseif ($difference <= 1) {

                    $date_score = 80;

                } elseif ($difference <= 3) {

                    $date_score = 60;

                } elseif ($difference <= 7) {

                    $date_score = 40;

                } else {

                    $date_score = 0;

                }

            }


            /* =========================
               FINAL 100 MARKS
               ========================= */

            $match_percentage =
                ($item_name_score * 40 / 100) +
                ($category_score * 25 / 100) +
                ($location_score * 25 / 100) +
                ($date_score * 10 / 100);


            $match_percentage =
                round($match_percentage, 2);


            /*
                50% বা তার বেশি হলে Match
            */

            if ($match_percentage >= 50 && $check_stmt && $insert_stmt) {

                $lost_id_val = (int) $lost['lost_id'];
                $found_id_val = (int) $found['found_id'];

                /*
                    Duplicate check
                */

                $check_stmt->bind_param("ii", $lost_id_val, $found_id_val);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result && $check_result->num_rows == 0) {

                    /*
                        MATCH INSERT
                    */

                    $insert_stmt->bind_param(
                        "iiddddd",
                        $lost_id_val,
                        $found_id_val,
                        $item_name_score,
                        $category_score,
                        $location_score,
                        $date_score,
                        $match_percentage
                    );

                    $insert_stmt->execute();
                }

            }

        }

    }

    if ($check_stmt) {
        $check_stmt->close();
    }
    if ($insert_stmt) {
        $insert_stmt->close();
    }
}


/*
    Done
*/

echo "
<script>

alert('Existing Lost and Found items matched successfully!');

window.location.href='my-matches.php';

</script>
";


$conn->close();

?>