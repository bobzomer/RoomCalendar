<?php
\Sentry\init(['dsn' => $_ENV['SENTRY_DSN']]);

if (array_key_exists('error', $_GET)) {
    \Sentry\captureMessage($_GET['error'] . ": " .  $_GET['error_description']);
    $error = $_GET['error'];
    $error_description = nl2br($_GET['error_description']);
    echo <<<html
<!DOCTYPE html>
<html lang="en"><head><title>Error</title></head>
<body>
<h1>$error</h1>
<code>$error_description</code>
</body>
</html>
html;
    exit();
}
