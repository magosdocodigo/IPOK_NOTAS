<?php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'LeBronJames2@');
define('DB_NAME', 'ipok_notas');

class Database {
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $this->conn->set_charset("utf8mb4");
            
            if ($this->conn->connect_error) {
                throw new Exception("Falha na conexão: " . $this->conn->connect_error);
            }
        } catch(Exception $e) {
            echo "Erro de conexão: " . $e->getMessage();
        }
        
        return $this->conn;
    }
}
?>