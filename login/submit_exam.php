<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "stm_gotham";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
    exit;
}

// Validate input
$session_id = $_POST['session_id'] ?? 0;
if (!$session_id) {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid.']);
    $conn->close();
    exit;
}

// Calculate score and store results
$sql = "SELECT a.question_id, a.selected_answer, q.correct_answer 
        FROM answers a 
        JOIN questions q ON a.question_id = q.id 
        WHERE a.exam_session_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $session_id);
$stmt->execute();
$result = $stmt->get_result();

$score = 0;
$total_questions = 20; // Fixed for 20 questions
$results = [];
while ($row = $result->fetch_assoc()) {
    $is_correct = ($row['selected_answer'] === $row['correct_answer']) ? 1 : 0;
    if ($is_correct) {
        $score += 5; // 5 points per correct answer
    }
    $results[] = [
        'question_id' => $row['question_id'],
        'selected_answer' => $row['selected_answer'],
        'is_correct' => $is_correct
    ];
}
$stmt->close();

// Insert detailed results into exam_results
foreach ($results as $result) {
    $sql = "INSERT INTO exam_results (exam_session_id, question_id, selected_answer, is_correct) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisi", $session_id, $result['question_id'], $result['selected_answer'], $result['is_correct']);
    $stmt->execute();
    $stmt->close();
}

// Update exam session with score
$sql = "UPDATE exam_sessions SET status = 'Completed', end_time = NOW(), score = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $score, $session_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'redirect' => 'exam_results.php?session_id=' . $session_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menyelesaikan ujian.']);
}
$stmt->close();
$conn->close();
?>