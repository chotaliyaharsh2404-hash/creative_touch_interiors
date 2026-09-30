<?php
require_once '../includes/config.php';

// Prevent caching on logout response
preventPageCaching();

unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);
unset($_SESSION['show_welcome_modal']);

// If no user is logged in, clean entire session
if (!isUserLoggedIn()) {
    destroyUserSession();
}

redirect('login.php');
?>
