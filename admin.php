<?php
require_once 'config.php';

$pdo = getConnection();
$message = '';
$error = '';

// 問題追加処理
if (isset($_POST['add_question'])) {
    $question = trim($_POST['question']);
    $choices = array_map('trim', [
        $_POST['choice1'],
        $_POST['choice2'], 
        $_POST['choice3'],
        $_POST['choice4']
    ]);
    $answer = intval($_POST['answer']);
    $difficulty = intval($_POST['difficulty'] ?? 1);
    
    if (!empty($question) && !empty($choices[0]) && !empty($choices[1])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO questions (question, choices, answer, difficulty) VALUES (?, ?, ?, ?)");
            $stmt->execute([$question, json_encode($choices), $answer, $difficulty]);
            $message = '問題を追加しました！';
        } catch (PDOException $e) {
            $error = '問題の追加に失敗しました: ' . $e->getMessage();
        }
    } else {
        $error = '必要な項目を入力してください';
    }
}

// 問題削除処理
if (isset($_POST['delete_question'])) {
    $question_id = intval($_POST['question_id']);
    try {
        // 関連する回答も削除
        $stmt = $pdo->prepare("DELETE FROM answers WHERE question_id = ?");
        $stmt->execute([$question_id]);
        
        $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
        $stmt->execute([$question_id]);
        
        $message = '問題を削除しました！';
    } catch (PDOException $e) {
        $error = '問題の削除に失敗しました: ' . $e->getMessage();
    }
}

// ユーザーリセット処理
if (isset($_POST['reset_users'])) {
    try {
        $pdo->exec("UPDATE users SET score = 0");
        $pdo->exec("DELETE FROM answers");
        $message = '全ユーザーのスコアをリセットしました！';
    } catch (PDOException $e) {
        $error = 'リセットに失敗しました: ' . $e->getMessage();
    }
}

// 統計情報を取得
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM questions) as total_questions,
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM answers) as total_answers,
    (SELECT AVG(score) FROM users) as avg_score";
$stats = $pdo->query($stats_query)->fetch();

// 問題一覧を取得
$questions = $pdo->query("SELECT * FROM questions ORDER BY id DESC LIMIT 20")->fetchAll();

// ユーザー一覧を取得
$users = $pdo->query("SELECT * FROM users ORDER BY score DESC, joined_at ASC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - みんはや風クイズ</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
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
        
        .subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .content {
            padding: 2rem;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e1e1e1;
        }
        
        .tab {
            padding: 1rem 2rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            border-bottom-color: #667eea;
            color: #667eea;
            font-weight: bold;
        }
        
        .tab:hover {
            background: #f8f9fa;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            border: 2px solid #e9ecef;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .form-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        input[type="text"], 
        input[type="number"], 
        textarea, 
        select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus, 
        textarea:focus, 
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .choices-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .btn {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin: 5px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #dc3545, #c82333);
        }
        
        .btn-danger:hover {
            box-shadow: 0 10px 20px rgba(220, 53, 69, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(45deg, #ffc107, #e0a800);
        }
        
        .btn-warning:hover {
            box-shadow: 0 10px 20px rgba(255, 193, 7, 0.3);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .table th {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .table tr:hover {
            background: #f8f9fa;
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
        
        .question-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .choices-preview {
            font-size: 0.9rem;
            color: #666;
        }
        
        .back-link {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 15px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            backdrop-filter: blur(10px);
            transition: all 0.3s;
        }
        
        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        @media (max-width: 768px) {
            .choices-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table {
                font-size: 14px;
            }
            
            .table th,
            .table td {
                padding: 10px 5px;
            }
        }
    </style>
</head>
<body>
    <a href="lobby.php" class="back-link">← ロビーに戻る</a>
    
    <div class="container">
        <div class="header">
            <h1 class="title">⚙️ 管理画面</h1>
            <p class="subtitle">クイズゲームの管理・設定</p>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <!-- タブナビゲーション -->
            <div class="tabs">
                <div class="tab active" onclick="showTab('stats')">📊 統計</div>
                <div class="tab" onclick="showTab('questions')">❓ 問題管理</div>
                <div class="tab" onclick="showTab('users')">👥 ユーザー管理</div>
                <div class="tab" onclick="showTab('settings')">⚙️ 設定</div>
            </div>
            
            <!-- 統計タブ -->
            <div id="stats" class="tab-content active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['total_questions'] ?></div>
                        <div class="stat-label">登録問題数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['total_users'] ?></div>
                        <div class="stat-label">参加ユーザー数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['total_answers'] ?></div>
                        <div class="stat-label">総回答数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['avg_score'] ? round($stats['avg_score'], 1) : 0 ?></div>
                        <div class="stat-label">平均スコア</div>
                    </div>
                </div>
            </div>
            
            <!-- 問題管理タブ -->
            <div id="questions" class="tab-content">
                <div class="form-section">
                    <h3 class="form-title">📝 新しい問題を追加</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="question">問題文</label>
                            <textarea id="question" name="question" placeholder="問題文を入力してください" required></textarea>
                        </div>
                        
                        <div class="choices-grid">
                            <div class="form-group">
                                <label for="choice1">選択肢A</label>
                                <input type="text" id="choice1" name="choice1" placeholder="選択肢A" required>
                            </div>
                            <div class="form-group">
                                <label for="choice2">選択肢B</label>
                                <input type="text" id="choice2" name="choice2" placeholder="選択肢B" required>
                            </div>
                            <div class="form-group">
                                <label for="choice3">選択肢C</label>
                                <input type="text" id="choice3" name="choice3" placeholder="選択肢C">
                            </div>
                            <div class="form-group">
                                <label for="choice4">選択肢D</label>
                                <input type="text" id="choice4" name="choice4" placeholder="選択肢D">
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem;">
                            <div class="form-group" style="flex: 1;">
                                <label for="answer">正解</label>
                                <select id="answer" name="answer" required>
                                    <option value="0">A</option>
                                    <option value="1">B</option>
                                    <option value="2">C</option>
                                    <option value="3">D</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="difficulty">難易度</label>
                                <select id="difficulty" name="difficulty">
                                    <option value="1">易しい</option>
                                    <option value="2">普通</option>
                                    <option value="3">難しい</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_question" class="btn">➕ 問題を追加</button>
                    </form>
                </div>
                
                <h3 class="form-title">📋 登録済み問題一覧</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>問題文</th>
                            <th>選択肢</th>
                            <th>正解</th>
                            <th>難易度</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $q): ?>
                            <?php $choices = json_decode($q['choices'], true); ?>
                            <tr>
                                <td><?= $q['id'] ?></td>
                                <td class="question-preview"><?= htmlspecialchars($q['question']) ?></td>
                                <td class="choices-preview">
                                    A: <?= htmlspecialchars($choices[0] ?? '') ?><br>
                                    B: <?= htmlspecialchars($choices[1] ?? '') ?><br>
                                    <?php if (!empty($choices[2])): ?>
                                        C: <?= htmlspecialchars($choices[2]) ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($choices[3])): ?>
                                        D: <?= htmlspecialchars($choices[3]) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= chr(65 + $q['answer']) ?></td>
                                <td>
                                    <?php
                                    $diff = ['', '易', '普', '難'];
                                    echo $diff[$q['difficulty']] ?? '普';
                                    ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('この問題を削除しますか？')">
                                        <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                        <button type="submit" name="delete_question" class="btn btn-danger">🗑️ 削除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- ユーザー管理タブ -->
            <div id="users" class="tab-content">
                <h3 class="form-title">👥 参加ユーザー一覧</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>名前</th>
                            <th>スコア</th>
                            <th>参加日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td><?= $u['score'] ?>点</td>
                                <td><?= date('m/d H:i', strtotime($u['joined_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 設定タブ -->
            <div id="settings" class="tab-content">
                <div class="form-section">
                    <h3 class="form-title">🔧 システム設定</h3>
                    <div style="margin-bottom: 2rem;">
                        <h4>現在の設定</h4>
                        <ul style="margin-top: 1rem; padding-left: 2rem;">
                            <li>1ラウンドの問題数: <?= QUESTIONS_PER_ROUND ?>問</li>
                            <li>制限時間: <?= TIME_LIMIT ?>秒</li>
                            <li>正解スコア: +<?= CORRECT_SCORE ?>点</li>
                            <li>不正解ペナルティ: <?= INCORRECT_PENALTY ?>点</li>
                        </ul>
                    </div>
                    
                    <h4>危険な操作</h4>
                    <form method="POST" onsubmit="return confirm('全ユーザーのスコアと回答履歴をリセットします。よろしいですか？')">
                        <button type="submit" name="reset_users" class="btn btn-danger">
                            🔄 全データリセット
                        </button>
                        <p style="color: #666; font-size: 0.9rem; margin-top: 0.5rem;">
                            ※ 全ユーザーのスコアを0にリセットし、回答履歴を削除します
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // 全てのタブコンテンツを非表示
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // 全てのタブボタンを非アクティブ
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // 指定されたタブコンテンツを表示
            document.getElementById(tabName).classList.add('active');
            
            // クリックされたタブボタンをアクティブに
            event.target.classList.add('active');
        }
        
        // フォームの入力チェック
        document.querySelector('form').addEventListener('submit', function(e) {
            const question = document.getElementById('question').value.trim();
            const choice1 = document.getElementById('choice1').value.trim();
            const choice2 = document.getElementById('choice2').value.trim();
            
            if (!question || !choice1 || !choice2) {
                alert('問題文と最低2つの選択肢は必須です');
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
