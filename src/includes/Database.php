<?php

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            // Log the detailed error instead of echoing
            error_log("Connection Error: " . $exception->getMessage());
            // Return null or handle the error as per application's needs
            return null;
        }
        return $this->conn;
    }
}
