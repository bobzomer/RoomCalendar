<?php
require_once 'vendor/autoload.php';
require_once 'database_access.php';

class Room
{
    /** @readonly */
    public int $id;
    /** @readonly */
    public string $name;
    /** @readonly */
    public string|null $location;

    public function __construct(int $id, string $name, string|null $location) {
        $this->id = $id;
        $this->name = $name;
        $this->location = $location;
    }
}

class Slot
{
    /** @readonly */
    public int $id;
    /** @readonly */
    public DateTimeImmutable $start_time;
    /** @readonly */
    public DateTimeImmutable $stop_time;

    public function __construct(int $id, string $start_time, string $stop_time) {
        $this->id = $id;
        $this->start_time = new DateTimeImmutable($start_time);
        $this->stop_time = new DateTimeImmutable($stop_time);
    }
}

class User
{
    /** @readonly */
    public int $id;
    /** @readonly */
    public string $name;
    /** @readonly */
    public bool $is_admin;

    public function __construct(int $id, string $name, bool $is_admin = false) {
        $this->id = $id;
        $this->name = $name;
        $this->is_admin = $is_admin;
    }
}

class CalendarDatabase extends Database
{
    protected function createTables() : void
    {
        //language=sql
        $query = <<<EOQ
CREATE TABLE rooms (
    id int NOT NULL AUTO_INCREMENT,
    name varchar(50) NOT NULL,
    location varchar(5000),
    is_enabled bit DEFAULT 1,
    PRIMARY KEY (id)
);
CREATE TABLE users (
    id int NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL,
    is_admin boolean NOT NULL DEFAULT false,
    PRIMARY KEY (id)
);
CREATE TABLE slots (
    id int NOT NULL AUTO_INCREMENT,
    start_time time NOT NULL,
    stop_time time NOT NULL,
    PRIMARY KEY (id)
);
CREATE TABLE room_bookings (
    id int NOT NULL AUTO_INCREMENT,
    room_id int NOT NULL,
    user_id int NOT NULL,
    slot_id int NOT NULL,
    day date NOT NULL,
    description varchar(100) NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (slot_id) REFERENCES slots(id),
    UNIQUE (room_id, slot_id, day)
);
INSERT INTO settings (name, value)
VALUES ('DatabaseVersion', '1');
INSERT INTO slots (start_time, stop_time)
VALUES ('08:10', '09:00'),
       ('09:00', '09:50'),
       ('09:50', '10:40'),
       ('11:00', '11:50'),
       ('11:50', '12:40'),
       ('12:40', '13:30'),
       ('13:35', '14:25'),
       ('14:25', '15:15'),
       ('15:15', '16:05');
INSERT INTO rooms (name)
VALUES ('C22'), ('C23'), ('C24');       
EOQ;
        $this->conn->exec($query);
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

    public function getUserByName(string $user_name, bool $create_if_absent = true) : User
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
INSERT INTO users (name) VALUES (?)
sql;
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$user_name]);
                return $this->getUserByName($user_name, false);
            }
            throw new Exception("Unable to find user name " . $user_name);
        }
        return new User($row['id'], $row['name'], $row['is_admin']);
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
