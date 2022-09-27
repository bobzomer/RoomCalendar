<!DOCTYPE html>
<?php
$slots = array("8:10", "9:00", "9:50", "11:00", "11:50", "12:40", "13:35", "14:25", "15:15");
$days = array("Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi");
$weekstart = date_create_immutable("2022-09-26");
$available_rooms = array("Toilettes", "Salle de bains", "Sauna");
$current_room = "Sauna";
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
    </script>
</head>
<body>
<div style="padding: 10px; height: 40px">
    <form style="position: absolute; left: 10px">
        <label for="roomSelection">Local&nbsp;:</label>
        <select id="roomSelection" name="roomSelection" autocomplete="off">
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
        <i class="glyphicon glyphicon-chevron-left"></i>
        <span class="week">
            <?php
            echo "Semaine du ".date_format($weekstart, "d/m/Y")." au ".date_format($weekstart->add(date_interval_create_from_date_string("4 days")), "d/m/Y");
            ?>
        </span>
        <i class="glyphicon glyphicon-chevron-right"></i>
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
<div id="formLayout" style="visibility: visible">
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
            <tr>
                <td><label type="until">Date de fin&nbsp;:</label></td>
                <td><input type="text" id="enddate" name="enddate"/></td>
            </tr>
        </table>
        <input type="submit" value="Enregistrer la réservation" style="margin-top: 10pt; width: 100%"/>
    </form>
</div>
</body>
</html>