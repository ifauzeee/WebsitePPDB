<?php
session_start();
header('Content-Type: application/json');

// Error logging setup
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/stmgotham/login/error.log');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
    exit;
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student' || !isset($_SESSION['student_id'])) {
    error_log("Unauthorized access: user_type={$_SESSION['user_type']}, student_id={$_SESSION['student_id']}");
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid']);
    exit;
}

$session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
$question_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
$selected_answer = isset($_POST['selected_answer']) ? trim($_POST['selected_answer']) : '';

if ($session_id <= 0 || $question_id <= 0) {
    error_log("Invalid input: session_id=$session_id, question_id=$question_id");
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit;
}

if ($selected_answer !== '' && !in_array($selected_answer, ['A', 'B', 'C', 'D'])) {
    error_log("Invalid answer: selected_answer=$selected_answer");
    echo json_encode(['success' => false, 'message' => 'Jawaban tidak valid']);
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "stm_gotham";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
    exit;
}

// Verify session belongs to the student
$sql = "SELECT id, status FROM exam_sessions WHERE id = ? AND registration_id = ? AND status = 'In Progress'";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed for session check: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Kesalahan server']);
    $conn->close();
    exit;
}
$stmt->bind_param("ii", $session_id, $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$session = $result->fetch_assoc();
$stmt->close();

if (!$session) {
    error_log("Invalid or completed session: session_id=$session_id, student_id={$_SESSION['student_id']}");
    echo json_encode(['success' => false, 'message' => 'Sesi ujian tidak valid atau telah selesai']);
    $conn->close();
    exit;
}

// Handle answer saving or deletion
if ($selected_answer === '') {
    // Delete existing answer
    $sql = "DELETE FROM answers WHERE exam_session_id = ? AND question_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for answer deletion: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Kesalahan server']);
        $conn->close();
        exit;
    }
    $stmt->bind_param("ii", $session_id, $question_id);
} else {
    // Insert or update answer
    $sql = "INSERT INTO answers (exam_session_id, question_id, selected_answer) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE selected_answer = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for answer insert/update: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Kesalahan server']);
        $conn->close();
        exit;
    }
    $stmt->bind_param("iiss", $session_id, $question_id, $selected_answer, $selected_answer);
}

if ($stmt->execute()) {
    error_log("Answer saved/deleted: session_id=$session_id, question_id=$question_id, answer=$selected_answer");
    echo json_encode(['success' => true]);
} else {
    error_log("Failed to save/delete answer: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan jawaban']);
}
$stmt->close();
$conn->close();
?>