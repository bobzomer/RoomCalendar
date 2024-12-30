<?php
require_once '../calendar_database.php';
require_once '../authentication.php';
require_once '../settings.php';

include '../error_handling.php';

try {
    session_start();

    $conn = new CalendarDatabase();
    $settings = getSettings($conn);

    [$user_name, $email] = checkOrAuthenticate($settings['AppId'], $settings['AppSecret'], $settings['Tenant']);
    if (array_key_exists('code', $_GET)) {
        header('Location: ' . getCurrentUrl());
        exit();
    }
    if (str_contains($email, '@student')) {
        echo <<<html
<!DOCTYPE html>
<html lang="en"><head><title>Erreur</title></head>
<body>
<h1>Interdit</h1>
Page interdite aux élèves
</body>
</html>
html;
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
            $description = $_GET['description'];
            $conn->addBooking($room_id, $user->id, $slot_id, $date, $description);
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
            font-family: Arial, SansSerif, sans-serif;
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
        .tooltiptext {
            visibility: hidden;
            text-align: center;
            border-radius: 6px;
            border-style: solid;
            border-width: 1px;
            padding: 5px;
            background-color: white;
            color: black;
            line-height: normal;

            /* Position the tooltip */
            z-index: 1;
            position: absolute;
        }

        .slot:hover .tooltiptext {
            visibility: visible;
        }
    </style>
    <script src="./tools.js" type="text/javascript"></script>
    <script>
        function displayForm(user, room_id, room, date, displayedDate, slot_id, slot, description) {
            document.getElementById("name").value = user;
            document.getElementById("room_id").value = room_id;
            document.getElementById("room").value = room;
            document.getElementById("date").value = date;
            document.getElementById("displayedDate").value = displayedDate;
            document.getElementById("slot_id").value = slot_id;
            document.getElementById("slot").value = slot;
            document.getElementById("description").value = description === undefined ? "" : description;
            document.getElementById("formLayout").style.visibility = "visible";
        }
        function displayDeletionForm(booking_id, user, room_id, room, date, displayedDate, slot_id, slot, description)
        {
            displayForm(user, room_id, room, date, displayedDate, slot_id, slot, description);
            document.getElementById("description").readOnly = true;
            document.getElementById("description").placeholder = "";
            document.getElementById("to_delete_booking_id").value = booking_id;
            document.getElementById("form_submit").value = "Supprimer la réservation";
        }

        function displayAdditionForm(user, room_id, room, date, displayedDate, slot_id, slot)
        {
            displayForm(user, room_id, room, date, displayedDate, slot_id, slot, "");
            document.getElementById("description").readOnly = false;
            document.getElementById("description").placeholder = "facultatif";
            document.getElementById("to_delete_booking_id").value = null;
            document.getElementById("form_submit").value = "Enregistrer la réservation";
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
                [$reservation_name, $booking_id, $description] = $reversations[$str_current_day][$slot->id];
                if ($reservation_name == $user->name)
                    $className = "reserved-myself";
                else
                    $className = "reserved";
                if ($reservation_name == $user->name || $user->is_admin)
                    $deletionCode = "<div class=\"deleteButton\" onclick=\"displayDeletionForm($booking_id, '$reservation_name', $current_room->id, '$current_room->name', '$str_current_day', '".date_format($current_day, "d/m/Y")."', $slot->id, '$start - $stop', '$description');\"><i class=\"glyphicon glyphicon-remove\"></i></div>";
                else
                    $deletionCode = "";

                if (empty($description))
                    $tooltip = "";
                else {
                    $tooltip = "<div class=\"tooltiptext\">Réservé par: $reservation_name<br/>Description: $description</div>";
                    $reservation_name = $description;
                }

                echo "  <div class=\"slot $className\">$reservation_name$deletionCode$tooltip</div>\n";
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
                    <input type="text" id="name" readonly/>
                </td>
            </tr>
            <tr>
                <td><label for="room">Local&nbsp;:</label></td>
                <td>
                    <input type="hidden" id="room_id" name="room_id" readonly/>
                    <input type="text" id="room" readonly/>
                </td>
            </tr>
            <tr>
                <td><label for="displayedDate">Date&nbsp;:</label></td>
                <td>
                    <input type="hidden" id="date" name="date" readonly/>
                    <input type="text" id="displayedDate" readonly/>
                </td>
            </tr>
            <tr>
                <td><label for="slot">Plage horaire&nbsp;:</label></td>
                <td>
                    <input type="hidden" id="slot_id" name="slot_id" readonly/>
                    <input type="text" id="slot" readonly/>
                </td>
            </tr>
            <tr>
                <td><label for="description">Description&nbsp;:</label></td>
                <td>
                    <input type="text" id="description" name="description"/>
                </td>
            </tr>
        </table>
        <input type="hidden" id="to_delete_booking_id" name="to_delete_booking_id" readonly/>
        <input type="submit" value="" id="form_submit" style="margin-top: 10pt; width: 100%"/>
    </form>
</div>
</body>
</html>