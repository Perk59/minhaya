<?php
require_once 'config.php';
header('Content-Type: application/json');

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$room_id = intval($_GET['room_id'] ?? 0);

if (!$room_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Room ID required']);
    exit;
}

try {
    $pdo = getConnection();
    
    // ルーム情報取得
    $stmt = $pdo->prepare("SELECT status FROM game_rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    
    if (!$room) {
        echo json_encode(['success' => false, 'error' => 'Room not found']);
        exit;
    }
    
    // 参加者情報取得
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.score, rp.is_ready
        FROM room_participants rp
        JOIN users u ON rp.user_id = u.id
        WHERE rp.room_id = ?
        ORDER BY rp.joined_at ASC
    ");
    $stmt->execute([$room_id]);
    $participants = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'room_status' => $room['status'],
        'participants' => $participants,
        'participant_count' => count($participants)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    error_log('Room status error: ' . $e->getMessage());
}
?>
