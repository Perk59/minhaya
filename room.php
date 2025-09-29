<?php
require_once 'config.php';
header('Content-Type: application/json');

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getConnection();
$response = ['success' => false];

try {
    $action = $_POST['action'] ?? '';
    $room_id = intval($_POST['room_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    
    // ルーム存在確認
    $stmt = $pdo->prepare("SELECT * FROM game_rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    
    if (!$room) {
        throw new Exception('Room not found');
    }
    
    switch ($action) {
        case 'buzz':
            // 早押し処理
            $question_id = intval($_POST['question_id']);
            
            // 現在のゲーム状態を取得
            $stmt = $pdo->prepare("SELECT * FROM game_states WHERE room_id = ? AND question_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$room_id, $question_id]);
            $game_state = $stmt->fetch();
            
            if (!$game_state || $game_state['status'] !== 'revealing') {
                throw new Exception('Cannot buzz at this time');
            }
            
            // 既に誰かが早押ししていないかチェック
            if ($game_state['buzzer_user_id']) {
                throw new Exception('Someone already buzzed');
            }
            
            // 早押し成功 - ゲーム状態を更新
            $stmt = $pdo->prepare("UPDATE game_states SET buzzer_user_id = ?, buzzer_timestamp = NOW(), status = 'buzzed' WHERE id = ?");
            $stmt->execute([$user_id, $game_state['id']]);
            
            // 問題文を全て表示状態に
            $stmt = $pdo->prepare("SELECT question FROM questions WHERE id = ?");
            $stmt->execute([$question_id]);
            $question = $stmt->fetch();
            
            $stmt = $pdo->prepare("UPDATE game_states SET revealed_text = ?, reveal_progress = 100.00 WHERE id = ?");
            $stmt->execute([$question['question'], $game_state['id']]);
            
            $response['success'] = true;
            $response['message'] = 'Buzzer pressed successfully';
            break;
            
        case 'answer':
            // 回答処理
            $question_id = intval($_POST['question_id']);
            $choice = intval($_POST['choice']);
            
            // 現在のゲーム状態を確認
            $stmt = $pdo->prepare("SELECT * FROM game_states WHERE room_id = ? AND question_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$room_id, $question_id]);
            $game_state = $stmt->fetch();
            
            if (!$game_state || $game_state['buzzer_user_id'] != $user_id) {
                throw new Exception('Not authorized to answer');
            }
            
            // 問題の正解を取得
            $stmt = $pdo->prepare("SELECT answer FROM questions WHERE id = ?");
            $stmt->execute([$question_id]);
            $question = $stmt->fetch();
            
            $is_correct = ($choice === intval($question['answer'])) ? 1 : 0;
            $score_change = $is_correct ? CORRECT_SCORE : INCORRECT_PENALTY;
            
            // 回答記録を保存
            $stmt = $pdo->prepare("INSERT INTO answers (user_id, question_id, choice, is_correct, answered_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $question_id, $choice, $is_correct]);
            
            // スコア更新
            $stmt = $pdo->prepare("UPDATE users SET score = score + ? WHERE id = ?");
            $stmt->execute([$score_change, $user_id]);
            
            // ゲーム状態を更新
            $stmt = $pdo->prepare("UPDATE game_states SET status = 'answered' WHERE id = ?");
            $stmt->execute([$game_state['id']]);
            
            $response['success'] = true;
            $response['is_correct'] = $is_correct;
            $response['score_change'] = $score_change;
            break;
            
        case 'timeout':
            // 時間切れ処理
            $question_id = intval($_POST['question_id']);
            
            // 回答記録（時間切れ）
            $stmt = $pdo->prepare("INSERT INTO answers (user_id, question_id, choice, is_correct, answered_at) VALUES (?, ?, -2, 0, NOW())");
            $stmt->execute([$user_id, $question_id]);
            
            // ゲーム状態を更新
            $stmt = $pdo->prepare("UPDATE game_states SET status = 'timeout' WHERE room_id = ? AND question_id = ?");
            $stmt->execute([$room_id, $question_id]);
            
            $response['success'] = true;
            break;
            
        case 'get_players':
            // プレイヤー一覧取得
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.score
                FROM room_participants rp
                JOIN users u ON rp.user_id = u.id
                WHERE rp.room_id = ?
                ORDER BY u.score DESC
            ");
            $stmt->execute([$room_id]);
            $players = $stmt->fetchAll();
            
            $response['success'] = true;
            $response['players'] = $players;
            break;
            
        case 'next_question':
            // 次の問題に進む（ホストのみ）
            if ($room['host_user_id'] != $user_id) {
                throw new Exception('Only host can advance to next question');
            }
            
            // 現在のゲーム状態を取得
            $stmt = $pdo->prepare("SELECT * FROM game_states WHERE room_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$room_id]);
            $current_state = $stmt->fetch();
            
            if (!$current_state) {
                throw new Exception('No current game state found');
            }
            
            $next_question_number = $current_state['question_number'] + 1;
            
            // ゲーム終了チェック（10問完了 or 誰かが5問正解）
            if ($next_question_number > 10) {
                $stmt = $pdo->prepare("UPDATE game_rooms SET status = 'finished' WHERE id = ?");
                $stmt->execute([$room_id]);
                $response['success'] = true;
                $response['game_finished'] = true;
                break;
            }
            
            // 5問正解者チェック
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, COUNT(*) as correct_count
                FROM answers a
                JOIN users u ON a.user_id = u.id
                JOIN room_participants rp ON rp.user_id = u.id
                WHERE rp.room_id = ? AND a.is_correct = 1
                GROUP BY u.id
                HAVING correct_count >= 5
                ORDER BY correct_count DESC, MIN(a.answered_at) ASC
                LIMIT 1
            ");
            $stmt->execute([$room_id]);
            $winner = $stmt->fetch();
            
            if ($winner) {
                // 勝者が決定 - ゲーム終了
                $stmt = $pdo->prepare("UPDATE game_rooms SET status = 'finished' WHERE id = ?");
                $stmt->execute([$room_id]);
                $response['success'] = true;
                $response['game_finished'] = true;
                $response['winner'] = $winner;
                break;
            }
            
            // 次の問題を選択
            $stmt = $pdo->query("SELECT * FROM questions ORDER BY RAND() LIMIT 1");
            $next_question = $stmt->fetch();
            
            if (!$next_question) {
                throw new Exception('No questions available');
            }
            
            // 新しいゲーム状態を作成
            $stmt = $pdo->prepare("
                INSERT INTO game_states (room_id, question_id, question_number, revealed_text, reveal_progress, status) 
                VALUES (?, ?, ?, '', 0.00, 'revealing')
            ");
            $stmt->execute([$room_id, $next_question['id'], $next_question_number]);
            
            $response['success'] = true;
            $response['game_finished'] = false;
            $response['next_question_number'] = $next_question_number;
            break;
            
        case 'get_game_state':
    // 現在のゲーム状態を取得
    $stmt = $pdo->prepare("
        SELECT gs.*, q.level
        FROM game_states gs
        JOIN questions q ON gs.question_id = q.id
        WHERE gs.room_id = ?
        ORDER BY gs.id DESC LIMIT 1
    ");
    $stmt->execute([$room_id]);
    $state = $stmt->fetch();
    
    $response = [
        'success' => true,
              'state' => $state,
                'newQuestion' => false
         ];
    
            if ($state['status'] === 'completed' && time() - strtotime($state['updated_at']) > 5) {
                $response['newQuestion'] = true;
            }
        break;
            
        case 'leave_room':
            // ルーム退出処理
            $stmt = $pdo->prepare("DELETE FROM room_participants WHERE room_id = ? AND user_id = ?");
            $stmt->execute([$room_id, $user_id]);
            
            // ホストが退出した場合、ルームを削除
            if ($room['host_user_id'] == $user_id) {
                $stmt = $pdo->prepare("DELETE FROM game_rooms WHERE id = ?");
                $stmt->execute([$room_id]);
            } else {
                // 参加者がいなくなったらルーム削除
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM room_participants WHERE room_id = ?");
                $stmt->execute([$room_id]);
                $participant_count = $stmt->fetch();
                
                if ($participant_count['count'] == 0) {
                    $stmt = $pdo->prepare("DELETE FROM game_rooms WHERE id = ?");
                    $stmt->execute([$room_id]);
                }
            }
            
            $response['success'] = true;
            break;
            
        case 'get_room_status':
            // ルーム状況取得（リアルタイム更新用）
            $stmt = $pdo->prepare("
                SELECT gr.status, gs.status as game_status, gs.buzzer_user_id, u.name as buzzer_user_name
                FROM game_rooms gr
                LEFT JOIN game_states gs ON gr.id = gs.room_id
                LEFT JOIN users u ON gs.buzzer_user_id = u.id
                WHERE gr.id = ?
                ORDER BY gs.id DESC
                LIMIT 1
            ");
            $stmt->execute([$room_id]);
            $status = $stmt->fetch();
            
            $response['success'] = true;
            $response['room_status'] = $status;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    $response['error'] = 'Database error: ' . $e->getMessage();
    error_log('Multiplayer API Database error: ' . $e->getMessage());
} catch (Exception $e) {
    http_response_code(400);
    $response['error'] = $e->getMessage();
    error_log('Multiplayer API error: ' . $e->getMessage());
}

echo json_encode($response);
?>
