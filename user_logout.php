<?php
require_once 'includes/config.php';

// Prevent caching on logout response
preventPageCaching();

// If admin is also logged in in the same browser session, unset user keys; otherwise destroy complete session
if (isAdminLoggedIn()) {
    unset($_SESSION['user_id']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_email']);
} else {
    destroyUserSession();
}

// Redirect to login page
redirect('login.php');
?>
