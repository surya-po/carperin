<?php
/**
 * CARPERIN - Supabase Database Connection & Adapter
 */

if (!class_exists('SupabaseResult')) {
    class SupabaseResult {
        private $rows;
        private $cursor = 0;
        public $num_rows = 0;

        public function __construct($rows) {
            $this->rows = is_array($rows) ? $rows : [];
            $this->num_rows = count($this->rows);
        }

        public function fetch_assoc() {
            if ($this->cursor < $this->num_rows) {
                return $this->rows[$this->cursor++];
            }
            return null;
        }

        public function fetch_all($mode = 1) {
            return $this->rows;
        }
    }
}

if (!class_exists('SupabaseStmt')) {
    class SupabaseStmt {
        private $pdo;
        private $sql;
        private $params = [];
        public $insert_id = 0;

        public function __construct($pdo, $sql) {
            $this->pdo = $pdo;
            $this->sql = $sql;
        }

        public function bind_param($types, &...$args) {
            $this->params = $args;
            return true;
        }

        public function execute($params = null) {
            if ($params !== null) {
                $this->params = $params;
            }

            $sql = $this->sql;
            $isInsert = (stripos(ltrim($sql), 'INSERT') === 0);

            if ($isInsert && stripos($sql, 'RETURNING') === false) {
                $sql .= ' RETURNING id';
            }

            try {
                $stmt = $this->pdo->prepare($sql);
                $res = $stmt->execute($this->params);
                if ($isInsert) {
                    $id = $stmt->fetchColumn();
                    if ($id) {
                        $this->insert_id = (int)$id;
                    }
                }
                return $res;
            } catch (Throwable $e) {
                error_log("SupabaseStmt Error: " . $e->getMessage() . " | SQL: " . $sql);
                return false;
            }
        }
    }
}

if (!class_exists('SupabaseConnection')) {
    class SupabaseConnection {
        public $pdo;
        public $connect_error = null;
        public $insert_id = 0;

        public function __construct($host, $user, $pass, $db, $port = 5432) {
            try {
                $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
                $this->pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (Throwable $e) {
                $this->connect_error = $e->getMessage();
            }
        }

        public function query($sql) {
            if (!$this->pdo) return false;

            // Abaikan query DDL dinamis jika kolom sudah ada
            if (stripos($sql, 'ALTER TABLE') !== false) {
                try {
                    $this->pdo->exec($sql);
                } catch (Throwable $e) {
                    // ignore
                }
                return true;
            }

            $trimmed  = ltrim($sql);
            $isSelect = (stripos($trimmed, 'SELECT') === 0 || stripos($trimmed, 'SHOW') === 0);
            $isInsert = (stripos($trimmed, 'INSERT') === 0);

            try {
                if ($isInsert && stripos($sql, 'RETURNING') === false) {
                    $sql .= ' RETURNING id';
                    $stmt = $this->pdo->query($sql);
                    $id = $stmt->fetchColumn();
                    if ($id) {
                        $this->insert_id = (int)$id;
                    }
                    return true;
                }

                if ($isSelect) {
                    $stmt = $this->pdo->query($sql);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    return new SupabaseResult($rows);
                } else {
                    $affected = $this->pdo->exec($sql);
                    return ($affected !== false);
                }
            } catch (Throwable $e) {
                error_log("Supabase Query Error: " . $e->getMessage() . " | SQL: " . $sql);
                return false;
            }
        }

        public function prepare($sql) {
            return new SupabaseStmt($this->pdo, $sql);
        }

        public function real_escape_string($string) {
            if ($this->pdo) {
                $quoted = $this->pdo->quote((string)$string);
                return substr($quoted, 1, -1);
            }
            return addslashes((string)$string);
        }

        public function escape_string($string) {
            return $this->real_escape_string($string);
        }
    }
}

// Konfigurasi Kredensial Supabase
$host = "db.vdigjznvsyajrycewntv.supabase.co";
$user = "postgres";
$pass = "carperin_12.id";
$db   = "postgres";
$port = 5432;

$conn = new SupabaseConnection($host, $user, $pass, $db, $port);
$koneksi = $conn;

if ($conn->connect_error) {
    die("Koneksi Database Supabase Gagal: " . $conn->connect_error);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper untuk menampilkan gambar yang benar
if (!function_exists('get_image_url')) {
    function get_image_url($gambar) {
        if (empty($gambar)) {
            return 'https://via.placeholder.com/150?text=No+Image';
        }
        if (file_exists('images/' . $gambar)) {
            return 'images/' . $gambar;
        }
        if (file_exists('uploads/' . $gambar)) {
            return 'uploads/' . $gambar;
        }
        if (filter_var($gambar, FILTER_VALIDATE_URL)) {
            return $gambar;
        }
        return 'images/' . $gambar;
    }
}