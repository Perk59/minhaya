// CSS animations for buzzer ready state
        function addBuzzerReadyAnimation() {
            if (!document.querySelector('#buzzerReadyStyle')) {
                const style = document.createElement('style');
                style.id = 'buzzerReadyStyle';
                style.textContent = `
                    @keyframes buzzerReady {
                        from { 
                            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.4);
                            transform: scale(1);
                        }
                        to { 
                            box-shadow: 0 20px 50px rgba(255, 215, 0, 0.8);
                            transform: scale(1.02);
                        }
                    }
                `;
                document.head.appendChild(style);
            }
        }<?php
require_once 'config.php';

// ログインチェック
if (!isset($_SESSION['user_id']) || !isset($_SESSION['room_id'])) {
    header('Location: lobby.php');
    exit;
}

$pdo = getConnection();

// ルーム情報を取得
$stmt = $pdo->prepare("SELECT * FROM game_rooms WHERE id = ? AND status = 'playing'");
$stmt->execute([$_SESSION['room_id']]);
$room = $stmt->fetch();

if (!$room) {
    header('Location: lobby.php');
    exit;
}

// ゲーム終了チェック
if ($room['status'] === 'finished') {
    header('Location: multiplayer_result.php');
    exit;
}

// 現在のユーザー情報
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// 参加者一覧を取得
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.score, rp.is_ready
    FROM room_participants rp
    JOIN users u ON rp.user_id = u.id
    WHERE rp.room_id = ?
    ORDER BY u.score DESC
");
$stmt->execute([$_SESSION['room_id']]);
$participants = $stmt->fetchAll();

// 現在のゲーム状態を取得
$stmt = $pdo->prepare("SELECT * FROM game_states WHERE room_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$_SESSION['room_id']]);
$game_state = $stmt->fetch();

// ゲーム状態初期化（最初のアクセス時）
if (!$game_state) {
    // 最初の問題を選択
    $stmt = $pdo->query("SELECT * FROM questions ORDER BY RAND() LIMIT 1");
    $question = $stmt->fetch();
    
    if ($question) {
        $stmt = $pdo->prepare("
            INSERT INTO game_states (room_id, question_id, question_number, revealed_text, reveal_progress, status) 
            VALUES (?, ?, 1, '', 0.00, 'revealing')
        ");
        $stmt->execute([$_SESSION['room_id'], $question['id']]);
        
        // ゲーム状態を再取得
        $stmt = $pdo->prepare("SELECT * FROM game_states WHERE room_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$_SESSION['room_id']]);
        $game_state = $stmt->fetch();
    }
}

// 現在の問題を取得
$stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
$stmt->execute([$game_state['question_id']]);
$question = $stmt->fetch();

$choices = json_decode($question['choices'], true);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>古代遺跡早押しバトル - 問題<?= $game_state['question_number'] ?></title>
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
            background: 
                repeating-linear-gradient(
                    90deg,
                    transparent,
                    transparent 100px,
                    rgba(212, 175, 55, 0.03) 100px,
                    rgba(212, 175, 55, 0.03) 101px
                );
            z-index: -1;
        }
        
        .game-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .game-header {
            background: linear-gradient(135deg, 
                rgba(34, 34, 34, 0.95) 0%,
                rgba(85, 85, 85, 0.9) 50%,
                rgba(34, 34, 34, 0.95) 100%
            );
            padding: 1.5rem;
            border-bottom: 4px solid #d4af37;
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .question-info {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .question-number {
            font-size: 2rem;
            font-weight: 700;
            color: #ffd700;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.8);
        }
        
        .timer-display {
            font-size: 3rem;
            font-weight: 700;
            color: #ff6347;
            text-shadow: 0 0 20px rgba(255, 99, 71, 0.8);
            min-width: 100px;
            text-align: center;
            animation: timerPulse 1s ease-in-out infinite;
        }
        
        .timer-display.warning {
            color: #ff0000;
            animation: timerDanger 0.5s ease-in-out infinite alternate;
        }
        
        @keyframes timerPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        @keyframes timerDanger {
            0% { transform: scale(1) rotate(-1deg); }
            100% { transform: scale(1.1) rotate(1deg); }
        }
        
        .game-main {
            flex: 1;
            display: flex;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
            padding: 2rem;
            gap: 2rem;
        }
        
        .question-area {
            flex: 2;
            display: flex;
            flex-direction: column;
        }
        
        .question-container {
            background: linear-gradient(145deg, 
                rgba(139, 69, 19, 0.9) 0%,
                rgba(160, 82, 45, 0.8) 50%,
                rgba(139, 69, 19, 0.9) 100%
            );
            border-radius: 20px;
            padding: 3rem;
            border: 3px solid #d4af37;
            box-shadow: 
                0 0 50px rgba(212, 175, 55, 0.3),
                inset 0 0 30px rgba(212, 175, 55, 0.1);
            position: relative;
            margin-bottom: 2rem;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .question-container::before {
            content: '🏺';
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 4rem;
            animation: artifactGlow 3s ease-in-out infinite alternate;
        }
        
        @keyframes artifactGlow {
            from { filter: drop-shadow(0 0 10px rgba(212, 175, 55, 0.8)); }
            to { filter: drop-shadow(0 0 30px rgba(212, 175, 55, 1)); }
        }
        
        .question-text {
            font-size: 2.5rem;
            color: #d4af37;
            text-align: center;
            line-height: 1.4;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
            font-weight: 600;
            position: relative;
        }
        
        .reveal-progress {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 8px;
            background: rgba(0,0,0,0.5);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .reveal-fill {
            height: 100%;
            background: linear-gradient(90deg, #d4af37, #ffd700);
            border-radius: 4px;
            transition: width 0.3s ease;
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.8);
        }
        
        .buzzer-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .buzzer-btn {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: linear-gradient(145deg, #ff6b6b, #ee5a24);
            border: 8px solid #d4af37;
            color: white;
            font-size: 2rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 
                0 10px 30px rgba(255, 107, 107, 0.4),
                inset 0 -5px 10px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        
        .buzzer-btn::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            border-radius: 50%;
            background: linear-gradient(145deg, transparent, rgba(255,255,255,0.3));
        }
        
        .buzzer-btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 
                0 20px 40px rgba(255, 107, 107, 0.6),
                inset 0 -5px 10px rgba(0,0,0,0.3);
        }
        
        .buzzer-btn:active {
            transform: translateY(2px) scale(0.95);
            animation: buzzerHit 0.3s ease;
        }
        
        @keyframes buzzerHit {
            0% { box-shadow: 0 0 50px rgba(255, 215, 0, 1); }
            100% { box-shadow: 0 0 100px rgba(255, 215, 0, 0); }
        }
        
        .buzzer-btn:disabled {
            background: linear-gradient(145deg, #666, #444);
            cursor: not-allowed;
            transform: none;
        }
        
        .choices-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            opacity: 0;
            pointer-events: none;
            transition: all 0.5s ease;
        }
        
        .choices-container.active {
            opacity: 1;
            pointer-events: all;
        }
        
        .choice-btn {
            background: linear-gradient(135deg, 
                rgba(101, 67, 33, 0.9) 0%,
                rgba(139, 69, 19, 0.8) 50%,
                rgba(101, 67, 33, 0.9) 100%
            );
            border: 3px solid #d4af37;
            border-radius: 15px;
            padding: 2rem;
            color: #d4af37;
            font-size: 1.4rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .choice-btn:hover {
            background: linear-gradient(135deg, 
                rgba(212, 175, 55, 0.3) 0%,
                rgba(255, 215, 0, 0.3) 50%,
                rgba(212, 175, 55, 0.3) 100%
            );
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(212, 175, 55, 0.4);
        }
        
        .choice-btn.selected {
            background: linear-gradient(135deg, #4169e1, #6495ed);
            color: white;
            animation: choiceSelected 0.5s ease;
        }
        
        .choice-btn.correct {
            background: linear-gradient(135deg, #32cd32, #90ee90);
            color: white;
            animation: correctAnswer 1s ease;
        }
        
        .choice-btn.incorrect {
            background: linear-gradient(135deg, #dc143c, #ff6347);
            color: white;
            animation: incorrectAnswer 1s ease;
        }
        
        @keyframes choiceSelected {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        @keyframes correctAnswer {
            0%, 100% { transform: scale(1); }
            25%, 75% { transform: scale(1.1); }
            50% { transform: scale(1.2); }
        }
        
        @keyframes incorrectAnswer {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .players-sidebar {
            flex: 1;
            max-width: 350px;
        }
        
        .players-container {
            background: linear-gradient(145deg, 
                rgba(34, 34, 34, 0.9) 0%,
                rgba(85, 85, 85, 0.8) 50%,
                rgba(34, 34, 34, 0.9) 100%
            );
            border-radius: 20px;
            padding: 2rem;
            border: 3px solid #d4af37;
            box-shadow: 0 0 30px rgba(212, 175, 55, 0.3);
        }
        
        .players-title {
            text-align: center;
            font-size: 1.8rem;
            color: #d4af37;
            margin-bottom: 2rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }
        
        .player-card {
            background: linear-gradient(135deg, 
                rgba(101, 67, 33, 0.8) 0%,
                rgba(139, 69, 19, 0.7) 100%
            );
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 2px solid #d4af37;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .player-card.current-user {
            border-color: #ffd700;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
        }
        
        .player-card.buzzer-holder {
            background: linear-gradient(135deg, #ff6347, #dc143c);
            border-color: #ff0000;
            box-shadow: 0 0 30px rgba(255, 99, 71, 0.8);
            animation: buzzerHolder 1s ease-in-out infinite alternate;
        }
        
        @keyframes buzzerHolder {
            from { box-shadow: 0 0 30px rgba(255, 99, 71, 0.8); }
            to { box-shadow: 0 0 50px rgba(255, 99, 71, 1); }
        }
        
        .player-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #d4af37;
            margin-bottom: 0.5rem;
        }
        
        .player-score {
            font-size: 1.1rem;
            color: #b8860b;
        }
        
        .player-status {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.5rem;
        }
        
        .result-display {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.5s ease;
        }
        
        .result-display.show {
            opacity: 1;
            pointer-events: all;
        }
        
        .result-content {
            background: linear-gradient(145deg, 
                rgba(139, 69, 19, 0.95) 0%,
                rgba(160, 82, 45, 0.9) 50%,
                rgba(139, 69, 19, 0.95) 100%
            );
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            border: 4px solid #d4af37;
            box-shadow: 0 0 50px rgba(212, 175, 55, 0.5);
            max-width: 500px;
        }
        
        .result-message {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }
        
        .result-message.correct {
            color: #90ee90;
        }
        
        .result-message.incorrect {
            color: #ff6347;
        }
        
        .next-btn {
            background: linear-gradient(45deg, #d4af37, #ffd700);
            color: #2d2d2d;
            padding: 15px 30px;
            border: none;
            border-radius: 30px;
            font-size: 1.3rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 2rem;
            transition: all 0.3s ease;
            font-family: 'Cinzel', serif;
        }
        
        .next-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(212, 175, 55, 0.4);
        }
        
        @media (max-width: 1024px) {
            .game-main {
                flex-direction: column;
                padding: 1rem;
            }
            
            .players-sidebar {
                max-width: none;
            }
            
            .question-text {
                font-size: 2rem;
            }
            
            .buzzer-btn {
                width: 150px;
                height: 150px;
                font-size: 1.5rem;
            }
            
            .choices-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <div class="header-content">
                <div class="question-info">
                    <div class="question-number">🏺 問題 <?= $game_state['question_number'] ?>/10</div>
                </div>
                <div class="timer-display" id="timer">15</div>
            </div>
        </div>
        
        <div class="game-main">
            <div class="question-area">
                <div class="question-container">
                    <div class="question-text" id="questionText"></div>
                    <div class="reveal-progress">
                        <div class="reveal-fill" id="revealProgress" style="width: 0%"></div>
                    </div>
                </div>
                
                <div class="buzzer-section">
                    <button class="buzzer-btn" id="buzzerBtn">
                        ⚡<br>BUZZ!
                    </button>
                    <div id="buzzer-status" style="margin-top: 1rem; font-size: 1.2rem; font-weight: 600;"></div>
                </div>
                
                <div class="choices-container" id="choicesContainer">
                    <?php foreach ($choices as $index => $choice): ?>
                        <button class="choice-btn" data-choice="<?= $index ?>">
                            <?= chr(65 + $index) ?>. <?= htmlspecialchars($choice) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="players-sidebar">
                <div class="players-container">
                    <h3 class="players-title">🗿 探検隊</h3>
                    <div id="playersContainer">
                        <?php foreach ($participants as $participant): ?>
                            <div class="player-card <?= $participant['id'] == $_SESSION['user_id'] ? 'current-user' : '' ?>" 
                                 data-user-id="<?= $participant['id'] ?>">
                                <div class="player-name"><?= htmlspecialchars($participant['name']) ?></div>
                                <div class="player-score">💰 <?= $participant['score'] ?>pt</div>
                                <div class="player-status">⭐</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="result-display" id="resultDisplay">
        <div class="result-content">
            <div class="result-message" id="resultMessage"></div>
            <button class="next-btn" id="nextBtn" onclick="nextQuestion()">次の問題へ ⚔️</button>
        </div>
    </div>
    
    <script>
        // ゲーム変数
        let gameData = {
            roomId: <?= $_SESSION['room_id'] ?>,
            userId: <?= $_SESSION['user_id'] ?>,
            questionId: <?= $question['id'] ?>,
            questionText: <?= json_encode($question['question']) ?>,
            correctAnswer: <?= $question['answer'] ?>,
            timeLeft: 15,
            revealProgress: 0,
            hasBuzzed: false,
            hasAnswered: false,
            canBuzz: true,
            revealSpeed: 2 // 文字数/秒
        };
        
        let timerInterval;
        let revealInterval;
        
        // ゲーム初期化
        function initializeGame() {
            startQuestionReveal();
            startTimer();
            updatePlayerDisplay();
        }
        
        // 問題文段階的表示
        function startQuestionReveal() {
            const questionTextEl = document.getElementById('questionText');
            const revealProgressEl = document.getElementById('revealProgress');
            const totalLength = gameData.questionText.length;
            let currentLength = 0;
            
            questionTextEl.textContent = '';
            
            revealInterval = setInterval(() => {
                if (currentLength < totalLength) {
                    currentLength += gameData.revealSpeed;
                    if (currentLength > totalLength) currentLength = totalLength;
                    
                    const revealedText = gameData.questionText.substring(0, Math.floor(currentLength));
                    questionTextEl.textContent = revealedText;
                    
                    const progress = (currentLength / totalLength) * 100;
                    revealProgressEl.style.width = progress + '%';
                    gameData.revealProgress = progress;
                } else {
                    clearInterval(revealInterval);
                    // 全文表示完了後、少し待ってから早押し可能
                    setTimeout(() => {
                        gameData.canBuzz = true;
                        document.getElementById('buzzerBtn').style.animation = 'buzzerReady 1s ease-in-out infinite alternate';
                    }, 500);
                }
            }, 500);
        }
        
        // タイマー開始
        function startTimer() {
            const timerEl = document.getElementById('timer');
            
            timerInterval = setInterval(() => {
                gameData.timeLeft--;
                timerEl.textContent = gameData.timeLeft;
                
                if (gameData.timeLeft <= 5) {
                    timerEl.classList.add('warning');
                }
                
                if (gameData.timeLeft <= 0) {
                    clearInterval(timerInterval);
                    clearInterval(revealInterval);
                    timeUp();
                }
            }, 1000);
        }
        
        // 早押しボタン
        function pressBuzzer() {
            if (!gameData.canBuzz || gameData.hasBuzzed || gameData.hasAnswered) return;
            
            gameData.hasBuzzed = true;
            gameData.canBuzz = false;
            clearInterval(revealInterval);
            
            // 視覚的フィードバック
            const buzzerBtn = document.getElementById('buzzerBtn');
            buzzerBtn.disabled = true;
            buzzerBtn.style.background = 'linear-gradient(145deg, #666, #444)';
            buzzerBtn.style.animation = 'none';
            
            // 早押し成功表示
            document.getElementById('buzzer-status').innerHTML = 
                '<span style="color: #90ee90;">🎯 早押し成功！選択肢を選んでください</span>';
            
            // 問題文を全表示
            document.getElementById('questionText').textContent = gameData.questionText;
            document.getElementById('revealProgress').style.width = '100%';
            
            // 選択肢を表示
            document.getElementById('choicesContainer').classList.add('active');
            
            // 自分が早押し成功したことを表示
            updatePlayerBuzzerStatus(gameData.userId);
            
            // サーバーに早押し送信
            fetch('multiplayer_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=buzz&room_id=${gameData.roomId}&question_id=${gameData.questionId}&user_id=${gameData.userId}`
            });
        }
        
        // 選択肢選択
        function selectChoice(choiceIndex) {
            if (!gameData.hasBuzzed || gameData.hasAnswered) return;
            
            gameData.hasAnswered = true;
            clearInterval(timerInterval);
            
            // 選択した選択肢をハイライト
            document.querySelector(`[data-choice="${choiceIndex}"]`).classList.add('selected');
            
            // 全選択肢を無効化
            const choiceBtns = document.querySelectorAll('.choice-btn');
            choiceBtns.forEach(btn => btn.disabled = true);
            
            // 結果表示
            setTimeout(() => {
                showResult(choiceIndex);
            }, 1000);
            
            //
