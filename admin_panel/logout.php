<?php
/**
 * Admin Logout
 * Destroys admin session and redirects to login
 */

require_once 'auth.php';

logoutAdmin();
header('Location: index.php');
exit();
?>