<?php
/**
 * 数据库连接配置
 */

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $config = $this->loadConfig();
        
        try {
            $this->connection = new mysqli(
                $config['host'],
                $config['user'],
                $config['password'],
                $config['database']
            );
            
            if ($this->connection->connect_error) {
                throw new Exception('数据库连接失败: ' . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8mb4");
        } catch (Exception $e) {
            die('数据库错误: ' . $e->getMessage());
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
    
    private function loadConfig() {
        // 从环境变量或配置文件加载
        if (file_exists(__DIR__ . '/../.env')) {
            $env = parse_ini_file(__DIR__ . '/../.env');
            return array(
                'host' => $env['DB_HOST'] ?? 'localhost',
                'user' => $env['DB_USER'] ?? 'root',
                'password' => $env['DB_PASS'] ?? '',
                'database' => $env['DB_NAME'] ?? 'cloudflare_dns'
            );
        }
        
        // 默认配置
        return array(
            'host' => 'localhost',
            'user' => 'root',
            'password' => '',
            'database' => 'cloudflare_dns'
        );
    }
    
    public function query($sql, $params = array()) {
        $stmt = $this->connection->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('SQL 错误: ' . $this->connection->error);
        }
        
        if (!empty($params)) {
            $types = $this->getParamTypes($params);
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('查询失败: ' . $stmt->error);
        }
        
        return $stmt;
    }
    
    private function getParamTypes($params) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }
    
    public function fetch($sql, $params = array()) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function fetchAll($sql, $params = array()) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        
        $stmt = $this->connection->prepare($sql);
        $types = $this->getParamTypes(array_values($data));
        $stmt->bind_param($types, ...array_values($data));
        $stmt->execute();
        
        return $this->connection->insert_id;
    }
    
    public function update($table, $data, $where, $whereParams = array()) {
        $set = implode(',', array_map(function($key) {
            return "$key=?";
        }, array_keys($data)));
        
        $sql = "UPDATE $table SET $set WHERE $where";
        $params = array_merge(array_values($data), $whereParams);
        
        $this->query($sql, $params);
        return $this->connection->affected_rows;
    }
    
    public function delete($table, $where, $params = array()) {
        $sql = "DELETE FROM $table WHERE $where";
        $this->query($sql, $params);
        return $this->connection->affected_rows;
    }
}
?>