<!DOCTYPE html>
<?php
$available_rooms = array("Toilettes", "Salle de bains", "Sauna");
$slots = array("8:10", "9:00", "9:50", "11:00", "11:50", "12:40", "13:35", "14:25", "15:15");
$days = array("Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi");

if (array_key_exists('room', $_GET))
    $current_room = $_GET['room'];
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
    ->sub(date_interval_create_from_date_string(($dayofweek - 1)." days"));

$reversations = array(
    "Lundi" => array(
        "9:50" => "Lorianne Ruelle"
    ),
    "Jeudi" => array(
        "11:00" => "Bruno Obsomer"
    )
);
?>
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
        }

        .reserved {
            background-color: lightcoral;
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
        function displayForm(room, date, slot) {
            document.getElementById("name").value = "Bruno Obsomer";
            document.getElementById("room").value = room;
            document.getElementById("date").value = date;
            document.getElementById("slot").value = slot;
            document.getElementById("formLayout").style.visibility = "visible";
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
<div style="padding: 10px; height: 50px; font-size: 14pt;">
    <form style="position: absolute; left: 10px">
        <label for="roomSelection">Local&nbsp;:</label>
        <select id="roomSelection" name="roomSelection" autocomplete="off" onchange="location.href = updateURLParameter(location.href, 'room', document.getElementById('roomSelection').value)">
            <?php
                foreach ($available_rooms as $room) {
                    if ($room == $current_room)
                        echo "<option value=\"$room\" selected=\"selected\">$room</option>";
                    else
                        echo "<option value=\"$room\">$room</option>";
                }
            ?>
        </select>
    </form>
    <div style="position: absolute; right: 10px">
        <?php
            echo "<i class=\"glyphicon glyphicon-chevron-left\" onclick=\"location.href = updateURLParameter(location.href, 'weekstart', '".date_format($weekstart->add(date_interval_create_from_date_string("-7 days")), "Y-m-d")."')\"></i>";
        ?>
        <span class="week">
            <?php
            echo "Semaine du ".date_format($weekstart, "d/m/Y")." au ".date_format($weekstart->add(date_interval_create_from_date_string("4 days")), "d/m/Y");
            ?>
        </span>
        <?php
            echo "<i class=\"glyphicon glyphicon-chevron-right\" onclick=\"location.href = updateURLParameter(location.href, 'weekstart', '".date_format($weekstart->add(date_interval_create_from_date_string("7 days")), "Y-m-d")."')\"></i>";
        ?>
    </div>
</div>
<div class="table">
    <div class="hours">
        <div class="header"></div>
        <?php
        foreach ($slots as $slot) {
            list($hour, $min) = explode(":", $slot);
            $min = $min + 50;
            if ($min >= 60) {
                $hour++;
                $min = $min - 60;
            }
            $end_slot = sprintf("%d:%02d", $hour, $min);
            echo "<div class=\"timerange\">$slot - $end_slot</div>";
        }
        ?>
    </div>
    <?php
    foreach ($days as $day_idx=>$day) {
        $current_day = $weekstart->add(date_interval_create_from_date_string("$day_idx days"));
        echo "<div class=\"day\">";
        echo "<div class=\"header\">$day ".date_format($current_day, "d/m")."</div>";
        foreach ($slots as $slot) {
            if (array_key_exists($day, $reversations) && array_key_exists($slot, $reversations[$day]))
                echo "<div class=\"slot reserved\">" . $reversations[$day][$slot] . "</div>";
            else
                echo "<div class=\"slot\" onclick=\"displayForm('$current_room', '".date_format($current_day, "d/m/Y")."', '$slot');\"></div>";
        }
        echo "</div>";
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
                <td><input type="text" id="name" name="name" readonly="readonly"/></td>
            </tr>
            <tr>
                <td><label for="room">Local&nbsp;:</label></td>
                <td><input type="text" id="room" name="room" readonly="readonly"/></td>
            </tr>
            <tr>
                <td><label for="date">Date&nbsp;:</label></td>
                <td><input type="text" id="date" name="date" readonly="readonly"/></td>
            </tr>
            <tr>
                <td><label for="slot">Plage horaire&nbsp;:</label></td>
                <td><input type="text" id="slot" name="slot" readonly="readonly"/></td>
            </tr>
        </table>
        <input type="submit" value="Enregistrer la réservation" style="margin-top: 10pt; width: 100%"/>
    </form>
</div>
</body>
</html>