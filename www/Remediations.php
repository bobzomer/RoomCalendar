<?php
require_once '../remediations_database.php';
require_once '../authentication.php';
require_once '../settings.php';

include '../error_handling.php';


$participant = new Participant(0, "Bruno Obsomer", false);
$modules = [
    new Module(0, "Mathématiques", "Blablabla",
        new DateTimeImmutable("2024-12-24"), 15,
        [new Session(0, "2024-12-24", 50 * 60),
            new Session(0, "2024-12-31", 50 * 60),
            new Session(0, "2025-02-06", 50 * 60)]),
    new Module(0, "Mathématiques", "Blablabla",
        new DateTimeImmutable("2023-12-24"), 15,
        [new Session(0, "2023-12-24", 50 * 60),
            new Session(0, "2023-12-31", 50 * 60),
            new Session(0, "2024-02-06", 50 * 60)]),
    new Module(0, "Mathématiques", "Blablabla",
        new DateTimeImmutable("2025-12-24"), 15,
        [new Session(0, "2025-12-24", 50 * 60),
            new Session(0, "2025-12-31", 50 * 60),
            new Session(0, "2026-02-06", 50 * 60)]),
]

/*
try {
    session_start();

    $conn = new RemediationsDatabase();
    $settings = getSettings($conn);

    [$user_name, $email] = checkOrAuthenticate($settings['AppId'], $settings['AppSecret'], $settings['Tenant']);
    if (array_key_exists('code', $_GET)) {
        header('Location: ' . getCurrentUrl());
        exit();
    }
    $participant = $conn->getUserByName($user_name, $email);

}
catch (Exception $exc) {
    \Sentry\captureException($exc);
    throw $exc;
}*/
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <title>Remédiations</title>

    <style>
        * {
            font-family: Arial, SansSerif, sans-serif;
        }
    </style>
    <script src="./tools.js" type="text/javascript"></script>
</head>
<body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<h1>Remédiations pour <?php echo $participant->name; ?></h1>

<div class="accordion" id="sectionsAccordion">
    <div class="accordion-item">
        <h5 class="accordion-header" id="headingOnGoing">
            <button class="accordion-button" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapseOnGoing"
                    aria-expanded="true" aria-controls="collapseOnGoing">
                Modules en cours
            </button>
        </h5>

        <div id="collapseOnGoing" class="accordion-collapse collapse show" aria-labelledby="headingOnGoing" data-bs-parent="#sectionsAccordion">
            <div class="accordion-body">
                <div class="accordion" id="onGoingAccordion">
                <?php
                $now = new DateTimeImmutable();
                foreach ($modules as $module) {
                    if ($module->start > $now || $module->end < $now) {
                        continue;
                    }
                    echo '<div class="accordion-item">';
                    echo ' <h6 class="accordion-header">';
                    echo '  <button class="accordion-button" type="button" data-bs-toggle="collapse" '.
                        'data-bs-parent="#onGoingAccordion" aria-expanded="true" '.
                        'aria-controls="collapse-'.strval($module->id).'" data-bs-target="#collapse-'.strval($module->id).'" >';
                    echo $module->name." (".strval(count($module->sessions))." sessions - ".date_format($module->start, "d/m/Y")." - ".date_format($module->end, "d/m/Y").")";
                    echo '  </button>';
                    echo ' </h6>';
                    echo ' <div id="collapse-'.strval($module->id).'" class="accordion-collapse collapse" aria-labelledby="heading-'.strval($module->id).'" data-bs-parent="#onGoingAccordion">';
                    echo '  <div class="accordion-body">';
                    echo '   <table class="table">';
                    echo '    <thead><tr><th>Date</th><th>Durée</th><th>Présence</th><th>Commentaire</th></tr></thead>';
                    echo '    <tbody>';
                    foreach ($module->sessions as $session) {
                        echo '      <tr><td>'.date_format($session->start_time, "d/m/Y").'</td><td>'.strval($session->duration / 50).' minutes</td><td>?</td><td>?</td></tr>';
                    }
                    echo '    </tbody>';
                    echo '   </table>';
                    echo '  </div>';
                    echo ' </div>';
                    echo '</div>';
                }
                ?>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h5 class="accordion-header" id="headingFuture">
            <button class="accordion-button" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapseFuture" data-bs-parent="#sectionsAccordion"
                    aria-expanded="true" aria-controls="collapseFuture">
                Modules futurs
            </button>
        </h5>

        <div id="collapseFuture" class="accordion-collapse collapse" aria-labelledby="headingFuture" data-bs-parent="#sectionsAccordion">
            <div class="accordion-body">
                <div class="accordion" id="futureAccordion">
                    <?php
                    $now = new DateTimeImmutable();
                    foreach ($modules as $module) {
                        if ($module->start <= $now) {
                            continue;
                        }
                        echo '<div class="accordion-item">';
                        echo ' <h6 class="accordion-header">';
                        echo '  <button class="accordion-button" type="button" data-bs-toggle="collapse" '.
                            'data-bs-parent="#futureAccordion" aria-expanded="true" '.
                            'aria-controls="collapse-'.strval($module->id).'" data-bs-target="#collapse-'.strval($module->id).'" >';
                        echo $module->name." (".strval(count($module->sessions))." sessions - ".date_format($module->start, "d/m/Y")." - ".date_format($module->end, "d/m/Y").")";
                        echo '  </button>';
                        echo ' </h6>';
                        echo ' <div id="collapse-'.strval($module->id).'" class="accordion-collapse collapse" aria-labelledby="heading-'.strval($module->id).'" data-bs-parent="#futureAccordion">';
                        echo '  <div class="accordion-body">';
                        echo '   <table class="table">';
                        echo '    <thead><tr><th>Date</th><th>Durée</th></tr></thead>';
                        echo '    <tbody>';
                        foreach ($module->sessions as $session) {
                            echo '      <tr><td>'.date_format($session->start_time, "d/m/Y").'</td><td>'.strval($session->duration / 60).' minutes</td></tr>';
                        }
                        echo '    </tbody>';
                        echo '   </table>';
                        echo '  </div>';
                        echo ' </div>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h5 class="accordion-header" id="headingPast">
            <button class="accordion-button" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapsePast" aria-expanded="true" aria-controls="collapsePast">
                Modules passés
            </button>
        </h5>

        <div id="collapsePast" class="accordion-collapse collapse" aria-labelledby="headingPast" data-bs-parent="#sectionsAccordion">
            <div class="accordion-body">
                <div class="accordion" id="pastAccordion">
                    <?php
                    $now = new DateTimeImmutable();
                    foreach ($modules as $module) {
                        if ($module->end >= $now) {
                            continue;
                        }
                        echo '<div class="accordion-item">';
                        echo ' <h6 class="accordion-header">';
                        echo '  <button class="accordion-button" type="button" data-bs-toggle="collapse" '.
                            'data-bs-parent="#pastAccordion" aria-expanded="true" '.
                            'aria-controls="collapse-'.strval($module->id).'" data-bs-target="#collapse-'.strval($module->id).'" >';
                        echo $module->name." (".strval(count($module->sessions))." sessions - ".date_format($module->start, "d/m/Y")." - ".date_format($module->end, "d/m/Y").")";
                        echo '  </button>';
                        echo ' </h6>';
                        echo ' <div id="collapse-'.strval($module->id).'" class="accordion-collapse collapse" aria-labelledby="heading-'.strval($module->id).'" data-bs-parent="#pastAccordion">';
                        echo '  <div class="accordion-body">';
                        echo '   <table class="table">';
                        echo '    <thead><tr><th>Date</th><th>Durée</th><th>Présence</th><th>Commentaire</th></tr></thead>';
                        echo '    <tbody>';
                        foreach ($module->sessions as $session) {
                            echo '      <tr><td>'.date_format($session->start_time, "d/m/Y").'</td><td>'.strval($session->duration / 50).' minutes</td><td>?</td><td>?</td></tr>';
                        }
                        echo '    </tbody>';
                        echo '   </table>';
                        echo '  </div>';
                        echo ' </div>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>