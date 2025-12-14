<?php
require_once __DIR__ . '/user_config.php';
start_user_session_once();

clear_user_session();

header('Location: index.php');
exit;
?>
