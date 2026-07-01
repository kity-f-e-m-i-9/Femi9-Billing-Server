<?php 
include("checksession.php");
$title = "Daily Login Rewards Report";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <title><?php echo $title;?> : <?php echo $business_name;?></title>

    <!-- Styles -->
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

    <style>
        .filter-section {
            margin-bottom: 20px;
        }
        .hidden {
            display: none;
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.15em;
        }
    </style>
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
        
        <div class="app-container">
            <?php include("app-header.php");?>
            
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td><?php echo $title;?></td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <form action="Daily-Login-Report-Details.php" method="post" enctype="multipart/form-data" id="reportForm">
                                            <div class="example-container">
                                                <div class="example-content">
                                                    
                                                    <!-- State Selection -->
                                                    <div class="filter-section">
                                                        <label for="state_select" class="form-label">
                                                            Select State <span style="color:red;">*</span>
                                                        </label>
                                                        <select required name="state_id" id="state_select" class="form-control">
                                                            <option value="" hidden>-- Select State --</option>
                                                            <?php 
                                                            $select_stateList = "SELECT * FROM `state` ORDER BY `st_name` ASC";
                                                            $fetch_stateList = mysqli_query($db_conn, $select_stateList);
                                                            while($result_stateList = mysqli_fetch_array($fetch_stateList)) {
                                                            ?>
                                                                <option value="<?php echo $result_stateList['id'];?>">
                                                                    <?php echo htmlspecialchars($result_stateList['st_name']);?>
                                                                </option>
                                                            <?php } ?>
                                                        </select>
                                                    </div>

                                                    <!-- User Type Selection (Hidden initially) -->
                                                    <div class="filter-section hidden" id="user_type_section">
                                                        <label for="user_type_select" class="form-label">
                                                            Select User Type <span style="color:red;">*</span>
                                                        </label>
                                                        <select required name="user_type" id="user_type_select" class="form-control">
                                                            <option value="" hidden>-- Select User Type --</option>
                                                            <option value="super_stockiest">Super Stockist</option>
                                                            <option value="stockiest">Stockist</option>
                                                            <option value="distributor">Distributor</option>
                                                            <option value="super_distributor">Super Distributor</option>
                                                        </select>
                                                        <small class="form-text text-muted">
                                                            <span class="spinner-border spinner-border-sm hidden" id="user_type_loader"></span>
                                                            <span id="user_type_message"></span>
                                                        </small>
                                                    </div>

                                                    <!-- User Name Selection (Hidden initially) -->
                                                    <div class="filter-section hidden" id="user_name_section">
                                                        <label for="user_name_select" class="form-label">
                                                            Select User <span style="color:red;">*</span>
                                                        </label>
                                                        <select required name="user_id" id="user_name_select" class="form-control">
                                                            <option value="" hidden>-- Select User --</option>
                                                        </select>
                                                        <small class="form-text text-muted">
                                                            <span class="spinner-border spinner-border-sm hidden" id="user_name_loader"></span>
                                                            <span id="user_name_message"></span>
                                                        </small>
                                                    </div>

                                                    <!-- Submit Button (Hidden initially) -->
                                                    <div class="filter-section hidden" id="submit_section">
                                                        <button type="submit" name="view_report" class="btn btn-primary">
                                                            <i class="material-icons">visibility</i> View Report
                                                        </button>
                                                    </div>

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

    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>

    <script>
    $(document).ready(function() {
        
        // When state is selected
        $('#state_select').on('change', function() {
            var stateId = $(this).val();
            
            if(stateId) {
                // Show user type section
                $('#user_type_section').removeClass('hidden');
                
                // Reset and hide subsequent sections
                $('#user_type_select').val('');
                $('#user_name_section').addClass('hidden');
                $('#user_name_select').html('<option value="" hidden>-- Select User --</option>');
                $('#submit_section').addClass('hidden');
            } else {
                // Hide all subsequent sections
                $('#user_type_section').addClass('hidden');
                $('#user_name_section').addClass('hidden');
                $('#submit_section').addClass('hidden');
            }
        });

        // When user type is selected
        $('#user_type_select').on('change', function() {
            var userType = $(this).val();
            var stateId = $('#state_select').val();
            
            if(userType && stateId) {
                // Show loader
                $('#user_name_loader').removeClass('hidden');
                $('#user_name_message').text('Loading users...');
                $('#user_name_select').prop('disabled', true);
                
                // Fetch users via AJAX
                $.ajax({
                    url: 'get_daily_login_filter_data.php',
                    type: 'GET',
                    data: {
                        action: 'get_users',
                        user_type: userType,
                        state_id: stateId
                    },
                    dataType: 'json',
                    success: function(response) {
                        // Hide loader
                        $('#user_name_loader').addClass('hidden');
                        
                        if(response.success) {
                            var users = response.data;
                            
                            if(users.length > 0) {
                                // Populate dropdown
                                var options = '<option value="" hidden>-- Select User --</option>';
                                $.each(users, function(index, user) {
                                    options += '<option value="' + user.id + '">' + user.name + '</option>';
                                });
                                
                                $('#user_name_select').html(options);
                                $('#user_name_message').text(users.length + ' users found');
                                $('#user_name_section').removeClass('hidden');
                            } else {
                                $('#user_name_message').text('No users found for selected criteria');
                                $('#user_name_select').html('<option value="" hidden>-- No Users Found --</option>');
                                $('#user_name_section').removeClass('hidden');
                            }
                        } else {
                            $('#user_name_message').text('Error: ' + response.message);
                        }
                        
                        $('#user_name_select').prop('disabled', false);
                    },
                    error: function(xhr, status, error) {
                        $('#user_name_loader').addClass('hidden');
                        $('#user_name_message').text('Error loading users. Please try again.');
                        $('#user_name_select').prop('disabled', false);
                        console.error('AJAX Error:', error);
                    }
                });
            } else {
                $('#user_name_section').addClass('hidden');
                $('#submit_section').addClass('hidden');
            }
        });

        // When user is selected
        $('#user_name_select').on('change', function() {
            var userId = $(this).val();
            
            if(userId) {
                // Show submit button
                $('#submit_section').removeClass('hidden');
            } else {
                $('#submit_section').addClass('hidden');
            }
        });

    });
    </script>
</body>
</html>