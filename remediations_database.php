<?php
require_once 'vendor/autoload.php';
require_once 'database_access.php';

class Module
{
    /** @readonly */
    public int $id;
    /** @readonly */
    public string $name;
    /** @readonly */
    public string|null $description;
    /** @readonly */
    public DateTimeImmutable|null $inscription_limit;
    /** @readonly */
    public int|null $participants_limit;
    /** @readonly */
    public array|null $sessions;
    /** @readonly */
    public DateTimeImmutable|null $start;
    /** @readonly */
    public DateTimeImmutable|null $end;

    public function __construct(int $id, string $name, string|null $description,
                                DateTimeImmutable $inscription_limit, int|null $participants_limit,
                                array|null $sessions = null) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->inscription_limit = $inscription_limit;
        $this->participants_limit = $participants_limit;
        $this->sessions = $sessions;
        if ($sessions !== null) {
            $this->start = min(array_map(function(Session $session) { return $session->start_time; }, $sessions));
            $this->end = max(array_map(function(Session $session) { return $session->start_time; }, $sessions));
        }
    }
}

class Session
{
    /** @readonly */
    public int $id;
    /** @readonly */
    public DateTimeImmutable $start_time;
    /** @readonly */
    public int $duration;

    public function __construct(int $id, string $start_time, int $duration) {
        $this->id = $id;
        $this->start_time = new DateTimeImmutable($start_time);
        $this->duration = $duration;
    }
}

class Participant
{
    /** @readonly */
    public int $id;
    /** @readonly */
    public string $name;
    /** @readonly */
    public bool $is_teacher;

    public function __construct(int $id, string $name, bool $is_teacher = false) {
        $this->id = $id;
        $this->name = $name;
        $this->is_teacher = $is_teacher;
    }
}

class RemediationsDatabase extends Database
{
    protected function createTables() : void
    {
        //language=sql
        $query = <<<EOQ
CREATE TABLE modules (
    id int NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL,
    description varchar(5000),
    inscription_limit datetime NOT NULL,
    participants_limit int NULL,
    PRIMARY KEY (id),
    UNIQUE (name)
);
CREATE TABLE sessions (
    id int NOT NULL AUTO_INCREMENT,
    module_id int NOT NULL,
    timeslot datetime NOT NULL,
    duration_s int NOT NULL,
    location varchar(100) NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (module_id) REFERENCES modules(id)
);
CREATE TABLE participants (
    id int NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL,
    is_teacher bool NOT NULL,
    PRIMARY KEY (id)
);
CREATE TABLE inscriptions (
    id int NOT NULL AUTO_INCREMENT,
    module_id int NOT NULL,
    participant_id int NOT NULL,
    comment varchar(5000) NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (module_id) REFERENCES modules(id),
    FOREIGN KEY (participant_id) REFERENCES participants(id)
);
CREATE TABLE presences (
    id int NOT NULL AUTO_INCREMENT,
    sessions_id int NOT NULL,
    participant_id int NOT NULL,
    comment varchar(500) NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (sessions_id) REFERENCES sessions(id),
    FOREIGN KEY (participant_id) REFERENCES participants(id),
    UNIQUE (room_id, slot_id, day)
);
EOQ;
        $this->conn->exec($query);
    }

    public function getSettings() : array
    {
        $settings = [];
        $data = $this->conn->query("SELECT id, name, value FROM settings", PDO::FETCH_ASSOC)->fetchAll();
        foreach ($data as $row) {
            $settings[$row['name']] = $row['value'];
        }
        return $settings;
    }

    public function setSettings(array $settings) : array
    {
        foreach ($settings as $name => $value) {
            $stmt = $this->conn->prepare("REPLACE INTO settings SET name = ?, value = ?;");
            $stmt->execute([$name, $value]);
        }
        return $this->getSettings();
    }

    /**
     * @return Room[]
     */
    public function getRoomList() : array
    {
        $stmt = $this->conn->query("SELECT id, name, location FROM rooms WHERE is_enabled = 1", PDO::FETCH_ASSOC);
        return array_map(fn($row): Room => new Room($row['id'], $row['name'], $row['location']), $stmt->fetchAll());
    }

    /**
     * @return Slot[]
     */
    public function getSlots() : array
    {
        $stmt = $this->conn->query("SELECT id, start_time, stop_time FROM slots", PDO::FETCH_ASSOC);
        return array_map(fn($row): Slot => new Slot($row['id'], $row['start_time'], $row['stop_time']), $stmt->fetchAll());
    }

    public function getUserByName(string $user_name, string $email, bool $create_if_absent = true) : Participant
    {
        if (!isset($user_name) || ctype_space($user_name))
            throw new Exception("Empty user name");
        $query = <<<EOQ
SELECT id, name, is_admin
FROM users
WHERE name = ?
EOQ;
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row == false) {
            if ($create_if_absent) {
                $query = <<<sql
INSERT INTO participants (name, is_teacher) VALUES (?)
sql;
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$user_name, !str_contains($email, '@student')]);
                return $this->getUserByName($user_name, $email,false);
            }
            throw new Exception("Unable to find user name " . $user_name);
        }
        return new Participant($row['id'], $row['name'], $row['is_teacher']);
    }

    public function getBookingUser(int $booking_id) : User
    {
        $query = <<<EOQ
SELECT users.id AS user_id, users.name AS user_name, users.is_admin as is_admin
FROM room_bookings
JOIN users ON room_bookings.user_id = users.id
WHERE room_bookings.id = $booking_id
EOQ;
        $row = $this->conn->query($query, PDO::FETCH_ASSOC)->fetch();
        return new User($row['user_id'], $row['user_name'], $row['is_admin']);
    }

    public function getBookings(int $room_id, DateTimeInterface $start_date, DateTimeInterface $stop_date) : array
    {
        $result = array();
        $one_day = new DateInterval("P1D");
        for ($day = DateTimeImmutable::createFromInterface($start_date); $day <= $stop_date; $day = $day->add($one_day)) {
            $result[$day->format('Y-m-d')] = array();
        }
        $str_start_date = $start_date->format('Y-m-d');
        $str_stop_date = $stop_date->format('Y-m-d');

        $query = <<<EOQ
SELECT room_bookings.id AS id, room_bookings.day AS day,
       room_bookings.description AS description,
       rooms.id AS room_id, rooms.name AS room_name, rooms.location AS room_location,
       users.id AS user_id, users.name AS user_name,
       slots.id AS slot_id, slots.start_time AS slot_start_time, slots.stop_time AS slot_stop_time
FROM room_bookings
JOIN rooms ON room_bookings.room_id = rooms.id
JOIN users ON room_bookings.user_id = users.id
JOIN slots ON room_bookings.slot_id = slots.id
WHERE rooms.id = $room_id AND room_bookings.day >= '$str_start_date' AND room_bookings.day <= '$str_stop_date'
EOQ;
        $stmt = $this->conn->query($query, PDO::FETCH_ASSOC);

        foreach ($stmt->fetchAll() as $row) {
            $result[$row['day']][$row['slot_id']] = [$row['user_name'], $row['id'], $row['description']];
        }
        return $result;
    }

    public function addBooking(int $room_id, int $user_id, int $slot_id, DateTimeInterface $day, string $description): void
    {
        $strDay = $day->format('Y-m-d');
        $query = <<<EOQ
INSERT INTO room_bookings(room_id, user_id, slot_id, day, description)
VALUES ($room_id, $user_id, $slot_id, '$strDay', ?);
EOQ;
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$description]);
    }

    public function deleteBooking(int $booking_id): void
    {
        $query = <<<EOQ
DELETE FROM room_bookings
WHERE id = $booking_id;
EOQ;
        $this->conn->exec($query);
    }
}
