<?php
require_once 'config.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$pdo = getConnection();

// 現在のユーザー情報を取得
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

// ルーム作成処理
if (isset($_POST['create_room'])) {
    try {
        // 6桁のランダムルームコード生成
        do {
            $room_code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
            $stmt = $pdo->prepare("SELECT id FROM game_rooms WHERE room_code = ?");
            $stmt->execute([$room_code]);
        } while ($stmt->fetch());
        
        // ルーム作成
        $stmt = $pdo->prepare("INSERT INTO game_rooms (room_code, host_user_id, status) VALUES (?, ?, 'waiting')");
        $stmt->execute([$room_code, $_SESSION['user_id']]);
        $room_id = $pdo->lastInsertId();
        
        // 作成者をルームに参加させる
        $stmt = $pdo->prepare("INSERT INTO room_participants (room_id, user_id, is_ready) VALUES (?, ?, 1)");
        $stmt->execute([$room_id, $_SESSION['user_id']]);
        
        $_SESSION['room_id'] = $room_id;
        $_SESSION['room_code'] = $room_code;
        
        header('Location: room.php');
        exit;
    } catch (PDOException $e) {
        $error = 'ルーム作成に失敗しました';
    }
}

// ルーム参加処理
if (isset($_POST['join_room'])) {
    $room_code = strtoupper(trim($_POST['room_code']));
    
    if (empty($room_code)) {
        $error = 'ルームコードを入力してください';
    } else {
        try {
            // ルーム存在確認
            $stmt = $pdo->prepare("SELECT * FROM game_rooms WHERE room_code = ? AND status = 'waiting'");
            $stmt->execute([$room_code]);
            $room = $stmt->fetch();
            
            if ($room) {
                // 既に参加済みかチェック
                $stmt = $pdo->prepare("SELECT id FROM room_participants WHERE room_id = ? AND user_id = ?");
                $stmt->execute([$room['id'], $_SESSION['user_id']]);
                
                if (!$stmt->fetch()) {
                    // ルームに参加
                    $stmt = $pdo->prepare("INSERT INTO room_participants (room_id, user_id, is_ready) VALUES (?, ?, 0)");
                    $stmt->execute([$room['id'], $_SESSION['user_id']]);
                }
                
                $_SESSION['room_id'] = $room['id'];
                $_SESSION['room_code'] = $room_code;
                
                header('Location: room.php');
                exit;
            } else {
                $error = 'ルームが見つからないか、既にゲームが開始されています';
            }
        } catch (PDOException $e) {
            $error = 'ルーム参加に失敗しました';
        }
    }
}

// アクティブなルーム一覧を取得
$stmt = $pdo->query("
    SELECT gr.room_code, gr.created_at, u.name as host_name, COUNT(rp.id) as player_count
    FROM game_rooms gr
    JOIN users u ON gr.host_user_id = u.id
    LEFT JOIN room_participants rp ON gr.id = rp.room_id
    WHERE gr.status = 'waiting' AND gr.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    GROUP BY gr.id
    ORDER BY gr.created_at DESC
    LIMIT 10
");
$active_rooms = $stmt->fetchAll();

// 全プレイヤーの統計を取得
$stmt = $pdo->query("SELECT name, score FROM users ORDER BY score DESC, joined_at ASC LIMIT 10");
$top_players = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ロビー - みんはや風クイズ</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cinzel', serif;
            background: 
                radial-gradient(circle at 20% 50%, rgba(139, 69, 19, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(34, 139, 34, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(85, 107, 47, 0.3) 0%, transparent 50%),
                linear-gradient(45deg, #2d4a22 0%, #5d4e37 25%, #3d2914 50%, #1c1c0a 75%, #0f0f0a 100%);
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            color: #d4af37;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d4af37' fill-opacity='0.1'%3E%3Cpath d='M30 30c0-11.046-8.954-20-20-20s-20 8.954-20 20 8.954 20 20 20 20-8.954 20-20z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.1;
            z-index: -1;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: linear-gradient(145deg, 
                rgba(139, 69, 19, 0.9) 0%,
                rgba(160, 82, 45, 0.8) 25%, 
                rgba(139, 69, 19, 0.9) 50%,
                rgba(101, 67, 33, 0.9) 75%,
                rgba(139, 69, 19, 0.9) 100%
            );
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 
                0 0 50px rgba(212, 175, 55, 0.3),
                inset 0 0 50px rgba(212, 175, 55, 0.1),
                0 20px 40px rgba(0,0,0,0.7);
            border: 3px solid #d4af37;
        }
        
        .header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .title {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .welcome {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .content {
            padding: 2rem;
        }
        
        .room-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .room-card {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            border: 2px solid #e9ecef;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .room-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .room-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .room-description {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
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
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        
        .btn-success:hover {
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }
        
        .active-rooms {
            margin-bottom: 3rem;
        }
        
        .section-title {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #667eea;
        }
        
        .rooms-list {
            display: grid;
            gap: 1rem;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .room-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        
        .room-item:hover {
            transform: translateX(5px);
        }
        
        .room-info {
            flex: 1;
        }
        
        .room-code {
            font-size: 1.2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .room-details {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.3rem;
        }
        
        .join-btn {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .join-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .stat-card {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            border: 2px solid #e9ecef;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }
        
        .top-players {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
        }
        
        .player-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 5px;
            background: white;
            border-radius: 8px;
        }
        
        .player-rank {
            font-weight: bold;
            color: #667eea;
            min-width: 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .quick-join {
            text-align: center;
            margin: 2rem 0;
        }
        
        .quick-join input {
            display: inline-block;
            width: 200px;
            margin-right: 10px;
            text-transform: uppercase;
        }
        
        .quick-join .btn {
            display: inline-block;
            width: auto;
            padding: 12px 20px;
        }
        
        @media (max-width: 768px) {
            .room-section {
                grid-template-columns: 1fr;
            }
            
            .room-item {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .quick-join input {
                width: 100%;
                margin-right: 0;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">🏃💨 みんはや風クイズ</h1>
            <p class="welcome">ようこそ、<?= htmlspecialchars($user['name']) ?>さん！</p>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <!-- クイックルーム参加 -->
            <div class="quick-join">
                <form method="POST" style="display: inline-block;">
                    <input type="text" name="room_code" placeholder="ルームコード (例: ABC123)" maxlength="6" style="text-transform: uppercase;">
                    <button type="submit" name="join_room" class="btn">🚀 すぐに参加</button>
                </form>
            </div>
            
            <!-- ルーム作成・参加 -->
            <div class="room-section">
                <div class="room-card">
                    <div class="room-icon">🎯</div>
                    <h3 class="room-title">新しいルーム作成</h3>
                    <p class="room-description">
                        友達を招待してクイズバトル！<br>
                        最大8人まで参加可能です。
                    </p>
                    <form method="POST">
                        <button type="submit" name="create_room" class="btn btn-success">
                            ✨ ルーム作成
                        </button>
                    </form>
                </div>
                
                <div class="room-card">
                    <div class="room-icon">👥</div>
                    <h3 class="room-title">ルームに参加</h3>
                    <p class="room-description">
                        ルームコードを入力して<br>
                        既存のゲームに参加しよう！
                    </p>
                    <form method="POST">
                        <div class="form-group">
                            <label for="room_code">ルームコード</label>
                            <input type="text" id="room_code" name="room_code" placeholder="ABC123" maxlength="6" style="text-transform: uppercase;" required>
                        </div>
                        <button type="submit" name="join_room" class="btn">
                            🚪 参加する
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- アクティブルーム一覧 -->
            <div class="active-rooms">
                <h2 class="section-title">🔥 アクティブなルーム</h2>
                <?php if (!empty($active_rooms)): ?>
                    <div class="rooms-list">
                        <?php foreach ($active_rooms as $room): ?>
                            <div class="room-item">
                                <div class="room-info">
                                    <div class="room-code"><?= htmlspecialchars($room['room_code']) ?></div>
                                    <div class="room-details">
                                        ホスト: <?= htmlspecialchars($room['host_name']) ?> | 
                                        参加者: <?= $room['player_count'] ?>人 | 
                                        作成: <?= date('H:i', strtotime($room['created_at'])) ?>
                                    </div>
                                </div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="room_code" value="<?= htmlspecialchars($room['room_code']) ?>">
                                    <button type="submit" name="join_room" class="join-btn">参加</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; color: #666; padding: 2rem;">
                        現在アクティブなルームはありません<br>
                        新しいルームを作成してください！
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 統計とランキング -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $user['score'] ?></div>
                    <div class="stat-label">あなたのスコア</div>
                </div>
                <div class="top-players">
                    <h4 style="margin-bottom: 1rem; text-align: center;">🏆 トップ10</h4>
                    <?php foreach ($top_players as $index => $player): ?>
                        <div class="player-item <?= $player['name'] === $user['name'] ? 'current-user' : '' ?>">
                            <div>
                                <span class="player-rank"><?= $index + 1 ?>.</span>
                                <?= htmlspecialchars($player['name']) ?>
                                <?= $player['name'] === $user['name'] ? ' (あなた)' : '' ?>
                            </div>
                            <div style="font-weight: bold;"><?= $player['score'] ?>点</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <a href="admin.php" style="color: #667eea; text-decoration: none; margin: 0 15px;">⚙️ 管理画面</a>
                <a href="logout.php" style="color: #667eea; text-decoration: none; margin: 0 15px;">🚪 ログアウト</a>
            </div>
        </div>
    </div>
    
    <script>
        // ルームコード入力フィールドを大文字に自動変換
        const roomCodeInputs = document.querySelectorAll('input[name="room_code"]');
        roomCodeInputs.forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
        });
        
        // 定期的にアクティブルーム一覧を更新
        setInterval(() => {
            window.location.reload();
        }, 30000); // 30秒ごと
    </script>
</body>
</html>
