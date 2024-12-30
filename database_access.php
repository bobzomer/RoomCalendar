<?php
use Dotenv\Dotenv;
use function Sentry\captureException;

require_once 'vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

abstract class Database
{
    protected PDO $conn;

    public function __construct()
    {
        $servername = $_ENV['DATABASE_SERVERNAME'];
        $db = $_ENV['DATABASE_DB'];
        try {
            $this->conn = new PDO("mysql:host=$servername;dbname=$db", $_ENV['DATABASE_USERNAME'], $_ENV['DATABASE_PASSWORD']);
            // set the PDO error mode to exception
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            try {
                $this->getSettings();
            }
            catch (PDOException $exc2) {
                captureException($exc2);
                $this->createTables();
            }
        }
        catch (PDOException $exc) {
            captureException($exc);
            $this->conn = new PDO("mysql:host=$servername", $_ENV['DATABASE_USERNAME'], $_ENV['DATABASE_PASSWORD']);
            // set the PDO error mode to exception
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createDatabase();
        }
    }

    protected function createDatabase() : void
    {
        $db = $_ENV['DATABASE_DB'];
        //language=sql
        $query = <<<EOQ
CREATE DATABASE $db;
USE $db;
CREATE TABLE settings (
    id int NOT NULL AUTO_INCREMENT,
    name varchar(50) NOT NULL,
    value varchar(5000),
    PRIMARY KEY (id),
    UNIQUE (name)
);
EOQ;
        $this->conn->exec($query);
        $this->createTables();
    }

    protected abstract function createTables();

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

}
