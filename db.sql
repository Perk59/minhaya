-- データベース作成
CREATE DATABASE quiz_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quiz_app;

-- ユーザーテーブル
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    score INT DEFAULT 0,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 問題テーブル
CREATE TABLE questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question TEXT NOT NULL,
    choices JSON NOT NULL,
    answer INT NOT NULL,
    difficulty INT DEFAULT 1
);

-- 回答テーブル
CREATE TABLE answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    choice INT NOT NULL,
    is_correct TINYINT NOT NULL,
    answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (question_id) REFERENCES questions(id)
);

-- サンプルクイズデータを挿入
INSERT INTO questions (question, choices, answer) VALUES
('日本の首都はどこですか？', '["東京", "大阪", "京都", "名古屋"]', 0),
('1 + 1 = ?', '["1", "2", "3", "4"]', 1),
('富士山の標高は約何メートルですか？', '["3000m", "3500m", "3776m", "4000m"]', 2),
('日本で一番大きな湖は？', '["琵琶湖", "霞ヶ浦", "サロマ湖", "猪苗代湖"]', 0),
('2024年開催の夏季オリンピックの開催地は？', '["東京", "パリ", "ロサンゼルス", "ブリスベン"]', 1),
('日本の国鳥は？', '["鶴", "雀", "キジ", "鷹"]', 2),
('太陽系で一番大きな惑星は？', '["地球", "火星", "木星", "土星"]', 2),
('日本の通貨単位は？', '["ドル", "円", "ウォン", "元"]', 1),
('世界で一番高い山は？', '["富士山", "エベレスト", "K2", "マッキンリー"]', 1),
('日本の47都道府県のうち、一番面積が大きいのは？', '["北海道", "岩手県", "福島県", "新潟県"]', 0);
