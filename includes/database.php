<?php
/**
 * Denthub Dental Clinic - Database Connection
 * PostgreSQL with MySQLi Compatibility Layer
 */

require_once __DIR__ . '/config.php';

/**
 * MySQLi-compatible statement wrapper for PDO
 */
class MySQLiCompatibleStatement {
    private $pdoStatement;
    private $params = [];
    private $types = '';
    private $result = null;

    public function __construct($pdoStatement) {
        $this->pdoStatement = $pdoStatement;
    }

    public function bind_param($types, ...$params) {
        $this->types = $types;
        $this->params = $params;
        return true;
    }

    public function execute() {
        if (!empty($this->params)) {
            // Bind parameters by position (PDO uses 1-based indexing)
            for ($i = 0; $i < count($this->params); $i++) {
                $type = isset($this->types[$i]) ? $this->types[$i] : 's';
                $pdoType = PDO::PARAM_STR;
                
                if ($type === 'i') {
                    $pdoType = PDO::PARAM_INT;
                } elseif ($type === 'd') {
                    $pdoType = PDO::PARAM_STR; // PDO doesn't have PARAM_FLOAT
                } elseif ($type === 'b') {
                    $pdoType = PDO::PARAM_LOB;
                }
                
                $this->pdoStatement->bindValue($i + 1, $this->params[$i], $pdoType);
            }
        }
        return $this->pdoStatement->execute();
    }

    public function get_result() {
        $this->result = new MySQLiCompatibleResult($this->pdoStatement);
        return $this->result;
    }

    public function close() {
        $this->pdoStatement = null;
    }
}

/**
 * MySQLi-compatible result wrapper for PDO
 */
class MySQLiCompatibleResult {
    private $pdoStatement;
    private $rows = null;
    private $index = 0;

    public function __construct($pdoStatement) {
        $this->pdoStatement = $pdoStatement;
        // Check if it's a PDOStatement or already an array
        if ($pdoStatement instanceof PDOStatement) {
            $this->rows = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Already fetched
            $this->rows = is_array($pdoStatement) ? $pdoStatement : [];
        }
        $this->index = 0;
    }

    public function fetch_assoc() {
        if ($this->index < count($this->rows)) {
            return $this->rows[$this->index++];
        }
        return null;
    }

    public function fetch_all($mode = MYSQLI_ASSOC) {
        return $this->rows;
    }

    public function num_rows() {
        return count($this->rows);
    }

    public function __get($name) {
        if ($name === 'num_rows') {
            return count($this->rows);
        }
        return null;
    }
}

/**
 * MySQLi-compatible database connection wrapper
 */
class MySQLiCompatibleConnection {
    private $pdo;
    private $lastQuery;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function prepare($sql) {
        // Convert MySQL placeholders to PostgreSQL if needed
        $sql = $this->convertMySQLToPostgreSQL($sql);
        $this->lastQuery = $sql;
        $stmt = $this->pdo->prepare($sql);
        return new MySQLiCompatibleStatement($stmt);
    }

    public function query($sql) {
        // Convert MySQL syntax to PostgreSQL
        $sql = $this->convertMySQLToPostgreSQL($sql);
        $this->lastQuery = $sql;
        try {
            $stmt = $this->pdo->query($sql);
            return new MySQLiCompatibleResult($stmt);
        } catch (PDOException $e) {
            // Return empty result on error
            return new MySQLiCompatibleResult([]);
        }
    }

    public function real_escape_string($string) {
        return substr($this->pdo->quote($string), 1, -1); // Remove quotes
    }

    public function escape($string) {
        return $this->real_escape_string($string);
    }

    public function getLastInsertId() {
        // PostgreSQL uses sequences, get the last inserted ID
        // This assumes the last query was an INSERT
        $result = $this->pdo->query("SELECT LASTVAL()");
        return $result->fetchColumn();
    }

    public function getInsertId() {
        return $this->getLastInsertId();
    }

    public function __get($name) {
        if ($name === 'insert_id') {
            return $this->getLastInsertId();
        }
        return null;
    }

    /**
     * Convert MySQL-specific SQL to PostgreSQL
     */
    private function convertMySQLToPostgreSQL($sql) {
        // Convert UNSIGNED to INTEGER (PostgreSQL doesn't have UNSIGNED)
        $sql = preg_replace('/\bUNSIGNED\b/i', '', $sql);
        
        // Convert SUBSTRING with CAST for number extraction
        // MySQL: CAST(SUBSTRING(patient_number, 4) AS UNSIGNED)
        // PostgreSQL: CAST(SUBSTRING(patient_number FROM 4) AS INTEGER)
        $sql = preg_replace('/CAST\(SUBSTRING\(([^,]+),\s*(\d+)\)\s+AS\s+UNSIGNED\)/i', 
            'CAST(SUBSTRING($1 FROM $2) AS INTEGER)', $sql);
        
        // Convert NOW() to CURRENT_TIMESTAMP (both work, but CURRENT_TIMESTAMP is more standard)
        // Actually, both work in PostgreSQL, so we can leave NOW() as is
        
        // Convert LIMIT without OFFSET
        // Both MySQL and PostgreSQL support LIMIT, so no change needed
        
        return $sql;
    }

    public function close() {
        $this->pdo = null;
    }
}

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            // Build PDO connection string
            $dsn = sprintf(
                "pgsql:host=%s;port=%d;dbname=%s;sslmode=%s",
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_SSLMODE
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Wrap PDO in MySQLi-compatible interface
            $this->connection = new MySQLiCompatibleConnection($pdo);
            
        } catch (PDOException $e) {
            die("Database connection error: " . $e->getMessage());
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql) {
        return $this->connection->query($sql);
    }

    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    public function escape($string) {
        return $this->connection->escape($string);
    }

    public function getLastInsertId() {
        return $this->connection->getLastInsertId();
    }

    public function __destruct() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

// Global function for easy access
function getDB() {
    return Database::getInstance()->getConnection();
}
