<?php
require_once dirname(__DIR__, 2) . '/config.php';
class Database {
    private $connection;
    
    public function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . Config::DB_HOST . ";dbname=" . Config::DB_NAME,
                Config::DB_USER,
                Config::DB_PASS
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Configurar zona horaria
            $this->connection->exec("SET time_zone = '-03:00'");
        } catch(PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Error de conexión a la base de datos");
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Ejecuta una consulta preparada de forma segura
     */
    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query execution error: " . $e->getMessage() . " | SQL: $sql");
            throw $e;
        }
    }
}
