<?php
/** MySQL connection settings. Environment overrides keep credentials out of source. */

class Database
{
    private $host = "localhost";
    private $port = "3306";
    private $db_name = "nexlace";
    private $username = "nexlace_app";
    private $password = "";
    private $conn;

    /**
     * Get Database Connection
     * @return PDO|null
     */
    public function getConnection()
    {
        $this->conn = null;
        $this->host = getenv('DB_HOST') ?: $this->host;
        $this->port = getenv('DB_PORT') ?: $this->port;
        $this->db_name = getenv('DB_NAME') ?: $this->db_name;
        $this->username = getenv('DB_USER') ?: $this->username;
        $password = getenv('DB_PASSWORD');
        if ($password !== false) {
            $this->password = $password;
        }


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
