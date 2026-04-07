<?php
/**
 * Database Configuration and Connection
 * MySQL Database: shashank
 * Table: Table_register
 */

class Database
{
    private $host = "localhost";
    private $port = "3306";
    private $db_name = "nexlace";
    private $username = "root";  // Change if you have a different username
    private $password = "";      // Add your MySQL password here if any
    private $conn;

    /**
     * Get Database Connection
     * @return PDO|null
     */
    public function getConnection()
    {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            return null;
        }

        return $this->conn;
    }
}