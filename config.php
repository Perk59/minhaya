<?php
// データベース設定
define('DB_HOST', 'localhost');
define('DB_NAME', 'xs163907_minhaya');
define('DB_USER', 'xs163907_kojima');
define('DB_PASS', 'Keito0805'); // パスワードを設定してください

// データベース接続関数
function getConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("データベース接続エラー: " . $e->getMessage());
    }
}

// セッション開始
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ゲーム設定
define('QUESTIONS_PER_ROUND', 10);
define('TIME_LIMIT', 15); // 秒
define('CORRECT_SCORE', 10);
define('INCORRECT_PENALTY', -5);
?>
