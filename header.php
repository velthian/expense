<?php
    if (session_status() === PHP_SESSION_NONE) 
    {
        session_start();
    }

    date_default_timezone_set('Asia/Calcutta');
    if(!isset($_SESSION['authenticated']))
    {
        header('Location: index.php');
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta HTTP-EQUIV="CACHE-CONTROL" CONTENT="NO-CACHE">
        <title>Expense Manager 1.0</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" />
        <link href="css/master.css?version=46t43" rel="stylesheet" type="text/css"/>
        <link rel="preconnect" href="https://fonts.googleapis.com"> 
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Lobster&family=Overpass+Mono:wght@300&family=Poppins:wght@300&display=swap" rel="stylesheet">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
        
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css">
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
        <script src="js/expense_js.js?version=1e" type="text/javascript"></script>
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <!-- Chart.js Data Labels Plugin -->
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

        <script>
            // Register plugin AFTER both scripts are loaded
            Chart.register(ChartDataLabels);
        </script>

    </head>

    <body>
        <div class="overlay"></div>
        <div id="main_container">
            <div id="content_wrap">
            <div id='mc_header_main'>
                <div id="main_menu"><img src='images/main-menu.png' width='30px' /></div>
                <div id="mc_header_text">
                    <div id='mc_h_t1'>ex</div><div id='mc_h_t2'>pense manager</div>
                </div>
                <div id ="mc_header_logout"><a id="logout" href="logout.php"><span id="mc_header_logout_span"><img src="images/logout.png" alt="" width="25px" /></span></a></div>
            </div>
            <div id="mc_header_date">
                <?php echo(date('d M y')); ?>
            </div>               
            <div id='menu_container'>
                <input type='hidden' id='menu_flag' value ='0'/>
                <div id="chevrons">
                    <a id="c_s_1" class="chev_span" href="landing.php">
                        <div class='chev_span_inner'>
                            <img src='images/transaction.png' width='20px' />
                            <span id="c_s_1_1">TRANSACTIONS</span>
                        </div>
                    </a>
                    <a id="c_s_1" class="chev_span" href="assignCategory.php">
                        <div class='chev_span_inner'>
                            <img src='images/categorization.png' width='20px' />
                            <span id="c_s_1_1">ASSIGN CATEGORY</span>
                        </div>
                    </a><!-- comment -->
                    <a id="c_s_1" class="chev_span" href="creditCard.php">
                        <div class='chev_span_inner'>
                            <img src='images/credit-card.png' width='20px' />
                            <span id="c_s_1_1">CREDIT CARD</span>
                        </div>
                    </a><!-- comment -->
                    <a id="c_s_1" class="chev_span" href="budgetManager.php">
                        <div class='chev_span_inner'>
                            <img src='images/budget.png' width='20px' />
                            <span id="c_s_1_1">MANAGE BUDGET</span>
                        </div>
                    </a><!-- comment -->
                    <a id="c_s_1" class="chev_span" href="analyze.php">
                        <div class='chev_span_inner'>
                            <img src='images/report.png' width='20px' />
                            <span id="c_s_1_1">REPORTS</span>
                        </div>
                    </a><!-- comment -->
                    <a id="c_s_1" class="chev_span" href="supCatManager.php">
                        <div class='chev_span_inner'>
                            <img src='images/supercat.png' width='20px' />
                            <span id="c_s_1_1">SUPER CATEGORIES</span>
                        </div>
                    </a><!-- comment -->
                    <a id="c_s_1" class="chev_span" href="spendView.php">
                        <div class='chev_span_inner'>
                            <img src='images/supercat.png' width='20px' />
                            <span id="c_s_1_1">AGGREGATE BY SUPER CATEGORY</span>
                        </div>
                    </a>
                    <a id="c_s_1" class="chev_span" href="creditCardRecon.php">
                        <div class='chev_span_inner'>
                            <img src='images/supercat.png' width='20px' />
                            <span id="c_s_1_1">CREDIT CARD RECON</span>
                        </div>
                    </a>
                    <a id="c_s_1" class="chev_span" href="creditCardReconList.php">
                        <div class='chev_span_inner'>
                            <img src='images/supercat.png' width='20px' />
                            <span id="c_s_1_1">CREDIT CARD RECON LIST</span>
                        </div>
                    </a>
                    <a id="c_s_1" class="chev_span" href="bankAccountRecon.php">
                        <div class='chev_span_inner'>
                            <img src='images/supercat.png' width='20px' />
                            <span id="c_s_1_1">BANK ACCOUNT RECON</span>
                        </div>
                    </a>
                    <a id="c_s_1" class="chev_span" href="bankAccountReconList.php">
                        <div class='chev_span_inner'>
                            <img src='images/supercat.png' width='20px' />
                            <span id="c_s_1_1">BANK ACCOUNT RECON LIST</span>
                        </div>
                    </a>
                    <a id="c_s_1" class="chev_span" href="creditCardReconList.php">
                        <div class='chev_span_inner'>
                            <img src='images/supercat.png' width='20px' />
                            <span id="c_s_1_1">UPLOADED STATEMENTS</span>
                        </div>
                    </a>
                </div>
            </div>

            <script>
                $('#main_menu').click(function(){
                    if($('#menu_flag').val() == '1')
                    {
                        $('#menu_container').fadeOut();
                        $('#menu_flag').val('0');
                    }
                    else
                    {
                        $('#menu_container').fadeIn();
                        $('#menu_flag').val('1');
                    }
                });
                
                window.addEventListener('mouseup', function(e) {
                    var x = document.querySelector('#menu_container');
                    if (event.target != document.querySelector("#main_menu")) {
                        x.style.display = "none";
                        $('#menu_flag').val('0');
                    }
                });
            </script>
