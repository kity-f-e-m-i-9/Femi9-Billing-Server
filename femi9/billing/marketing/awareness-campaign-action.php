<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");
error_reporting(0);

// Self-migrating — same convention as salesbdm_locations/BdmTpScope.php's
// own CREATE TABLE IF NOT EXISTS, so this never needs a separate migration
// run on the live server.
$db_conn->query("
    CREATE TABLE IF NOT EXISTS ms_awareness_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ms_id INT NOT NULL,
        campaign_date DATE NOT NULL,
        location VARCHAR(150) NOT NULL,
        topic VARCHAR(200) NOT NULL,
        people_reached INT NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ms_date (ms_id, campaign_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $msId = (int)$markeingSTFID;
    $campaignDate = $_POST['campaign_date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $campaignDate)) { $campaignDate = date('Y-m-d'); }

    $location = RemoveSpecialChar(str_replace("'", "&#39;", trim($_POST['location'] ?? '')));
    $topic = RemoveSpecialChar(str_replace("'", "&#39;", trim($_POST['topic'] ?? '')));
    $peopleReached = max(0, (int)($_POST['people_reached'] ?? 0));
    $notes = RemoveSpecialChar(str_replace("'", "&#39;", trim($_POST['notes'] ?? '')));

    if ($location === '' || $topic === '') {
        echo "<script>window.location='awareness-campaign.php?err=missing';</script>";
        exit;
    }

    $stmt = $db_conn->prepare(
        "INSERT INTO ms_awareness_campaigns (ms_id, campaign_date, location, topic, people_reached, notes)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isssis', $msId, $campaignDate, $location, $topic, $peopleReached, $notes);
    $stmt->execute();
    $stmt->close();

    echo "<script>window.location='awareness-campaign.php?added=1';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    // Only the person who logged it can remove it — same ms_id ownership
    // check used elsewhere (order-invoice-status.php etc.), not an admin-
    // level delete.
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $db_conn->prepare("DELETE FROM ms_awareness_campaigns WHERE id=? AND ms_id=?");
    $msId = (int)$markeingSTFID;
    $stmt->bind_param('ii', $id, $msId);
    $stmt->execute();
    $stmt->close();

    echo "<script>window.location='awareness-campaign.php?deleted=1';</script>";
    exit;
}

header('Location: awareness-campaign.php');
exit;
