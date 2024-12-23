<?php

namespace app;

interface DatabaseInterface {
    public function connect();
    public function close();
    public function prepare($sql);
    public function query($sql);
}

class NgoreiDb implements DatabaseInterface {
    const CONN_TYPE_PDO = 'pdo';
    const CONN_TYPE_MYSQLI = 'mysqli';

    private string $host;
    private string $username;
    private string $password;
    private string $database;
    private int $port;
    private string $charset;
    /** @var \PDO|\mysqli|null */
    private $conn = null;
    private $pdo = null;
    private $currentDatabase = null;

    public function __construct() {
        $this->host = DB_HOST;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->database = DB_NAME;
        $this->port = DB_PORT;
        $this->charset = DB_CHARSET;
        $this->connect();
    }

    public function connect() {
        try {
            $this->conn = new \PDO(
                "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch(\PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    /**
     * Prepare SQL statement
     * @param string $sql
     * @return \PDOStatement
     */
    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    /**
     * Execute SQL query directly
     * @param string $sql
     * @return \PDOStatement
     */
    public function query($sql) {
        return $this->conn->query($sql);
    }

    /**
     * Get database connection
     * @return \PDO
     */
    public function getConnection() {
        return $this->conn;
    }

    /**
     * Koneksi menggunakan mysqli
     * 
     * Method ini menggunakan ekstensi mysqli untuk koneksi ke MySQL/MariaDB
     * Keuntungan:
     * - Mudah digunakan untuk proyek sederhana
     * - Performa lebih baik untuk MySQL
     * - Mendukung fitur MySQL spesifik
     * 
     * @return \mysqli object koneksi mysqli
     * @throws \Exception jika koneksi gagal
     */
    public function connMysqli(): \mysqli {
        try {
            $this->conn = new \mysqli($this->host, $this->username, $this->password, $this->database);
            
            if ($this->conn->connect_error) {
                throw new \Exception("Koneksi gagal: " . $this->conn->connect_error);
            }
            
            return $this->conn;
        } catch (\Exception $e) {
            die("Error: " . $e->getMessage());
        }
    }

    /**
     * Koneksi menggunakan PDO MySQL
     * 
     * Method ini menggunakan PDO untuk koneksi ke MySQL/MariaDB
     * Keuntungan:
     * - Lebih aman dari SQL injection
     * - Mendukung prepared statements
     * - Portable ke database lain
     * - Interface yang konsisten
     * 
     * @return \PDO object koneksi PDO
     * @throws \PDOException jika koneksi gagal
     */
    public function connPDO(string $database = null): \PDO {
        try {
            if ($this->pdo === null) {
                // Koneksi awal tanpa database
                $dsn = "mysql:host={$this->host}";
                if ($database) {
                    $dsn .= ";dbname={$database}";
                }
                $dsn .= ";charset={$this->charset}";

                $this->pdo = new \PDO(
                    $dsn,
                    $this->username,
                    $this->password,
                    [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
                    ]
                );
                error_log("New PDO connection established");
            }

            // Jika database berbeda dari yang sekarang, ganti database
            if ($database !== null && $database !== $this->currentDatabase) {
                $this->pdo->exec("USE `{$database}`");
                $this->currentDatabase = $database;
                error_log("Database switched to: $database");
            }

            return $this->pdo;
        } catch (\PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new \RuntimeException("Koneksi database gagal: " . $e->getMessage());
        }
    }

    /**
     * Koneksi menggunakan SQLite
     * 
     * Method ini menggunakan PDO untuk koneksi ke database SQLite
     * Keuntungan:
     * - Database berbasis file
     * - Tidak memerlukan server database
     * - Cocok untuk aplikasi kecil-menengah
     * - Mudah untuk backup (cukup copy file)
     * 
     * Catatan: Pastikan folder database memiliki permission yang benar
     * 
     * @return \PDO object koneksi PDO SQLite
     * @throws \PDOException jika koneksi gagal
     */
    public function connSQLite() {
        try {
            $path = $this->database; // path ke file .sqlite
            $this->conn = new \PDO("sqlite:" . $path);
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            return $this->conn;
        } catch(\PDOException $e) {
            die("Koneksi SQLite gagal: " . $e->getMessage());
        }
    }

    /**
     * Koneksi menggunakan PostgreSQL
     * 
     * Method ini menggunakan PDO untuk koneksi ke PostgreSQL
     * Keuntungan:
     * - Mendukung fitur advanced database
     * - Sangat baik untuk data kompleks
     * - Mendukung JSON native
     * - Skalabilitas tinggi
     * 
     * Requirement:
     * - Ekstensi pdo_pgsql terinstall
     * - PostgreSQL server (default port: 5432)
     * 
     * @return \PDO object koneksi PDO PostgreSQL
     * @throws \PDOException jika koneksi gagal
     */
    public function connPostgre() {
        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->database}";
            $this->conn = new \PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            return $this->conn;
        } catch(\PDOException $e) {
            die("Koneksi PostgreSQL gagal: " . $e->getMessage());
        }
    }

    /**
     * Koneksi menggunakan SQL Server
     * 
     * Method ini menggunakan PDO untuk koneksi ke Microsoft SQL Server
     * Keuntungan:
     * - Integrasi baik dengan produk Microsoft
     * - Fitur enterprise level
     * - Mendukung transaksi kompleks
     * 
     * Requirement:
     * - Microsoft SQL Server Driver untuk PHP
     * - Ekstensi pdo_sqlsrv terinstall
     * - SQL Server (default port: 1433)
     * 
     * @return \PDO object koneksi PDO SQL Server
     * @throws \PDOException jika koneksi gagal
     */
    public function connSQLSRV() {
        try {
            $dsn = "sqlsrv:Server={$this->host},{$this->port};Database={$this->database}";
            $this->conn = new \PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            return $this->conn;
        } catch(\PDOException $e) {
            die("Koneksi SQL Server gagal: " . $e->getMessage());
        }
    }

    /**
     * Menutup koneksi database
     * 
     * Method ini akan menutup koneksi database yang aktif
     * Penting untuk memanggil method ini setelah selesai menggunakan database
     * untuk menghemat resources server
     */
    public function close() {
        if($this->conn instanceof \PDO || $this->conn instanceof \mysqli) {
            $this->conn = null;
        }
    }

    public function isConnected(): bool {
        return $this->conn !== null;
    }
}
