<?php
require_once 'config.php';

// セッションの破棄
session_destroy();

// Cookieの削除（セッションCookieがある場合）
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログアウト - みんはや風クイズ</title>
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
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        
        .title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .message {
            color: #666;
            margin-bottom: 2rem;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        
        .btn {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            display: inline-block;
            margin: 0 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .logout-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .countdown {
            font-size: 0.9rem;
            color: #999;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logout-icon">👋</div>
        <h1 class="title">ログアウト完了</h1>
        <p class="message">
            ログアウトしました。<br>
            ご利用ありがとうございました！<br>
            また遊びに来てくださいね。
        </p>
        
        <div>
            <a href="index.php" class="btn">🏠 トップページに戻る</a>
        </div>
        
        <div class="countdown">
            <span id="countdown">5</span>秒後に自動的にトップページに移動します...
        </div>
    </div>
    
    <script>
        // 5秒後に自動リダイレクト
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(function() {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = 'index.php';
            }
        }, 1000);
    </script>
</body>
</html>
