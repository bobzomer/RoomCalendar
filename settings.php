<?php
require_once '../database_access.php';

function getSettings(Database $conn) : array {
    $settings = $conn->getSettings();

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $settings['AppId'] = $_POST['AppId'];
        $settings['AppSecret'] = $_POST['AppSecret'];
        $settings['Tenant'] = $_POST['Tenant'];
        $settings = $conn->setSettings($settings);
    }

    if (!isset($settings['AppId'])) {
        echo <<<html
<!DOCTYPE html>
<html lang="en"><head><title>Initial setup</title></head>
<body>
<h1>Setup</h1>
<form method="post">
<table>
<tr>
<td><label for="AppId">AppId</label></td>
<td><input id="AppId" name="AppId" /></td>
</tr>
<tr>
<td><label for="AppSecret">AppSecret</label></td>
<td><input id="AppSecret" name="AppSecret" /></td>
</tr>
<tr>
<td><label for="Tenant">Tenant</label></td>
<td><input id="Tenant" name="Tenant" /></td>
</tr>
</table>
<input type="submit" value="Enregistrer" id="form_submit" />
</form></body></html>
html;
        exit();
    }
}