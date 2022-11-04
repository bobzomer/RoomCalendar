<?php
require_once 'database_access.php';
require_once 'authentication.php';

session_start();

$conn = new Database();
$settings = $conn->getSettings();

$user_name = logout($settings['AppId'], $settings['AppSecret']);
