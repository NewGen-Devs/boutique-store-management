<?php

/**
 * Database Class - MySQLi Connection & Query Builder
 * Handles database connectivity and basic query operations
 */

namespace App\Core;

class Database
{
    /**
     * Singleton instance
     */

    private static $instance = null;
    /**
     * MySQLi connection instance
     */
    private $connection;

    /**
     * Database configuration
     */
    private $config;

    /**
     * Query result
     */
    private $result;

    /**
     * Last executed query
     */
    private $lastQuery;

    /**
     * Query log for debugging
     */
    private $queryLog = [];

    /**
     * Constructor - Initialize database connection
     */
    private function __construct($config = [])
    {
        $this->config = $config ?: [
            'host' => DB_HOST,
            'user' => DB_USER,
            'password' => DB_PASSWORD,
            'database' => DB_NAME,
            'charset' => DB_CHARSET,
        ];

        $this->connect();
    }
    /**
     * Get singleton instance
     */
    public static function getInstance($config = [])
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }


    /**
     * Establish database connection
     */
    private function connect()
    {
        // Set connection timeout to avoid hanging
        $this->connection = @new \mysqli(
            $this->config['host'],
            $this->config['user'],
            $this->config['password'],
            $this->config['database']
        );

        if ($this->connection->connect_error) {
            throw new \Exception(
                'Database Connection Failed: ' . $this->connection->connect_error
            );
        }

        // Set charset
        $this->connection->set_charset($this->config['charset'] ?? 'utf8mb4');
    }

    /**
     * Execute a query
     */
    public function query($sql, $params = [])
    {
        $this->lastQuery = $sql;

        // Log query if debugging enabled
        if (env('ENABLE_QUERY_LOGGING', false)) {
            $this->queryLog[] = $sql;
        }

        // Prepare statement
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            throw new \Exception('Query preparation failed: ' . $this->connection->error);
        }

        // Bind parameters safely
        if (!empty($params)) {
            $types = $this->getParamTypes($params);
            // Use call_user_func_array for safer binding with many parameters
            $paramRefs = [];
            $paramRefs[] = &$types;
            foreach ($params as $key => &$value) {
                $paramRefs[] = &$value;
            }
            call_user_func_array([$stmt, 'bind_param'], $paramRefs);
        }

        // Execute
        if (!$stmt->execute()) {
            throw new \Exception('Query execution failed: ' . $stmt->error);
        }

        // Get result
        $this->result = $stmt->get_result();

        return $this->result;
    }

    /**
     * Start a database transaction natively
     */
    public function beginTransaction()
    {
        return $this->connection->begin_transaction();
    }

    /**
     * Commit the active transaction
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * Rollback the active transaction
     */
    public function rollback()
    {
        return $this->connection->rollback();
    }

    /**
     * Get parameter types string for bind_param
     */
    private function getParamTypes($params)
    {
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

    /**
     * Fetch all results as associative array
     */
    public function fetchAll()
    {
        $results = [];
        if ($this->result) {
            while ($row = $this->result->fetch_assoc()) {
                $results[] = $row;
            }
        }
        return $results;
    }

    /**
     * Fetch single result as associative array
     */
    public function fetch()
    {
        return $this->result ? $this->result->fetch_assoc() : null;
    }

    /**
     * Fetch results as objects
     */
    public function fetchObjects($className)
    {
        $results = [];
        if ($this->result) {
            while ($row = $this->result->fetch_assoc()) {
                $obj = new $className();
                foreach ($row as $key => $value) {
                    $obj->$key = $value;
                }
                $results[] = $obj;
            }
        }
        return $results;
    }
    /**
     * Fetch single value
     */
    public function fetchColumn()
    {
        if ($this->result) {
            $row = $this->result->fetch_array(MYSQLI_NUM);
            return $row ? $row[0] : null;
        }
        return null;
    }

    /**
     * Get number of affected rows
     */
    public function affectedRows()
    {
        return $this->connection->affected_rows;
    }

    /**
     * Get last insert ID
     */
    public function insertId()
    {
        return $this->connection->insert_id;
    }

    /**
     * Count rows in result
     */
    public function rowCount()
    {
        return $this->result ? $this->result->num_rows : 0;
    }

    /**
     * Simple INSERT query
     */
    public function insert($table, $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new \Exception('Insert preparation failed: ' . $this->connection->error);
        }

        $types = $this->getParamTypes(array_values($data));
        $paramRefs = [];
        $paramRefs[] = &$types;
        foreach (array_values($data) as $key => &$value) {
            $paramRefs[] = &$value;
        }
        call_user_func_array([$stmt, 'bind_param'], $paramRefs);

        if (!$stmt->execute()) {
            throw new \Exception('Insert failed: ' . $stmt->error);
        }

        return $this->connection->insert_id;
    }

    /**
     * Simple UPDATE query
     */
    public function update($table, $data, $where, $whereParams)
    {
        $setClause = implode(', ', array_map(fn($key) => "{$key} = ?", array_keys($data)));
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";

        $params = array_merge(array_values($data), $whereParams);
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            throw new \Exception('Update preparation failed: ' . $this->connection->error);
        }

        $types = $this->getParamTypes($params);
        $paramRefs = [];
        $paramRefs[] = &$types;
        foreach ($params as $key => &$value) {
            $paramRefs[] = &$value;
        }
        call_user_func_array([$stmt, 'bind_param'], $paramRefs);

        if (!$stmt->execute()) {
            throw new \Exception('Update failed: ' . $stmt->error);
        }

        return $this->connection->affected_rows;
    }

    /**
     * Simple DELETE query
     */
    public function delete($table, $where, $params)
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";

        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new \Exception('Delete preparation failed: ' . $this->connection->error);
        }

        $types = $this->getParamTypes($params);
        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new \Exception('Delete failed: ' . $stmt->error);
        }

        return $this->connection->affected_rows;
    }

    /**
     * Get query log
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * Close database connection
     */
    public function close()
    {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->close();
    }
}
