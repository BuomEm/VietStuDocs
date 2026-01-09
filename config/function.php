<?php
// Set timezone to Vietnam (UTC+7)
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env if exists
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

// Basic OpenSSL config for Windows
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    if (isset($_ENV['OPENSSL_CONF']) && file_exists($_ENV['OPENSSL_CONF'])) {
        putenv("OPENSSL_CONF=" . $_ENV['OPENSSL_CONF']);
    }
}

require_once __DIR__ . '/helper.php';

class DB
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
    }
    public function dis_connect()
    {
        if ($this->ketnoi) {
            mysqli_close($this->ketnoi);
        }
    }
    public function site($data)
    {
        $this->connect();
        $row = $this->ketnoi->query("SELECT * FROM `settings` WHERE `name` = '$data' ")->fetch_array();
        return $row['value'] ?? null;
    }
    public function query($sql)
    {
        $this->connect();
        $row = $this->ketnoi->query($sql);
        return $row;
    }
    public function cong($table, $data, $sotien, $where)
    {
        $this->connect();
        $row = $this->ketnoi->query("UPDATE `$table` SET `$data` = `$data` + '$sotien' WHERE $where ");
        return $row;
    }
    public function tru($table, $data, $sotien, $where)
    {
        $this->connect();
        $row = $this->ketnoi->query("UPDATE `$table` SET `$data` = `$data` - '$sotien' WHERE $where ");
        return $row;
    }
    public function insert($table, $data)
    {
        $this->connect();
        $field_list = '';
        $value_list = '';
        foreach ($data as $key => $value) {
            $field_list .= ",$key";
            $value_list .= ",'".mysqli_real_escape_string($this->ketnoi, $value)."'";
        }
        $sql = 'INSERT INTO '.$table. '('.trim($field_list, ',').') VALUES ('.trim($value_list, ',').')';
        return mysqli_query($this->ketnoi, $sql);
    }
    public function update($table, $data, $where)
    {
        $this->connect();
        $sql = '';
        foreach ($data as $key => $value) {
            $sql .= "$key = '".mysqli_real_escape_string($this->ketnoi, $value)."',";
        }
        $sql = 'UPDATE '.$table. ' SET '.trim($sql, ',').' WHERE '.$where;
        return mysqli_query($this->ketnoi, $sql);
    }
    public function update_value($table, $data, $where, $value1)
    {
        $this->connect();
        $sql = '';
        foreach ($data as $key => $value) {
            $sql .= "$key = '".mysqli_real_escape_string($this->ketnoi, $value)."',";
        }
        $sql = 'UPDATE '.$table. ' SET '.trim($sql, ',').' WHERE '.$where.' LIMIT '.$value1;
        return mysqli_query($this->ketnoi, $sql);
    }
    public function remove($table, $where)
    {
        $this->connect();
        $sql = "DELETE FROM $table WHERE $where";
        return mysqli_query($this->ketnoi, $sql);
    }
    public function get_list($sql)
    {
        $this->connect();
        $result = mysqli_query($this->ketnoi, $sql);
        if (!$result) {
            die('Câu truy vấn bị sai');
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
            die('Câu truy vấn bị sai');
        }
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        if ($row) {
            return $row;
        }
        return false;
    }
    public function num_rows($sql)
    {
        $this->connect();
        $result = mysqli_query($this->ketnoi, $sql);
        if (!$result) {
            die('Câu truy vấn bị sai');
        }
        $row = mysqli_num_rows($result);
        mysqli_free_result($result);
        if ($row) {
            return $row;
        }
        return false;
    }
    public function get_conn() {
        $this->connect();
        return $this->ketnoi;
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
    
    /**
     * Prepared Statements Support (for secure API)
     */
    public function prepare($sql) {
        $this->connect();
        $stmt = mysqli_prepare($this->ketnoi, $sql);
        if (!$stmt) {
            error_log("Prepare failed: " . mysqli_error($this->ketnoi));
            return false;
        }
        return new VSDStmt($stmt, $this->ketnoi);
    }
}

/**
 * Prepared Statement Wrapper
 */
class VSDStmt {
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
                    // NULL values: use 's' type, MySQLi will handle NULL correctly
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
            
            // Bind params with references (required for mysqli_stmt_bind_param)
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
        if (!$result) {
            return false;
        }
        return mysqli_fetch_assoc($result);
    }
    
    public function fetch_all() {
        $result = mysqli_stmt_get_result($this->stmt);
        if (!$result) {
            return [];
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    public function get_result() {
        return mysqli_stmt_get_result($this->stmt);
    }
    
    public function affected_rows() {
        return mysqli_stmt_affected_rows($this->stmt);
    }
    
    public function close() {
        if (!$this->closed && $this->stmt) {
            mysqli_stmt_close($this->stmt);
            $this->closed = true;
            $this->stmt = null;
        }
    }
    
    public function __destruct() {
        if (!$this->closed && $this->stmt) {
            mysqli_stmt_close($this->stmt);
            $this->closed = true;
        }
    }
}

$VSD = new DB();
$conn = null;

function db_query($sql) { global $VSD; return $VSD->query($sql); }
function db_get_results($sql) { global $VSD; return $VSD->get_list($sql); }
function db_get_row($sql) { global $VSD; return $VSD->get_row($sql); }
function db_num_rows($sql) { global $VSD; return $VSD->num_rows($sql); }
function db_escape($string) { global $VSD; return $VSD->escape($string); }
function db_insert_id() { global $VSD; return $VSD->insert_id(); }
function db_site($data) { global $VSD; return $VSD->site($data); }
function db_cong($table, $data, $sotien, $where) { global $VSD; return $VSD->cong($table, $data, $sotien, $where); }
function db_tru($table, $data, $sotien, $where) { global $VSD; return $VSD->tru($table, $data, $sotien, $where); }
function db_insert($table, $data) { global $VSD; return $VSD->insert($table, $data); }
function db_update($table, $data, $where) { global $VSD; return $VSD->update($table, $data, $where); }
function db_update_value($table, $data, $where, $value1) { global $VSD; return $VSD->update_value($table, $data, $where, $value1); }
function db_delete($table, $where) { global $VSD; return $VSD->remove($table, $where); }
function db_table_exists($table_name) {
    $table_name = db_escape($table_name);
    $sql = "SHOW TABLES LIKE '$table_name'";
    return db_num_rows($sql) > 0;
}
function db_error() { global $VSD; return $VSD->error(); }

function db_find($table, $where) {
    $where_str = '';
    if (is_array($where)) {
        $conditions = [];
        foreach ($where as $col => $val) { $conditions[] = "$col = '" . db_escape($val) . "'"; }
        $where_str = implode(' AND ', $conditions);
    } else { $where_str = $where; }
    return db_get_row("SELECT * FROM $table WHERE $where_str LIMIT 1");
}

function db_get($table, $where = [], $order = '', $limit = '') {
    $where_str = '';
    if (!empty($where)) {
        if (is_array($where)) {
            $conditions = [];
            foreach ($where as $col => $val) { $conditions[] = "$col = '" . db_escape($val) . "'"; }
            $where_str = ' WHERE ' . implode(' AND ', $conditions);
        } else { $where_str = ' WHERE ' . $where; }
    }
    $order_sql = $order ? " ORDER BY $order" : '';
    $limit_sql = $limit ? " LIMIT $limit" : '';
    return db_get_results("SELECT * FROM $table$where_str$order_sql$limit_sql");
}

function db_count($table, $where = []) {
    $where_str = '';
    if (!empty($where)) {
        if (is_array($where)) {
            $conditions = [];
            foreach ($where as $col => $val) { $conditions[] = "$col = '" . db_escape($val) . "'"; }
            $where_str = ' WHERE ' . implode(' AND ', $conditions);
        } else { $where_str = ' WHERE ' . $where; }
    }
    $row = db_get_row("SELECT COUNT(*) as count FROM $table$where_str");
    return $row ? intval($row['count']) : 0;
}

/**
 * Anti-Double-Submit Helper
 * Uses session-based tokens to prevent identical requests within a short timeframe.
 * Useful for critical actions like money additions or tutor applications.
 */
function check_double_submit($action_name, $limit_seconds = 5) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    $user_id = $_SESSION['user_id'] ?? 'guest';
    $key = "last_submit_{$action_name}_{$user_id}";
    
    if (isset($_SESSION[$key]) && (time() - $_SESSION[$key]) < $limit_seconds) {
        return false; // Submission is too fast
    }
    
    $_SESSION[$key] = time();
    return true;
}

$conn = $VSD->get_conn();
