<?php
// src/includes/database.php

class Database {
    private $pdo;
    private $last_insert_id;

    public function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // In a real application, you would log this error and show a generic message.
            // For debugging, we'll show the error.
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check the logs.");
        }
    }

    /**
     * A versatile method to execute any SQL query.
     * Uses prepared statements to prevent SQL injection.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params The parameters to bind to the query.
     * @return PDOStatement Returns the PDOStatement object after execution.
     */
    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            // Check if the query was an INSERT and store the last insert ID
            if (stripos(trim($sql), 'INSERT') === 0) {
                $this->last_insert_id = $this->pdo->lastInsertId();
            }

            return $stmt;
        } catch (PDOException $e) {
            // Log the error for administrators
            error_log("Database query failed: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . implode(", ", $params));
            // You could throw an exception or handle it as needed.
            // For now, we'll return false to indicate failure.
            return false;
        }
    }

    /**
     * Retrieves the ID of the last inserted row.
     *
     * @return string The ID of the last inserted row.
     */
    public function lastInsertId() {
        return $this->last_insert_id;
    }

    /**
     * A specific helper function to get a single setting value.
     *
     * @param string $setting_name The name of the setting to retrieve.
     * @param mixed $default The default value to return if the setting is not found.
     * @return mixed The value of the setting or the default value.
     */
    public function getSetting($setting_name, $default = null) {
        $stmt = $this->executeQuery("SELECT value FROM settings WHERE name = ? LIMIT 1", [$setting_name]);
        if ($stmt && $stmt->rowCount() > 0) {
            return $stmt->fetchColumn();
        }
        return $default;
    }

    /**
     * A specific helper function to update or insert a setting value.
     *
     * @param string $setting_name The name of the setting.
     * @param mixed $value The value of the setting.
     * @return bool True on success, false on failure.
     */
    public function setSetting($setting_name, $value) {
        // ON DUPLICATE KEY UPDATE is a safe and efficient way to handle insert/update logic
        $stmt = $this->executeQuery(
            "INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?",
            [$setting_name, $value, $value]
        );
        return $stmt !== false;
    }


}
