<?php
namespace App\Support;

/**
 * Database Wrapper Class
 */
class Database
{
    private $ketnoi;
    
    public function connect()
    {
        if (!$this->ketnoi) {
            $host = defined('DB_HOST') ? DB_HOST : ($_ENV['DB_HOST'] ?? 'localhost');
            $user = defined('DB_USER') ? DB_USER : ($_ENV['DB_USERNAME'] ?? 'root');
            $pass = defined('DB_PASS') ? DB_PASS : ($_ENV['DB_PASSWORD'] ?? '');
            $name = defined('DB_NAME') ? DB_NAME : ($_ENV['DB_DATABASE'] ?? '');

            $this->ketnoi = mysqli_connect($host, $user, $pass, $name) or die('Error => DATABASE');
            mysqli_query($this->ketnoi, "SET NAMES 'utf8mb4'");
            mysqli_query($this->ketnoi, "SET time_zone = '+07:00'");
        }
        return $this->ketnoi;
    }

    public function dis_connect()
    {
        if ($this->ketnoi) {
            mysqli_close($this->ketnoi);
            $this->ketnoi = null;
        }
    }

    public function query($sql)
    {
        $this->connect();
        return $this->ketnoi->query($sql);
    }

    public function get_list($sql)
    {
        $this->connect();
        $result = mysqli_query($this->ketnoi, $sql);
        if (!$result) {
            return [];
        }
        $return = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $return[] = $row;
        }
        mysqli_free_result($result);
        return $return;
    }

    public function get_row($sql)
    {
        $this->connect();
        $result = mysqli_query($this->ketnoi, $sql);
        if (!$result) {
            return false;
        }
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        return $row ?: false;
    }

    public function num_rows($sql)
    {
        $this->connect();
        $result = mysqli_query($this->ketnoi, $sql);
        if (!$result) {
            return 0;
        }
        $count = mysqli_num_rows($result);
        mysqli_free_result($result);
        return $count;
    }

    public function escape($string) {
        $this->connect();
        return mysqli_real_escape_string($this->ketnoi, $string);
    }

    public function insert_id() {
        $this->connect();
        return mysqli_insert_id($this->ketnoi);
    }

    public function error() {
        $this->connect();
        return mysqli_error($this->ketnoi);
    }
    
    public function prepare($sql) {
        $this->connect();
        $stmt = mysqli_prepare($this->ketnoi, $sql);
        if (!$stmt) {
            error_log("Prepare failed: " . mysqli_error($this->ketnoi));
            return false;
        }
        return new Statement($stmt, $this->ketnoi);
    }
}

/**
 * Prepared Statement Wrapper
 */
class Statement {
    private $stmt;
    private $conn;
    private $closed = false;
    
    public function __construct($stmt, $conn) {
        $this->stmt = $stmt;
        $this->conn = $conn;
    }
    
    public function execute($params = []) {
        if (!empty($params)) {
            $types = '';
            $bind_params = [];
            
            foreach ($params as $param) {
                if ($param === null) {
                    $types .= 's';
                    $bind_params[] = null;
                } elseif (is_int($param)) {
                    $types .= 'i';
                    $bind_params[] = $param;
                } elseif (is_float($param)) {
                    $types .= 'd';
                    $bind_params[] = $param;
                } else {
                    $types .= 's';
                    $bind_params[] = $param;
                }
            }
            
            $refs = [];
            foreach ($bind_params as $key => $value) {
                $refs[$key] = &$bind_params[$key];
            }
            mysqli_stmt_bind_param($this->stmt, $types, ...$refs);
        }
        
        $result = mysqli_stmt_execute($this->stmt);
        if (!$result) {
            error_log("Execute failed: " . mysqli_stmt_error($this->stmt));
            return false;
        }
        
        return $this;
    }
    
    public function fetch_assoc() {
        $result = mysqli_stmt_get_result($this->stmt);
        return $result ? mysqli_fetch_assoc($result) : false;
    }
    
    public function fetch_all() {
        $result = mysqli_stmt_get_result($this->stmt);
        if (!$result) return [];
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    public function close() {
        if (!$this->closed && $this->stmt) {
            mysqli_stmt_close($this->stmt);
            $this->closed = true;
            $this->stmt = null;
        }
    }
    
    public function __destruct() {
        $this->close();
    }
}

