<?php

class Database{

    private $host = "localhost";
    private $db = "mydb";
    private $user = "root";
    private $pass = "";
    private $charset = "utf8";
    private $pdo;

    
    public function __construct() {
        $dsn = "mysql:host={$this->host};dbname={$this->db};charset={$this->charset}";
        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass);
            //PDO::ERRMODE_EXCEPTION - zachytime vynimku (exception) a tak ziskame error
            //PDO::ERRMODE_WARNING - vyhodi warning (nie je idealne pre produkciu)
            //PDO::ERRMODE_SILENT - nevyhodi nic 
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('Connection failed: ' . $e->getMessage());
        }
    }

    public function __destruct(){
        $this->pdo = null;
    }

    public function getConnection(){
        return $this->pdo;
    }
}

?>