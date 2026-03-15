<?php

if (session_status() != PHP_SESSION_NONE) 
{
    session_unset();
    session_destroy();
}

session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST")
{

    $login = $_POST['login'];
    $pwd = $_POST['pwd'];

    include('db.php');
    $sql = "SELECT * FROM credentials WHERE loginid='" . strtolower($login) . "'";
    $result = $conn->query($sql);
    if($row = $result->fetch_assoc())
    {
        if($row['pwd'] == $pwd)
        {   
            $_SESSION['authenticated'] =true;
            $_SESSION['loginid'] = $login;
            
            $_SESSION['status'] = 0;
            switch($row['role'])
            {
                case('user'):
                    $_SESSION['role'] = 'user';
                    header("Location:landing.php");
                    break;
                case('admin'):
                    $_SESSION['role'] = 'admin';
                    header("Location:landing.php");
                    break;
            }
            $continue = 1;
            
        }
        else
        {
            $_SESSION['authenticated'] =false;
            $continue = 2;
        }
    }
}
else
{
    $continue = 0;
}
    
?>

<?php
if($continue == 0 || $continue == 2)
{
    ?>
    <!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Expense Manager 1.0</title>
            <meta name="description=" content="Expense Manager 1.0" >
            <link rel="preconnect" href="https://fonts.gstatic.com"> 
            <link href="https://fonts.googleapis.com/css2?family=Karla:wght@300&display=swap" rel="stylesheet">
            <link href="https://fonts.googleapis.com/css2?family=Lobster&family=Overpass+Mono:wght@300&family=Poppins:wght@300&display=swap" rel="stylesheet">
            <link href="css/master.css?v=4w" rel="stylesheet" type="text/css"/>
            <link href="js/jquery-ui.css" rel="stylesheet" type="text/css"/>
            <link href="js/jquery-ui.min.css" rel="stylesheet" type="text/css"/>
            <link href="js/jquery-ui.structure.css" rel="stylesheet" type="text/css"/>
            <link href="js/jquery-ui.structure.min.css" rel="stylesheet" type="text/css"/>
            <script src="js/jquery.js" type="text/javascript"></script>
            <script src="js/jquery-ui.js" type="text/javascript"></script>
            <script src="js/jquery-ui.min.js" type="text/javascript"></script>
            <script src="js/expense_js.js" type="text/javascript"></script>
        </head>
        <body id="login_form_body">
            <div id="login_container">
                <p class="title_head_centered">expense manager</p>
                <form action="" method="POST" enctype="multipart/form-data" id='login_form'>
                    <input id="login" class="login_input" type="text" autocomplete="off" value='' name='login' placeholder="LOGIN ID" /><br>
                    <input id="pwd" class="login_input" type="password" autocomplete="off" value='' name='pwd' placeholder="PASSWORD" /><br>
                    <input id='submit_login' type='submit' value='LOG ON' />
                </form> 
            <?php
            if($continue == 2)
            {
                echo("<div id='error'>Incorrect Password</div>");
            }
            ?>
            </div>
            
            <script>
                $(document).ready(function(){
                    $('#login_container').center();
                    $("#login").focus();
                });
                
                $("#login").blur(function(){
                    $("#pwd").focus();
                });
                
                $("#pwd").blur(function(){
                    $("#submit_login").focus();
                });
            </script>
        </body>
    </html>
<?php
}
?>
