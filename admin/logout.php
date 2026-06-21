<?php
session_start();
unset($_SESSION["admin_auth"]);
unset($_SESSION["admin_name"]);

header("Location: index.php");
exit;
