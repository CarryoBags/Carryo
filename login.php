<?php
session_start();

// Check if the user is already logged in, redirect to index.php
if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// Dummy user credentials (replace with your actual credentials)
$validUsername = 'admin';
$validPassword = '123456';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validate the credentials
    if ($username === $validUsername && $password === $validPassword) {
        $_SESSION['user'] = $username; // Set the user session variable
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error_message'] = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login_container">

          <?php if (isset($_SESSION['error_message'])) { ?>
            <div class="error_msg">
                <?php echo $_SESSION['error_message']; ?>
            </div>
            <?php unset($_SESSION['error_message']); // Clear the session variable after displaying ?>
        <?php } ?>



<form method="post">
  <h1 class="a11y-hidden">Login Form</h1>
  <div>
    <label class="label-email">
      <input type="username" class="text" name="username" placeholder="username" tabindex="1" required />
      <span class="required">Username</span>
    </label>
  </div>
  <input type="checkbox" name="show-password" class="show-password a11y-hidden" id="show-password" tabindex="3" />
  <label class="label-show-password" for="show-password">
    <span>Show Password</span>
  </label>
  <div>
    <label class="label-password">
      <input type="password" class="text" name="password" placeholder="Password" tabindex="2" required />
      <span class="required">Password</span>
    </label>
  </div>
  <input type="submit" value="Log In" />

  <figure aria-hidden="true">
    <div class="person-body"></div>
    <div class="neck skin"></div>
    <div class="head skin">
      <div class="eyes"></div>
      <div class="mouth"></div>
    </div>
    <div class="hair"></div>
    <div class="ears"></div>
    <div class="shirt-1"></div>
    <div class="shirt-2"></div>
  </figure>
</form>



    </div>
</body>
</html>
