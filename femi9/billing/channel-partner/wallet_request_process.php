<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// Cancel pending request
if (isset($_GET['delid'])) {
    $del_id = (int)base64_decode($_GET['delid']);
    if ($del_id > 0) {
        $uid   = mysqli_real_escape_string($db_conn, $Login_user_IDvl);
        $utype = mysqli_real_escape_string($db_conn, $Login_user_TYPEvl);
        mysqli_query($db_conn,
            "DELETE FROM wallet_withdraw WHERE id='$del_id' AND user_type='$utype' AND user_id='$uid' AND req_status='pending'"
        );
        $_SESSION['successMessage'] = "Withdraw request cancelled.";
    }
    header("Location: wallet-history.php");
    exit;
}

// Submit new withdraw request
if (isset($_REQUEST['sent_money_request'])) {
    $req_id        = mysqli_real_escape_string($db_conn, $_REQUEST['req_id'] ?? '');
    $req_status    = 'pending';
    $user_type     = mysqli_real_escape_string($db_conn, $Login_user_TYPEvl);
    $user_id       = mysqli_real_escape_string($db_conn, $Login_user_IDvl);
    $date          = date("Y-m-d");
    $time          = date("H:i:s");
    $request_amount = (float)($_REQUEST['request_amount'] ?? 0);
    $acname        = mysqli_real_escape_string($db_conn, str_replace("'", "", $_REQUEST['acname'] ?? ''));
    $acnumber      = mysqli_real_escape_string($db_conn, str_replace("'", "", $_REQUEST['acnumber'] ?? ''));
    $bankname      = mysqli_real_escape_string($db_conn, str_replace("'", "", $_REQUEST['bankname'] ?? ''));
    $ifsc          = mysqli_real_escape_string($db_conn, str_replace("'", "", $_REQUEST['ifsc'] ?? ''));
    $pannumber     = mysqli_real_escape_string($db_conn, str_replace("'", "", $_REQUEST['pannumber'] ?? ''));

    // Atomic pending + balance check inside a transaction to prevent race conditions
    mysqli_begin_transaction($db_conn);

    // Lock: block if already has a pending request
    $pending = mysqli_num_rows(mysqli_query($db_conn,
        "SELECT id FROM wallet_withdraw WHERE user_type='$user_type' AND user_id='$user_id' AND req_status='pending' FOR UPDATE"
    ));
    if ($pending > 0) {
        mysqli_rollback($db_conn);
        $_SESSION['errorMessage'] = "You have already sent one request. Kindly wait for company approval.";
        header("Location: wallet-history.php");
        exit;
    }

    // Balance check: requested amount must not exceed available wallet balance
    $totalCredits   = (float)(mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT COALESCE(SUM(commission_amount),0) FROM wallet_monthly_sls_report WHERE refer_by_usertype='$user_type' AND refer_by_userid='$user_id'"))[0] ?? 0);
    $totalWithdrawn = (float)(mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT COALESCE(SUM(amount),0) FROM wallet_withdraw WHERE user_type='$user_type' AND user_id='$user_id' AND req_status='approved'"))[0] ?? 0);
    $walletBalance  = $totalCredits - $totalWithdrawn;

    if ($request_amount > $walletBalance) {
        mysqli_rollback($db_conn);
        $_SESSION['errorMessage'] = "Requested amount ₹" . number_format($request_amount, 2) . " exceeds your available balance ₹" . number_format(max(0, $walletBalance), 2) . ".";
        header("Location: wallet-history.php");
        exit;
    }

    // Prevent duplicate req_id
    $dup = mysqli_num_rows(mysqli_query($db_conn, "SELECT id FROM wallet_withdraw WHERE req_id='$req_id'"));
    if ($dup === 0 && $request_amount >= 100) {
        mysqli_query($db_conn,
            "INSERT INTO wallet_withdraw (amount, req_id, req_status, user_type, user_id, date, time, remarks,
             updated_date, updated_time, TDS_percentage, TDS_deduction, sent_amount, acname, acnumber, bankname, ifsc, pannumber)
             VALUES ('$request_amount','$req_id','$req_status','$user_type','$user_id','$date','$time',
             'Nil','1970-01-01','12:12:12','0','0','0','$acname','$acnumber','$bankname','$ifsc','$pannumber')"
        );

        // Update PAN in profile if empty
        if (!empty($pannumber)) {
            $pan_row = mysqli_fetch_assoc(mysqli_query($db_conn,
                "SELECT pannumber FROM users_profile WHERE user_tempid='$user_id' AND usertype='$user_type' LIMIT 1"
            ));
            if (empty($pan_row['pannumber'])) {
                mysqli_query($db_conn,
                    "UPDATE users_profile SET pannumber='$pannumber' WHERE user_tempid='$user_id' AND usertype='$user_type'"
                );
            }
        }

        $_SESSION['successMessage'] = "Withdraw request sent successfully.";
        mysqli_commit($db_conn);
    } else {
        mysqli_rollback($db_conn);
    }

    header("Location: wallet-history.php");
    exit;
}

header("Location: wallet-history.php");
exit;
