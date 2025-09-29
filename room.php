<?php
require_once 'config.php';

// ログインチェック
if (!isset($_SESSION['user_id']) || !isset($_SESSION['room_id'])) {
    header('Location: lobby.php');
    exit;
}

$pdo = getConnection();

// ルーム情報を取得
$stmt = $pdo->prepare("
    SELECT gr.*, u.name as host_name 
    FROM game_rooms gr 
    JOIN users u ON gr.host_user_id = u.id 
    WHERE gr.id = ?
");
$stmt->execute([$_SESSION['room_id']]);
$room = $stmt->fetch();

if (!$room) {
    unset($_SESSION['room_id'], $_SESSION['room_code']);
    header('Location: lobby.php');
    exit;
}

// ゲーム開始チェック
if ($room['status'] === 'playing') {
    header('Location: multiplayer_game.php');
    exit;
}

// 現在のユーザー情報
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$message = '';
$error = '';

// 準備完了切り替え処理
if (isset($_POST['toggle_ready'])) {
    try {
        $stmt = $pdo->prepare("UPDATE room_participants SET is_ready = 1 - is_ready WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$_SESSION['room_id'], $_SESSION['user_id']]);
    } catch (PDOException $e) {
        $error = '準備状態の更新に失敗しました';
    }
}

// ゲーム開始処理（ホストのみ）
if (isset($_POST['start_game']) && $room['host_user_id'] == $_SESSION['user_id']) {
    try {
        // 全員が準備完了かチェック
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(is_ready) as ready FROM room_participants WHERE room_id = ?");
        $stmt->execute([$_SESSION['room_id']]);
        $readyCheck = $stmt->fetch();
        
        if ($readyCheck['total'] >= 2 && $readyCheck['ready'] == $readyCheck['total']) {
            // ゲーム開始
            $stmt = $pdo->prepare("UPDATE game_rooms SET status = 'playing' WHERE id = ?");
            $stmt->execute([$_SESSION['room_id']]);
            
            header('Location: multiplayer_game.php');
            exit;
        } else {
            $error = '全員が準備完了状態である必要があります（最低2人）';
        }
    } catch (PDOException $e) {
        $error = 'ゲーム開始に失敗しました';
    }
}

// ルーム退出処理
if (isset($_POST['leave_room'])) {
    try {
        // ホストが退出する場合、ルームを削除
        if ($room['host_user_id'] == $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM game_rooms WHERE id = ?");
            $stmt->execute([$_SESSION['room_id']]);
        } else {
            // 参加者の場合は退出のみ
            $stmt = $pdo->prepare("DELETE FROM room_participants WHERE room_id = ? AND user_id = ?");
            $stmt->execute([$_SESSION['room_id'], $_SESSION['user_id']]);
        }
        
        unset($_SESSION['room_id'], $_SESSION['room_code']);
        header('Location: lobby.php');
        exit;
    } catch (PDOException $e) {
        $error = 'ルーム退出に失敗しました';
    }
}

// 参加者一覧を取得
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.score, rp.is_ready, rp.joined_at,
           CASE WHEN u.id = ? THEN 1 ELSE 0 END as is_host
    FROM room_participants rp
    JOIN users u ON rp.user_id = u.id
    WHERE rp.room_id = ?
    ORDER BY is_host DESC, rp.joined_at ASC
");
$stmt->execute([$room['host_user_id'], $_SESSION['room_id']]);
$participants = $stmt->fetchAll();

// 現在のユーザーの準備状態を取得
$current_user_ready = false;
foreach ($participants as $participant) {
    if ($participant['id'] == $_SESSION['user_id']) {
        $current_user_ready = $participant['is_ready'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>遺跡の間 <?= htmlspecialchars($room['room_code']) ?> - 早押しバトル</title>
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
            position: relative;
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
        
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                repeating-linear-gradient(
                    90deg,
                    transparent,
                    transparent 100px,
                    rgba(212, 175, 55, 0.03) 100px,
                    rgba(212, 175, 55, 0.03) 101px
                ),
                repeating-linear-gradient(
                    0deg,
                    transparent,
                    transparent 100px,
                    rgba(212, 175, 55, 0.03) 100px,
                    rgba(212, 175, 55, 0.03) 101px
                );
            z-index: -1;
        }
        
        .container {
            max-width: 900px;
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
            position: relative;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 30% 20%, rgba(212, 175, 55, 0.1) 0%, transparent 30%),
                radial-gradient(circle at 70% 80%, rgba(255, 215, 0, 0.1) 0%, transparent 30%);
            pointer-events: none;
            z-index: 1;
        }
        
        .header {
            background: linear-gradient(135deg, 
                rgba(34, 34, 34, 0.95) 0%,
                rgba(85, 85, 85, 0.9) 50%,
                rgba(34, 34, 34, 0.95) 100%
            );
            color: #d4af37;
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
            z-index: 2;
            border-bottom: 4px solid #d4af37;
            box-shadow: 
                0 4px 20px rgba(212, 175, 55, 0.3),
                inset 0 -2px 10px rgba(212, 175, 55, 0.2);
        }
        
        .header::before {
            content: '🏛️';
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 4rem;
            text-shadow: 0 0 20px rgba(212, 175, 55, 0.8);
            animation: templeGlow 3s ease-in-out infinite alternate;
        }
        
        @keyframes templeGlow {
            from { text-shadow: 0 0 20px rgba(212, 175, 55, 0.8), 0 0 40px rgba(212, 175, 55, 0.4); }
            to { text-shadow: 0 0 30px rgba(212, 175, 55, 1), 0 0 60px rgba(212, 175, 55, 0.6); }
        }
        
        .room-code {
            font-size: 4rem;
            font-weight: 700;
            margin-bottom: 1rem;
            letter-spacing: 8px;
            background: linear-gradient(45deg, #d4af37, #ffd700, #d4af37);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: goldShimmer 3s ease-in-out infinite;
            text-shadow: 0 0 30px rgba(212, 175, 55, 0.8);
            filter: drop-shadow(2px 2px 4px rgba(0,0,0,0.8));
        }
        
        @keyframes goldShimmer {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .room-info {
            font-size: 1.4rem;
            opacity: 0.9;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }
        
        .copy-button {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(45deg, rgba(212, 175, 55, 0.3), rgba(255, 215, 0, 0.3));
            color: #d4af37;
            border: 2px solid #d4af37;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Cinzel', serif;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        
        .copy-button:hover {
            background: linear-gradient(45deg, rgba(212, 175, 55, 0.6), rgba(255, 215, 0, 0.6));
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(212, 175, 55, 0.4);
        }
        
        .content {
            padding: 3rem 2rem;
            position: relative;
            z-index: 2;
        }
        
        .section-title {
            font-size: 2.2rem;
            color: #d4af37;
            margin-bottom: 2rem;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: 3px;
            background: linear-gradient(90deg, transparent, #d4af37, transparent);
        }
        
        .participants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .participant-card {
            background: linear-gradient(135deg, 
                rgba(101, 67, 33, 0.9) 0%,
                rgba(139, 69, 19, 0.8) 50%,
                rgba(101, 67, 33, 0.9) 100%
            );
            padding: 2rem;
            border-radius: 15px;
            border: 2px solid #d4af37;
            position: relative;
            transition: all 0.4s ease;
            overflow: hidden;
        }
        
        .participant-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.3), transparent);
            transition: left 0.6s ease;
        }
        
        .participant-card.ready::before {
            animation: mysticalSweep 2s ease-in-out infinite;
        }
        
        @keyframes mysticalSweep {
            0% { left: -100%; }
            50% { left: 100%; }
            100% { left: -100%; }
        }
        
        .participant-card.ready {
            background: linear-gradient(135deg, 
                rgba(34, 139, 34, 0.9) 0%,
                rgba(50, 205, 50, 0.8) 50%,
                rgba(34, 139, 34, 0.9) 100%
            );
            border-color: #90EE90;
            box-shadow: 0 0 30px rgba(144, 238, 144, 0.6);
            transform: translateY(-5px);
        }
        
        .participant-card.host {
            border-color: #ffd700;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.6);
        }
        
        .participant-card.host::after {
            content: '👑';
            position: absolute;
            top: -10px;
            right: 15px;
            font-size: 2rem;
            animation: crownFloat 2s ease-in-out infinite alternate;
        }
        
        @keyframes crownFloat {
            from { transform: translateY(0px) rotate(-5deg); }
            to { transform: translateY(-10px) rotate(5deg); }
        }
        
        .participant-name {
            font-size: 1.6rem;
            font-weight: 600;
            color: #d4af37;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }
        
        .participant-score {
            color: #b8860b;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        
        .ready-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .ready-status.ready {
            background: rgba(144, 238, 144, 0.3);
            color: #90EE90;
            border: 1px solid #90EE90;
        }
        
        .ready-status.not-ready {
            background: rgba(255, 69, 0, 0.3);
            color: #FF6347;
            border: 1px solid #FF6347;
        }
        
        .controls {
            text-align: center;
            margin-top: 3rem;
        }
        
        .btn {
            background: linear-gradient(45deg, #d4af37, #ffd700);
            color: #2d2d2d;
            padding: 15px 30px;
            border: none;
            border-radius: 30px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 10px;
            font-family: 'Cinzel', serif;
            text-transform: uppercase;
            letter-spacing: 2px;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s ease;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(212, 175, 55, 0.4);
        }
        
        .btn-ready {
            background: linear-gradient(45deg, #32cd32, #90EE90);
        }
        
        .btn-start {
            background: linear-gradient(45deg, #ff6347, #ff4500);
            color: white;
            animation: startPulse 2s ease-in-out infinite;
        }
        
        @keyframes startPulse {
            0%, 100% { box-shadow: 0 0 20px rgba(255, 99, 71, 0.6); }
            50% { box-shadow: 0 0 40px rgba(255, 99, 71, 0.9); }
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #8b0000, #dc143c);
            color: white;
        }
        
        .game-rules {
            background: rgba(0, 0, 0, 0.4);
            padding: 2rem;
            border-radius: 15px;
            margin: 2rem 0;
            border-left: 5px solid #d4af37;
            backdrop-filter: blur(10px);
        }
        
        .game-rules h3 {
            color: #d4af37;
            margin-bottom: 1rem;
            font-size: 1.8rem;
        }
        
        .rule-item {
            color: #c0c0c0;
            margin-bottom: 0.8rem;
            padding-left: 1.5rem;
            position: relative;
            font-size: 1.1rem;
        }
        
        .rule-item::before {
            content: '⚱️';
            position: absolute;
            left: 0;
            top: 0;
        }
        
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 2rem;
            border-left: 5px solid;
            backdrop-filter: blur(10px);
        }
        
        .alert-success {
            background: rgba(50, 205, 50, 0.2);
            color: #90EE90;
            border-left-color: #90EE90;
        }
        
        .alert-error {
            background: rgba(220, 20, 60, 0.2);
            color: #FF6347;
            border-left-color: #FF6347;
        }
        
        .mystical-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: #d4af37;
            border-radius: 50%;
            opacity: 0.6;
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.6; }
            50% { transform: translateY(-20px) rotate(180deg); opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .participants-grid {
                grid-template-columns: 1fr;
            }
            
            .room-code {
                font-size: 2.5rem;
                letter-spacing: 3px;
            }
            
            .participant-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="mystical-particles" id="particles"></div>
    
    <div class="container">
        <div class="header">
            <button class="copy-button" onclick="copyRoomCode()">📋 コード コピー</button>
            <div class="room-code" id="roomCode"><?= htmlspecialchars($room['room_code']) ?></div>
            <div class="room-info">🏛️ 古代遺跡の間 - ホスト: <?= htmlspecialchars($room['host_name']) ?></div>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <h2 class="section-title">🗿 探検隊メンバー (<?= count($participants) ?>/8人)</h2>
            
            <div class="participants-grid">
                <?php foreach ($participants as $participant): ?>
                    <div class="participant-card <?= $participant['is_ready'] ? 'ready' : '' ?> <?= $participant['is_host'] ? 'host' : '' ?>" data-user-id="<?= $participant['id'] ?>">
                        <div class="participant-name"><?= htmlspecialchars($participant['name']) ?></div>
                        <div class="participant-score">💰 <?= $participant['score'] ?>ポイント</div>
                        <div class="ready-status <?= $participant['is_ready'] ? 'ready' : 'not-ready' ?>">
                            <?= $participant['is_ready'] ? '🟢 準備完了' : '🔴 待機中' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

$difficulty_form = '
<div class="form-group">
    <label>難易度を選択</label>
    <select name="difficulty" required>
        <option value="beginner">初級</option>
        <option value="intermediate">中級</option>
        <option value="advanced">上級</option>
    </select>
</div>';
            
            <div class="game-rules">
                <h3>🏺 古代の掟</h3>
                <div class="rule-item">問題文が徐々に現れる間に、いち早くブザーを押せ</div>
                <div class="rule-item">正解すればポイント獲得、不正解なら次の者に権利が移る</div>
                <div class="rule-item">最初に5問正解した者が遺跡の主となる</div>
                <div class="rule-item">各問題15秒の制限時間あり</div>
                <div class="rule-item">全員が準備完了すると冒険開始</div>
            </div>
            
            <div class="controls">
                <?php if ($room['host_user_id'] == $_SESSION['user_id']): ?>
                    <!-- ホストの場合 -->
                    <?php
                    $ready_count = 0;
                    $total_count = count($participants);
                    foreach ($participants as $p) {
                        if ($p['is_ready']) $ready_count++;
                    }
                    ?>
                    <?php if ($ready_count >= 2 && $ready_count == $total_count): ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="start_game" class="btn btn-start">
                                ⚔️ 冒険開始！
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn" disabled>
                            全員の準備完了を待機中... (<?= $ready_count ?>/<?= $total_count ?>)
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- 参加者の場合 -->
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="toggle_ready" class="btn <?= $current_user_ready ? 'btn-ready' : '' ?>">
                            <?= $current_user_ready ? '✅ 準備OK' : '🛡️ 準備する' ?>
                        </button>
                    </form>
                <?php endif; ?>
                
                <form method="POST" style="display: inline;" onsubmit="return confirm('遺跡から退出しますか？')">
                    <button type="submit" name="leave_room" class="btn btn-danger">
                        🚪 遺跡退出
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <audio id="mysticalSound" preload="auto">
        <!-- ここに神秘的なサウンドファイルを追加可能 -->
    </audio>
    
    <script>
        // 神秘的なパーティクル効果
        function createParticles() {
            const container = document.getElementById('particles');
            const colors = ['#d4af37', '#ffd700', '#daa520', '#b8860b'];
            
            for (let i = 0; i < 30; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.background = colors[Math.floor(Math.random() * colors.length)];
                particle.style.animationDelay = Math.random() * 6 + 's';
                particle.style.animationDuration = (4 + Math.random() * 4) + 's';
                container.appendChild(particle);
            }
        }
        
        // ルームコードコピー機能
        function copyRoomCode() {
            const roomCode = document.getElementById('roomCode').textContent;
            navigator.clipboard.writeText(roomCode).then(() => {
                const button = document.querySelector('.copy-button');
                button.textContent = '✅ コピーされた！';
                button.style.background = 'linear-gradient(45deg, rgba(144, 238, 144, 0.6), rgba(50, 205, 50, 0.6))';
                
                setTimeout(() => {
                    button.textContent = '📋 コード コピー';
                    button.style.background = 'linear-gradient(45deg, rgba(212, 175, 55, 0.3), rgba(255, 215, 0, 0.3))';
                }, 2000);
            });
        }
        
        // 準備完了ボタンの処理
        let isSubmitting = false;
        const readyForm = document.querySelector('form[method="POST"]');
        if (readyForm && readyForm.querySelector('button[name="toggle_ready"]')) {
            readyForm.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
                isSubmitting = true;
                // 3秒後にフラグをリセット（通信が遅い場合のため）
                setTimeout(() => {
                    isSubmitting = false;
                }, 3000);
            });
        }
        
        // ページ読み込み時の初期化
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            
            // Ajaxによるステータス更新（リロードなし）
            let lastUpdateTime = Date.now();
            
            setInterval(() => {
                // 送信中はスキップ
                if (isSubmitting) return;
                
                // 5秒以上経過してからのみ更新
                if (Date.now() - lastUpdateTime < 5000) return;
                
                // AJAXで参加者情報のみ更新
                fetch('room_status.php?room_id=<?= $_SESSION['room_id'] ?>')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // ゲーム開始チェック
                            if (data.room_status === 'playing') {
                                window.location.href = 'multiplayer_game.php';
                                return;
                            }
                            
                            // 参加者リストの更新（現在のユーザーの準備状態は保持）
                            const currentUserId = <?= $_SESSION['user_id'] ?>;
                            const currentUserReady = <?= $current_user_ready ? 'true' : 'false' ?>;
                            
                            // 参加者カードを更新（自分以外）
                            data.participants.forEach(participant => {
                                const card = document.querySelector(`.participant-card[data-user-id="${participant.id}"]`);
                                if (card && participant.id != currentUserId) {
                                    const readyStatus = card.querySelector('.ready-status');
                                    if (participant.is_ready) {
                                        card.classList.add('ready');
                                        readyStatus.className = 'ready-status ready';
                                        readyStatus.textContent = '🟢 準備完了';
                                    } else {
                                        card.classList.remove('ready');
                                        readyStatus.className = 'ready-status not-ready';
                                        readyStatus.textContent = '🔴 待機中';
                                    }
                                }
                            });
                            
                            lastUpdateTime = Date.now();
                        }
                    })
                    .catch(err => {
                        console.log('Status update failed:', err);
                    });
            }, 2000);
            
            // 準備完了時のエフェクト
            const readyCards = document.querySelectorAll('.participant-card.ready');
            readyCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.animation = 'mysticalAppear 0.8s ease-out';
                }, index * 200);
            });
            
            // ホバーエフェクト強化
            const cards = document.querySelectorAll('.participant-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px) rotateX(5deg)';
                    this.style.boxShadow = '0 20px 40px rgba(212, 175, 55, 0.4)';
                });
                
                card.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('ready')) {
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = '0 0 30px rgba(212, 175, 55, 0.3)';
                    } else {
                        this.style.transform = 'translateY(-5px)';
                        this.style.boxShadow = '0 0 30px rgba(144, 238, 144, 0.6)';
                    }
                });
            });
        });
        
        // 神秘的な音響効果（オプション）
        function playMysticalSound() {
            // const audio = document.getElementById('mysticalSound');
            // if (audio) audio.play();
        }
        
        // キーボードショートカット
        document.addEventListener('keydown', function(e) {
            if (e.code === 'Space' && !isSubmitting) {
                e.preventDefault();
                const readyButton = document.querySelector('button[name="toggle_ready"]');
                if (readyButton) readyButton.click();
            }
        });
    </script>
</body>
</html>
