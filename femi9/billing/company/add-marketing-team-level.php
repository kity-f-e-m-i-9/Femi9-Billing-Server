<?php
include("checksession.php");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db_conn->query("CREATE TABLE IF NOT EXISTS marketing_team_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_rank INT NOT NULL,
    level_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_level_rank (level_rank)
)");

// Pre-fill next available rank
$stmt_mx = $db_conn->query("SELECT COALESCE(MAX(level_rank), 0) + 1 AS next_rank FROM marketing_team_levels");
$next_rank = $stmt_mx ? (int)$stmt_mx->fetch_assoc()['next_rank'] : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Team Level : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">

    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>

    <div class="app-container">
        <?php include("app-header.php"); ?>

        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">

                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble">
                                        <tr>
                                            <td>Add Team Level</td>
                                            <td>
                                                <a href="manage-marketing-team-levels" title="Manage Levels">&#9776;</a>
                                            </td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">

                                    <?php if (isset($_REQUEST['alreadyexists'])): ?>
                                        <div class="alert alert-danger">A level for rank <?php echo (int)($_GET['rank'] ?? 0); ?> already exists.</div>
                                    <?php endif; ?>

                                    <p class="text-muted mb-3" style="font-size:13px;">
                                        A team level defines a rung in the marketing team hierarchy.
                                        Example: Rank 1 = <em>SM</em>, Rank 2 = <em>ASM</em>, Rank 3 = <em>DM</em>. Rank 1 is the top of the hierarchy.
                                    </p>

                                    <?php include("validate-scripts.php"); ?>

                                    <form action="marketing-team-level-action" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                        <div class="example-container">
                                            <div class="example-content">

                                                <div class="mb-3">
                                                    <label class="form-label">Rank <span class="text-danger">*</span></label>
                                                    <input type="number" required name="level_rank"
                                                           class="form-control"
                                                           value="<?php echo $next_rank; ?>"
                                                           min="1" max="100"
                                                           style="max-width:100px;">
                                                    <small class="text-muted">Each rank must be unique. Rank 1 is the top level.</small>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Level Name <span class="text-danger">*</span></label>
                                                    <input type="text" required name="level_name"
                                                           class="form-control"
                                                           placeholder="e.g. SM / ASM / DM"
                                                           maxlength="50"
                                                           onkeypress="restrictSpecialChars(event)">
                                                </div>

                                                <br>
                                                <button type="submit" name="insert-marketing-team-level" class="btn btn-primary">
                                                    <i class="material-icons">add</i> Add Level
                                                </button>
                                                <a href="manage-marketing-team-levels" class="btn btn-secondary ms-2">Cancel</a>

                                            </div>
                                        </div>
                                    </form>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/highlight/highlight.pack.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
