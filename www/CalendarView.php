<?php
require_once '../database_access.php';
require_once '../authentication.php';

\Sentry\init(['dsn' => $_ENV['SENTRY_DSN']]);

if (array_key_exists('error', $_GET)) {
    \Sentry\captureMessage($_GET['error'] . ": " .  $_GET['error_description']);
    $error = $_GET['error'];
    $error_description = nl2br($_GET['error_description']);
    echo <<<html
<!DOCTYPE html>
<html><head><title>Error</title></head>
<body>
<h1>$error</h1>
<code>$error_description</code>
</body>
</html>
html;
    exit();
}

try {
    session_start();

    $conn = new Database();
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
<html><head><title>Initial setup</title></head>
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
    $user_name = checkOrAuthenticate($settings['AppId'], $settings['AppSecret'], $settings['Tenant']);
    if (array_key_exists('code', $_GET)) {
        header('Location: ' . getCurrentUrl());
        exit();
    }
    $user = $conn->getUserByName($user_name);

    if (array_key_exists('slot_id', $_GET)) {
        $room_id = intval($_GET['room_id']);
        $date = new DateTimeImmutable($_GET['date']);
        if (array_key_exists('to_delete_booking_id', $_GET) && !empty($_GET['to_delete_booking_id'])) {
            $booking_id = intval($_GET['to_delete_booking_id']);
            $booking_user = $conn->getBookingUser($booking_id);
            if ($user->is_admin || $booking_user->id == $user->id) {
                $conn->deleteBooking($booking_id);
            }
        } else {
            // This is a booking request => book the room
            $slot_id = intval($_GET['slot_id']);
            $conn->addBooking($room_id, $user->id, $slot_id, $date);
        }
        $weekstart = $date->format('Y-m-d');
        echo "<html><head><script>window.location.replace(location.href.split('?')[0] + '?room_id=$room_id&weekstart=$weekstart')</script></head></html>";
        exit();
    }

    $available_rooms = $conn->getRoomList();
    $slots = $conn->getSlots();

    $days = array("Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi");

    if (array_key_exists('room_id', $_GET))
        $current_room = array_values(array_filter($available_rooms, function ($r) {
            return strval($r->id) == $_GET['room_id'];
        }))[0];
    else
        $current_room = $available_rooms[0];

    if (array_key_exists('weekstart', $_GET))
        $weekstart_param = strtotime($_GET['weekstart']);
    else
        $weekstart_param = strtotime("now");
    $dayofweek = date('w', $weekstart_param);
    if ($dayofweek == 0)
        $dayofweek += 7;
    $weekstart = (new DateTimeImmutable())
        ->setTimestamp($weekstart_param)
        ->sub(date_interval_create_from_date_string(($dayofweek - 1) . " days"));
    $weekstop = $weekstart->add(date_interval_create_from_date_string("4 days"));

    $reversations = $conn->getBookings($current_room->id, $weekstart, $weekstop);
}
catch (Exception $exc) {
    \Sentry\captureException($exc);
    throw $exc;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Calendar view</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        * {
            font-family: Arial, SansSerif;
        }

        .table {
            width: 100%;
            display: flex;
            position: absolute;
        }

        .day {
            flex: 1;
        }

        .hours {
            flex: 0.5;
            white-space: nowrap;
        }

        .header {
            font-weight: bold;
            height: 20pt;
            text-align: center;
        }

        .timerange {
            text-align: right;
            height: 60px;
            line-height: 60px;
            vertical-align: center;
            margin-right: 20px;
            margin-bottom: 1pt;
            margin-top: 1pt;
        }

        .slot {
            border: 1pt solid black;
            border-radius: 5px;
            height: 60px;
            line-height: 60px;
            text-align: center;
            white-space: nowrap;
        }
        .deleteButton {
            margin-left: 20px;
            display: inline-block;
        }

        .reserved {
            background-color: lightcoral;
        }

        .reserved-myself {
            background-color: palegoldenrod;
        }

        #formLayout {
            position: absolute;
            z-index: 10;
            width: 100%;
        }
        #bookingForm {
            padding: 15px;
            margin-left: auto;
            margin-right: auto;
            margin-top: 50px;
            display: block;
            width: 400pt;
            font-size: 16pt;
            border: 1pt solid black;
            border-radius: 5pt;
            background: white;
        }
        td {
            padding: 5px;
        }
        input {
            width: 250pt;
        }
        .closeButton {
            text-align: right;
        }
    </style>
    <script>
        function displayForm(user, room_id, room, date, displayedDate, slot_id, slot) {
            document.getElementById("name").value = user;
            document.getElementById("room_id").value = room_id;
            document.getElementById("room").value = room;
            document.getElementById("date").value = date;
            document.getElementById("displayedDate").value = displayedDate;
            document.getElementById("slot_id").value = slot_id;
            document.getElementById("slot").value = slot;
            document.getElementById("formLayout").style.visibility = "visible";
        }
        function displayDeletionForm(booking_id, user, room_id, room, date, displayedDate, slot_id, slot)
        {
            displayForm(user, room_id, room, date, displayedDate, slot_id, slot);
            document.getElementById("to_delete_booking_id").value = booking_id;
            document.getElementById("form_submit").value = "Supprimer la réservation"
        }

        function displayAdditionForm(user, room_id, room, date, displayedDate, slot_id, slot)
        {
            displayForm(user, room_id, room, date, displayedDate, slot_id, slot);
            document.getElementById("to_delete_booking_id").value = null;
            document.getElementById("form_submit").value = "Enregistrer la réservation"
        }

        // Taken from https://stackoverflow.com/a/10997390
        function updateURLParameter(url, param, paramVal)
        {
            var TheAnchor = null;
            var newAdditionalURL = "";
            var tempArray = url.split("?");
            var baseURL = tempArray[0];
            var additionalURL = tempArray[1];
            var temp = "";

            if (additionalURL)
            {
                var tmpAnchor = additionalURL.split("#");
                var TheParams = tmpAnchor[0];
                TheAnchor = tmpAnchor[1];
                if(TheAnchor)
                    additionalURL = TheParams;

                tempArray = additionalURL.split("&");

                for (var i=0; i<tempArray.length; i++)
                {
                    if(tempArray[i].split('=')[0] != param)
                    {
                        newAdditionalURL += temp + tempArray[i];
                        temp = "&";
                    }
                }
            }
            else
            {
                var tmpAnchor = baseURL.split("#");
                var TheParams = tmpAnchor[0];
                TheAnchor  = tmpAnchor[1];

                if(TheParams)
                    baseURL = TheParams;
            }

            if(TheAnchor)
                paramVal += "#" + TheAnchor;

            var rows_txt = temp + "" + param + "=" + paramVal;
            return baseURL + "?" + newAdditionalURL + rows_txt;
        }
    </script>
</head>
<body>
<div style="padding: 10px; height: 50px; font-size: 14pt; width: 100%">
    <form style="position: absolute; left: 10px">
        <label for="roomSelection">Local&nbsp;:</label>
        <select id="roomSelection" autocomplete="off" onchange="location.href = updateURLParameter(location.href, 'room_id', document.getElementById('roomSelection').value)">
            <?php
                foreach ($available_rooms as $room) {
                    if ($room == $current_room)
                        echo "<option value=\"$room->id\" selected=\"selected\">$room->name</option>";
                    else
                        echo "<option value=\"$room->id\">$room->name</option>";
                }
            ?>
        </select>
    </form>
    <div style="width: 100%; display: flex; justify-content: center">
        <div>
            <?php
                echo "<i class=\"glyphicon glyphicon-chevron-left\" onclick=\"location.href = updateURLParameter(location.href, 'weekstart', '".date_format($weekstart->add(date_interval_create_from_date_string("-7 days")), "Y-m-d")."')\"></i>";
            ?>
            <span class="week">
                <?php
                echo "Semaine du ".date_format($weekstart, "d/m/Y")." au ".date_format($weekstop, "d/m/Y");
                ?>
            </span>
            <?php
                echo "<i class=\"glyphicon glyphicon-chevron-right\" onclick=\"location.href = updateURLParameter(location.href, 'weekstart', '".date_format($weekstart->add(date_interval_create_from_date_string("7 days")), "Y-m-d")."')\"></i>";
            ?>
        </div>
        <div style="position: absolute; right: 10px">
            <?php echo $user->name; ?>
            <a href="logout.php" class="btn btn-primary">Se déconnecter</a>
        </div>
    </div>
</div>
<div class="table">
    <div class="hours">
        <div class="header"></div>
        <?php
        foreach ($slots as $slot) {
            $start = $slot->start_time->format('H:i');
            $stop = $slot->stop_time->format('H:i');
            echo "<div class=\"timerange\">$start - $stop</div>";
        }
        ?>
    </div>
    <?php
    foreach ($days as $day_idx=>$day) {
        $current_day = $weekstart->add(date_interval_create_from_date_string("$day_idx days"));
        $str_current_day = $current_day->format('Y-m-d');
        echo "<div class=\"day\">\n";
        echo "  <div class=\"header\">$day ".date_format($current_day, "d/m")."</div>\n";
        foreach ($slots as $slot) {
            $start = $slot->start_time->format('H:i');
            $stop = $slot->stop_time->format('H:i');
            if (array_key_exists($str_current_day, $reversations) && array_key_exists($slot->id, $reversations[$str_current_day])) {
                [$reservation_name, $booking_id] = $reversations[$str_current_day][$slot->id];
                if ($reservation_name == $user->name)
                    $className = "reserved-myself";
                else
                    $className = "reserved";
                if ($reservation_name == $user->name || $user->is_admin)
                    $deletionCode = "<div class=\"deleteButton\" onclick=\"displayDeletionForm($booking_id, '$reservation_name', $current_room->id, '$current_room->name', '$str_current_day', '".date_format($current_day, "d/m/Y")."', $slot->id, '$start - $stop');\"><i class=\"glyphicon glyphicon-remove\"></i></div>";
                else
                    $deletionCode = "";

                echo "  <div class=\"slot $className\">$reservation_name$deletionCode</div>\n";
            }
            else
                echo "  <div class=\"slot\" onclick=\"displayAdditionForm('$user->name', $current_room->id, '$current_room->name', '$str_current_day', '".date_format($current_day, "d/m/Y")."', $slot->id, '$start - $stop');\"></div>\n";
        }
        echo "</div>\n";
    }
    ?>
</div>
<div id="formLayout" style="visibility: hidden">
    <form id="bookingForm" >
        <table>
            <tr>
                <td></td>
                <td class="closeButton"><i class="glyphicon glyphicon-remove" onclick="document.getElementById('formLayout').style.visibility='hidden'"></i></td>
            </tr>
            <tr>
                <td><label for="name">Nom&nbsp;:</label></td>
                <td>
                    <input type="text" id="name" readonly="readonly"/>
                </td>
            </tr>
            <tr>
                <td><label for="room">Local&nbsp;:</label></td>
                <td>
                    <input type="hidden" id="room_id" name="room_id" readonly="readonly"/>
                    <input type="text" id="room" readonly="readonly"/>
                </td>
            </tr>
            <tr>
                <td><label for="displayedDate">Date&nbsp;:</label></td>
                <td>
                    <input type="hidden" id="date" name="date" readonly="readonly"/>
                    <input type="text" id="displayedDate" readonly="readonly"/>
                </td>
            </tr>
            <tr>
                <td><label for="slot">Plage horaire&nbsp;:</label></td>
                <td>
                    <input type="hidden" id="slot_id" name="slot_id" readonly="readonly"/>
                    <input type="text" id="slot" readonly="readonly"/>
                </td>
            </tr>
        </table>
        <input type="hidden" id="to_delete_booking_id" name="to_delete_booking_id" readonly="readonly"/>
        <input type="submit" value="" id="form_submit" style="margin-top: 10pt; width: 100%"/>
    </form>
</div>
</body>
</html>