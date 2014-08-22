<?php
	//include 'auth.php';
	$auth_realm = 'NBT Configuration';
	require_once 'auth.php';
	require_once 'productname.php';
	require_once 'helpers.php';
?>

<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="images/favicon.ico">

    <title><?php print $productShortName; ?> Change Password</title>

    <!-- Bootstrap core CSS -->
    <link href="bootstrap/css/bootstrap.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="navbar.css" rel="stylesheet">

    <!-- Just for debugging purposes. Don't actually copy this line! -->
    <!--[if lt IE 9]><script src="../../docs-assets/js/ie8-responsive-file-warning.js"></script><![endif]-->

      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
    <![endif]-->
  </head>

  <body>

    <div class="container">

    <?php include 'menu.php'; ?>

<?php
    // check for the submit
    // if set, check the old password first
    // then check the 2 new ones match
    // then set the password

    if ((isset($_POST["submit"])))
    {
        $message = array();

        // basic test - are the values provided?
        if ( !empty($_POST['old_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_password']) )
        {
            // test 1 - is the old password valid?  We can just re-auth to validate
            if ( authenticate($_SESSION['username'],$_POST['old_password']) )
            {
                // bad password
                $message['type'] = 'danger';
                $message['text'] = 'The old password provided is incorrect';
            } else
            {
                // Old password is correct - now validate new == confirm
                if ( strcmp($_POST['new_password'],$_POST['confirm_password']) == 0 )
                {
                    // proceed with changing the password
                    $result = changePassword($_SESSION['username'],$_POST['old_password'],$_POST['new_password']);
                    if ($result == 0)
                    {
                        // Successfully changed password
                        $message['type'] = 'success';
                        $message['text'] = 'The password for ' . $_SESSION['username'] . ' has been successfully changed';
                    } else
                    {
                        // Unable to change password
                        $message['type'] = 'danger';
                        $message['text'] = 'Unable to change password. An unexpected error occured (' . $result . ')';

                    }
                } else
                {
                    $message['type'] = 'danger';
                    $message['text'] = 'The passwords you entered did not match';
                }
            }
        } else
        {
            $message['type'] = 'danger';
            $message['text'] = 'You must provide the old password and a new password';
        }

        if (!empty($message))
        {
            doAlert($message['type'],true,$message['text']);
        }
    }

    // *************************
    // PAGE CONTENT STARTS BELOW
    // *************************
?>
    <form method='post' action=''>
    <div class="panel panel-default">
        <div class="panel-heading clearfix">
            <h4 class="panel-title pull-left" style="padding-top: 7.5px;">Change Password</h4>
            <div class="btn-group pull-right">
                <button class='btn btn-primary' type='submit' name='submit'>Save</button>
            </div>
        </div>

            <div class='panel-body'>
                <div class='input-group input-group-lg'>
                    <span class='input-group-addon'>
                        <span class='glyphicon glyphicon-lock'></span> 
                    </span>
                    <input type='password' class='form-control' name='old_password' placeholder='Old Password'>
                </div>
                &nbsp;
                <div class='input-group input-group-lg'>
                    <span class='input-group-addon'>
                        <span class='glyphicon glyphicon-lock'></span>
                    </span>
                    <input type='password' class='form-control' name='new_password' placeholder='New Password'>
                </div>
                &nbsp;
                <div class='input-group input-group-lg'>
                    <span class='input-group-addon'>
                        <span class='glyphicon glyphicon-lock'></span>
                    </span>
                    <input type='password' class='form-control' name='confirm_password' placeholder='Confirm Password'>
                </div>
            </div>
        </form>
    </div>

    </div> <!-- /container -->

    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="https://code.jquery.com/jquery-1.10.2.min.js"></script>
    <script src="bootstrap/js/bootstrap.min.js"></script>
    <script src="signiant.js"></script>
  </body>
</html>

