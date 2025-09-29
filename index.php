<?php
require_once 'config.php';

// 既にログインしている場合はlobbyにリダイレクト
if (isset($_SESSION['user_id'])) {
    header('Location: lobby.php');
    exit;
}

$error = '';

if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    
    if (empty($name)) {
        $error = '名前を入力してください';
    } else {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("INSERT INTO users (name, score) VALUES (?, 0)");
            $stmt->execute([$name]);
            
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['username'] = $name;
            
            header('Location: lobby.php');
            exit;
        } catch (PDOException $e) {
            $error = 'データベースエラーが発生しました';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>みんはや風クイズ - ログイン</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        
        .title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .error {
            color: #e74c3c;
            margin-top: 1rem;
            padding: 10px;
            background: #fdf2f2;
            border-radius: 5px;
            border-left: 4px solid #e74c3c;
        }
        
        .anonymous-link {
            margin-top: 1rem;
        }
        
        .anonymous-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .anonymous-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title">🏃💨 みんはや風クイズ</h1>
        <p class="subtitle">早押しクイズゲームに参加しよう！</p>
        
        <form method="POST">
            <div class="form-group">
                <label for="name">参加者名</label>
                <input type="text" id="name" name="name" placeholder="あなたの名前を入力" maxlength="50" required>
            </div>
            
            <button type="submit" class="btn">ゲームに参加</button>
        </form>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="anonymous-link">
            <a href="?anonymous=1" onclick="return joinAnonymous()">匿名で参加</a>
        </div>
    </div>
    
    <script>
        function joinAnonymous() {
            const randomNames = ['プレイヤー1', 'プレイヤー2', 'プレイヤー3', 'プレイヤー4', 'プレイヤー5'];
            const randomName = randomNames[Math.floor(Math.random() * randomNames.length)] + Math.floor(Math.random() * 1000);
            document.getElementById('name').value = randomName;
            document.querySelector('form').submit();
            return false;
        }
        
        // エンターキーでフォーム送信
        document.getElementById('name').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('form').submit();
            }
        });
    </script>
</body>
</html>
