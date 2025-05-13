<?php
session_start();

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Error logging setup
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/stmgotham/login/error.log');

// Check if student is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student' || !isset($_SESSION['email'])) {
    error_log("Invalid session: user_type or email not set");
    $_SESSION['error'] = "Sesi tidak valid. Silakan login kembali.";
    header("Location: login.php");
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "stm_gotham";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    $_SESSION['error'] = "Koneksi database gagal.";
    header("Location: login.php");
    exit;
}

// Fetch student data
$email = $_SESSION['email'];
$sql = "SELECT id, full_name, major_id, exam_permission FROM registrations WHERE email = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed for student data: " . $conn->error);
    $_SESSION['error'] = "Kesalahan server.";
    $conn->close();
    header("Location: login.php");
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    error_log("Student not found: email=$email");
    $_SESSION['error'] = "Data siswa tidak ditemukan.";
    $conn->close();
    header("Location: login.php");
    exit;
}

// Store student_id in session for validation
$_SESSION['student_id'] = $student['id'];

// Check exam permission
if ($student['exam_permission'] != 1) {
    error_log("Exam permission denied: student_id={$student['id']}");
    $_SESSION['error'] = "Anda belum diizinkan untuk mengikuti ujian.";
    $conn->close();
    header("Location: student_dashboard.php");
    exit;
}

// Check major_id
if (!$student['major_id']) {
    error_log("Major ID missing: student_id={$student['id']}");
    $_SESSION['error'] = "Jurusan tidak ditemukan. Silakan perbarui profil Anda.";
    $conn->close();
    header("Location: student_dashboard.php");
    exit;
}

// Check for active or completed exam session
$sql = "SELECT id, start_time, status FROM exam_sessions WHERE registration_id = ? AND status IN ('In Progress', 'Scheduled')";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed for exam session check: " . $conn->error);
    $_SESSION['error'] = "Kesalahan server.";
    $conn->close();
    header("Location: student_dashboard.php");
    exit;
}
$stmt->bind_param("i", $student['id']);
$stmt->execute();
$result = $stmt->get_result();
$session = $result->fetch_assoc();
$stmt->close();

// Handle session state
$duration = 60 * 60; // 60 minutes in seconds
if ($session) {
    if ($session['status'] === 'Completed') {
        error_log("Exam already completed: student_id={$student['id']}, session_id={$session['id']}");
        $_SESSION['error'] = "Anda sudah menyelesaikan ujian.";
        $conn->close();
        header("Location: student_dashboard.php");
        exit;
    }
    $start_time = strtotime($session['start_time']);
    $now = time();
    if ($now - $start_time > $duration) {
        // Mark session as completed if expired
        $sql = "UPDATE exam_sessions SET status = 'Completed', end_time = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $session['id']);
            $stmt->execute();
            $stmt->close();
            error_log("Session expired: session_id={$session['id']}, student_id={$student['id']}");
        } else {
            error_log("Prepare failed for session expiration: " . $conn->error);
        }
        $_SESSION['error'] = "Sesi ujian telah berakhir.";
        $conn->close();
        header("Location: student_dashboard.php");
        exit;
    }
} else {
    // Create new exam session
    $sql = "INSERT INTO exam_sessions (registration_id, major_id, start_time, status) VALUES (?, ?, NOW(), 'In Progress')";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for session creation: " . $conn->error);
        $_SESSION['error'] = "Gagal membuat sesi ujian.";
        $conn->close();
        header("Location: student_dashboard.php");
        exit;
    }
    $stmt->bind_param("ii", $student['id'], $student['major_id']);
    if (!$stmt->execute()) {
        error_log("Failed to create exam session: " . $stmt->error);
        $_SESSION['error'] = "Gagal membuat sesi ujian.";
        $stmt->close();
        $conn->close();
        header("Location: student_dashboard.php");
        exit;
    }
    $session = ['id' => $stmt->insert_id, 'start_time' => date('Y-m-d H:i:s'), 'status' => 'In Progress'];
    error_log("Created new exam session: session_id={$session['id']}, student_id={$student['id']}");
    $stmt->close();
}

// Fetch questions for the student's major
$sql = "SELECT id, question_text, option_a, option_b, option_c, option_d FROM questions WHERE major_id = ? ORDER BY id";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed for questions fetch: " . $conn->error);
    $_SESSION['error'] = "Kesalahan server.";
    $conn->close();
    header("Location: student_dashboard.php");
    exit;
}
$stmt->bind_param("i", $student['major_id']);
$stmt->execute();
$result = $stmt->get_result();
$questions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($questions)) {
    error_log("No questions found for major_id={$student['major_id']}");
    $_SESSION['error'] = "Tidak ada soal tersedia untuk jurusan Anda.";
    $conn->close();
    header("Location: student_dashboard.php");
    exit;
}

// Fetch answered questions
$sql = "SELECT question_id, selected_answer FROM answers WHERE exam_session_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed for answers fetch: " . $conn->error);
    $_SESSION['error'] = "Kesalahan server.";
    $conn->close();
    header("Location: student_dashboard.php");
    exit;
}
$stmt->bind_param("i", $session['id']);
$stmt->execute();
$result = $stmt->get_result();
$answers = [];
while ($row = $result->fetch_assoc()) {
    $answers[$row['question_id']] = $row['selected_answer'];
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Exam Session - STM Gotham City</title>
    <link rel="icon" href="https://cdn.pixabay.com/photo/2018/10/05/21/29/bat-3726896_1280.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            margin: 0;
            min-height: 100vh;
            position: relative;
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
                radial-gradient(circle at 15% 50%, rgba(59, 130, 246, 0.08) 0%, transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.08) 0%, transparent 25%);
            z-index: -1;
        }

        .navbar-fixed {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }

        .card {
            background: rgba(30, 41, 59, 0.8);
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        .btn {
            transition: all 0.3s ease;
            border-radius: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: all 0.4s ease;
            z-index: -1;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
            padding: 0.75rem 2rem;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.5);
        }

        .btn-success {
            background: #10b981;
            color: #fff;
            padding: 0.75rem 2rem;
        }

        .btn-success:hover {
            background: #059669;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5);
        }

        .btn-warning {
            background: #f59e0b;
            color: #fff;
            padding: 0.75rem 2rem;
        }

        .btn-warning:hover {
            background: #d97706;
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.5);
        }

        .btn-mark {
            background: #eab308;
            color: #fff;
            padding: 0.75rem 2rem;
        }

        .btn-mark:hover {
            background: #ca8a04;
            box-shadow: 0 8px 25px rgba(234, 179, 8, 0.5);
        }

        .btn-danger {
            background: #ef4444;
            color: #fff;
            padding: 0.75rem 2rem;
        }

        .btn-danger:hover {
            background: #dc2626;
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.5);
        }

        .btn:disabled {
            background: #4b5563;
            cursor: not-allowed;
        }

        .option-label {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .option-label:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .option-label input:checked + span {
            background: #2563eb;
            color: #fff;
            border-radius: 0.5rem;
            padding: 0.5rem;
        }

        .timer {
            font-size: 1.5rem;
            font-weight: 600;
            color: #f59e0b;
        }

        .question-nav {
            background: rgba(30, 41, 59, 0.9);
            border-radius: 1rem;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .question-nav-item {
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
        }

        .question-nav-item.answered {
            background: #10b981;
            color: #fff;
        }

        .question-nav-item.marked {
            background: #eab308;
            color: #fff;
        }

        .question-nav-item.current {
            background: #2563eb;
            color: #fff;
            font-weight: bold;
        }

        .question-nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .progress-bar {
            height: 0.5rem;
            border-radius: 0.25rem;
            background: rgba(255, 255, 255, 0.1);
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 0.25rem;
            background: #10b981;
            transition: width 0.3s ease;
        }

        .fade {
            animation: fade 0.3s ease-in-out;
        }

        @keyframes fade {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #2563eb;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            display: none;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav id="navbar" class="fixed w-full top-0 z-50 navbar-fixed py-4">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <a href="../index.html" class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center shadow-lg">
                        <img src="https://cdn.pixabay.com/photo/2018/10/05/21/29/bat-3726896_1280.png" alt="Logo STM Gotham City" class="w-10 h-10 object-contain">
                    </div>
                    <div class="text-white font-bold text-xl">STM Gotham City</div>
                </a>
            </div>
            <div class="flex items-center space-x-4">
                <div class="timer" id="exam-timer" aria-live="polite">00:00:00</div>
                <button id="review-exam" class="btn btn-warning">Tinjau Jawaban</button>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container mx-auto p-6 pt-24 flex">
        <!-- Question Navigation Sidebar -->
        <aside class="w-1/4 pr-6">
            <div class="question-nav sticky top-24">
                <h3 class="text-lg font-semibold mb-4">Navigasi Soal</h3>
                <div class="grid grid-cols-5 gap-2">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-nav-item <?php echo isset($answers[$question['id']]) ? 'answered' : ''; ?>" 
                             data-question-index="<?php echo $index; ?>"
                             aria-label="Soal <?php echo $index + 1; ?><?php echo isset($answers[$question['id']]) ? ', sudah dijawab' : ''; ?>">
                            <?php echo $index + 1; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-6">
                    <h4 class="text-sm font-medium mb-2">Progres</h4>
                    <div class="progress-bar">
                        <div class="progress-bar-fill" id="progress-bar-fill" style="width: 0%"></div>
                    </div>
                    <p class="text-sm text-gray-400 mt-2" id="progress-text">0 dari <?php echo count($questions); ?> dijawab</p>
                </div>
            </div>
        </aside>

        <!-- Questions -->
        <section class="w-3/4">
            <div class="card p-6">
                <h1 class="text-2xl font-bold mb-4">Ujian Online - <?php echo htmlspecialchars($student['full_name']); ?></h1>

                <div id="question-container">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question fade hidden" data-question-id="<?php echo $question['id']; ?>" id="question-<?php echo $index; ?>">
                            <h3 class="text-lg font-semibold mb-4">
                                Soal <?php echo $index + 1; ?>:
                                <?php echo htmlspecialchars($question['question_text']); ?>
                            </h3>
                            <div class="space-y-3">
                                <?php foreach (['a', 'b', 'c', 'd'] as $option): ?>
                                    <label class="option-label" aria-label="Pilihan <?php echo strtoupper($option); ?>">
                                        <input type="radio" name="answer_<?php echo $question['id']; ?>" value="<?php echo strtoupper($option); ?>" 
                                            class="mr-2" <?php echo isset($answers[$question['id']]) && $answers[$question['id']] === strtoupper($option) ? 'checked' : ''; ?>
                                            aria-checked="<?php echo isset($answers[$question['id']]) && $answers[$question['id']] === strtoupper($option) ? 'true' : 'false'; ?>">
                                        <span class="flex-1"><?php echo htmlspecialchars($question['option_' . $option]); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="spinner mt-4" id="spinner-<?php echo $question['id']; ?>"></div>
                        </div>
                    <?php endforeach; ?>
                    <!-- Review Page -->
                    <div class="review hidden" id="review-page">
                        <h3 class="text-lg font-semibold mb-4">Tinjau Jawaban</h3>
                        <p class="text-gray-400 mb-6">Periksa jawaban Anda sebelum mengakhiri ujian.</p>
                        <div id="review-list" class="space-y-4"></div>
                    </div>
                </div>

                <div class="flex justify-between mt-6 space-x-2">
                    <div class="flex flex-col space-y-2">
                        <button id="prev-question" class="btn btn-primary" disabled>Sebelumnya</button>
                        <button id="mark-question" class="btn btn-mark">Tandai Soal</button>
                        <button id="clear-answer" class="btn btn-danger">Hapus Jawaban</button>
                    </div>
                    <button id="next-question" class="btn btn-primary">Selanjutnya</button>
                </div>
                <div class="flex justify-end mt-4">
                    <button id="submit-exam" class="btn btn-success">Selesai Ujian</button>
                </div>
            </div>
        </section>
    </main>

    <script>
        const sessionId = <?php echo json_encode($session['id']); ?>;
        const questions = document.querySelectorAll('.question');
        const reviewPage = document.getElementById('review-page');
        const reviewList = document.getElementById('review-list');
        const navItems = document.querySelectorAll('.question-nav-item');
        let currentQuestion = 0;
        const totalQuestions = questions.length;
        let answers = <?php echo json_encode($answers); ?>;
        let isSubmitting = false;
        let isExamSubmitted = false;
        let timerInterval = null;
        let lastSubmitAttempt = 0;
        const SUBMIT_DEBOUNCE_MS = 5000; // 5 seconds debounce
        let markedQuestions = JSON.parse(localStorage.getItem('markedQuestions') || '{}');

        // Update progress
        function updateProgress() {
            const answeredCount = Object.keys(answers).length;
            const progressPercent = (answeredCount / totalQuestions) * 100;
            document.getElementById('progress-bar-fill').style.width = `${progressPercent}%`;
            document.getElementById('progress-text').textContent = `${answeredCount} dari ${totalQuestions} dijawab`;
            navItems.forEach((item, index) => {
                const questionId = questions[index].dataset.questionId;
                item.classList.toggle('answered', !!answers[questionId]);
                item.classList.toggle('marked', !!markedQuestions[questionId]);
            });
        }

        // Show current question or review page
        function showQuestion(index, isReview = false) {
            if (isExamSubmitted) return;
            questions.forEach((q, i) => q.classList.toggle('hidden', i !== index || isReview));
            reviewPage.classList.toggle('hidden', !isReview);
            navItems.forEach((item, i) => {
                item.classList.toggle('current', i === index && !isReview);
                const questionId = questions[i].dataset.questionId;
                item.classList.toggle('answered', !!answers[questionId]);
                item.classList.toggle('marked', !!markedQuestions[questionId]);
            });
            document.getElementById('prev-question').disabled = index === 0 && !isReview || isExamSubmitted;
            document.getElementById('next-question').disabled = isExamSubmitted;
            document.getElementById('next-question').textContent = isReview ? 'Kembali' : (index === totalQuestions - 1 ? 'Tinjau' : 'Selanjutnya');
            document.getElementById('submit-exam').disabled = isExamSubmitted;
            document.getElementById('review-exam').disabled = isExamSubmitted;
            document.getElementById('mark-question').disabled = isExamSubmitted;
            document.getElementById('clear-answer').disabled = isExamSubmitted;
            if (isReview) {
                populateReview();
            }
        }

        // Populate review page
        function populateReview() {
            reviewList.innerHTML = '';
            questions.forEach((question, index) => {
                const questionId = question.dataset.questionId;
                const answer = answers[questionId] || 'Belum Dijawab';
                const isMarked = markedQuestions[questionId] ? '<span class="text-yellow-500">[Ditandai]</span>' : '';
                const div = document.createElement('div');
                div.className = 'p-4 bg-gray-800 rounded-lg';
                div.innerHTML = `
                    <p class="font-semibold">Soal ${index + 1}: ${question.querySelector('h3').textContent.split(': ')[1]} ${isMarked}</p>
                    <p class="text-gray-400">Jawaban: ${answer}</p>
                    <button class="text-blue-500 hover:underline" data-question-index="${index}">Ubah Jawaban</button>
                `;
                reviewList.appendChild(div);
            });
            reviewList.querySelectorAll('button').forEach(button => {
                button.addEventListener('click', () => {
                    if (isExamSubmitted) return;
                    currentQuestion = parseInt(button.dataset.questionIndex);
                    showQuestion(currentQuestion);
                });
            });
        }

        // Save answer via AJAX
        function saveAnswer(questionId, answer, callback) {
            if (isSubmitting || isExamSubmitted) return;
            isSubmitting = true;
            const spinner = document.getElementById(`spinner-${questionId}`);
            spinner.style.display = 'block';
            fetch('save_answer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `session_id=${sessionId}&question_id=${questionId}&selected_answer=${encodeURIComponent(answer)}`
            })
            .then(response => response.json())
            .then(data => {
                isSubmitting = false;
                spinner.style.display = 'none';
                if (data.success) {
                    if (answer === '') {
                        delete answers[questionId];
                    } else {
                        answers[questionId] = answer;
                    }
                    updateProgress();
                    callback(true);
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Gagal menyimpan jawaban. Coba lagi.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    callback(false);
                }
            })
            .catch(error => {
                isSubmitting = false;
                spinner.style.display = 'none';
                Swal.fire({
                    title: 'Error',
                    text: 'Gagal menyimpan jawaban. Periksa koneksi Anda.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                console.error('Save answer error:', error);
                callback(false);
            });
        }

        // Timer logic
        const startTimeStr = '<?php echo $session['start_time']; ?>';
        const startTime = new Date(startTimeStr).getTime();
        const duration = 60 * 60 * 1000; // 60 minutes in milliseconds
        const timerElement = document.getElementById('exam-timer');
        let hasWarned = false;

        // Validate startTime
        if (isNaN(startTime)) {
            console.error('Invalid startTime:', startTimeStr);
            Swal.fire({
                title: 'Error',
                text: 'Waktu mulai ujian tidak valid. Sesi akan ditutup.',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = 'student_dashboard.php';
            });
        } else {
            console.log('Timer initialized with startTime:', startTimeStr, 'sessionId:', sessionId);

            function updateTimer() {
                if (isExamSubmitted) {
                    console.log('Timer stopped: exam submitted');
                    clearInterval(timerInterval);
                    timerInterval = null;
                    return;
                }

                const now = new Date().getTime();
                const elapsed = now - startTime;
                const remaining = duration - elapsed;

                if (remaining <= 0) {
                    console.log('Timer expired, submitting exam');
                    timerElement.textContent = '00:00:00';
                    clearInterval(timerInterval);
                    timerInterval = null;
                    submitExam();
                    return;
                }

                const hours = Math.floor(remaining / (1000 * 60 * 60));
                const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((remaining % (1000 * 60)) / 1000);
                timerElement.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                console.log('Timer tick:', timerElement.textContent);

                if (remaining <= 5 * 60 * 1000 && !hasWarned) {
                    console.log('Showing 5-minute warning');
                    Swal.fire({
                        title: 'Peringatan',
                        text: 'Waktu ujian tinggal 5 menit!',
                        icon: 'warning',
                        timer: 3000,
                        showConfirmButton: false
                    });
                    hasWarned = true;
                }
            }

            // Clear any existing timer
            if (window.examTimerInterval) {
                console.warn('Clearing existing timer interval:', window.examTimerInterval);
                clearInterval(window.examTimerInterval);
            }

            // Start new timer
            timerInterval = setInterval(updateTimer, 1000);
            window.examTimerInterval = timerInterval;
            console.log('Timer started, interval ID:', timerInterval);
        }

        // Submit exam
        function submitExam() {
            const now = Date.now();
            if (isExamSubmitted || now - lastSubmitAttempt < SUBMIT_DEBOUNCE_MS) {
                console.log('Exam submission blocked: isExamSubmitted=', isExamSubmitted, 'timeSinceLastAttempt=', now - lastSubmitAttempt);
                return;
            }
            isExamSubmitted = true;
            lastSubmitAttempt = now;
            console.log('Submitting exam, sessionId=', sessionId);
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
                window.examTimerInterval = null;
                console.log('Timer cleared on submit');
            }
            document.getElementById('submit-exam').disabled = true;
            document.getElementById('prev-question').disabled = true;
            document.getElementById('next-question').disabled = true;
            document.getElementById('review-exam').disabled = true;
            document.getElementById('mark-question').disabled = true;
            document.getElementById('clear-answer').disabled = true;
            Swal.fire({
                title: 'Selesai Ujian',
                text: 'Apakah Anda yakin ingin mengakhiri ujian? Pastikan semua jawaban telah disimpan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Selesai',
                cancelButtonText: 'Batal',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('submit_exam.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `session_id=${sessionId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Ujian Selesai',
                                text: 'Ujian Anda telah disimpan. Anda akan diarahkan ke halaman hasil.',
                                icon: 'success',
                                confirmButtonText: 'OK',
                                allowOutsideClick: false,
                                allowEscapeKey: false
                            }).then(() => {
                                console.log('Exam submitted successfully, redirecting to:', data.redirect);
                                localStorage.removeItem('markedQuestions');
                                window.location.href = data.redirect;
                            });
                        } else {
                            isExamSubmitted = false;
                            lastSubmitAttempt = 0;
                            document.getElementById('submit-exam').disabled = false;
                            document.getElementById('prev-question').disabled = currentQuestion === 0;
                            document.getElementById('next-question').disabled = false;
                            document.getElementById('review-exam').disabled = false;
                            document.getElementById('mark-question').disabled = false;
                            document.getElementById('clear-answer').disabled = false;
                            Swal.fire({
                                title: 'Error',
                                text: data.message || 'Gagal menyelesaikan ujian.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                            console.error('Submit exam failed:', data.message);
                        }
                    })
                    .catch(error => {
                        isExamSubmitted = false;
                        lastSubmitAttempt = 0;
                        document.getElementById('submit-exam').disabled = false;
                        document.getElementById('prev-question').disabled = currentQuestion === 0;
                        document.getElementById('next-question').disabled = false;
                        document.getElementById('review-exam').disabled = false;
                        document.getElementById('mark-question').disabled = false;
                        document.getElementById('clear-answer').disabled = false;
                        Swal.fire({
                            title: 'Error',
                            text: 'Gagal menyelesaikan ujian. Periksa koneksi Anda.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        console.error('Submit exam error:', error);
                    });
                } else {
                    isExamSubmitted = false;
                    lastSubmitAttempt = 0;
                    document.getElementById('submit-exam').disabled = false;
                    document.getElementById('prev-question').disabled = currentQuestion === 0;
                    document.getElementById('next-question').disabled = false;
                    document.getElementById('review-exam').disabled = false;
                    document.getElementById('mark-question').disabled = false;
                    document.getElementById('clear-answer').disabled = false;
                    if (!timerInterval) {
                        console.log('Restarting timer after cancel');
                        timerInterval = setInterval(updateTimer, 1000);
                        window.examTimerInterval = timerInterval;
                    }
                }
            });
        }

        // Mark question
        document.getElementById('mark-question').addEventListener('click', () => {
            if (isExamSubmitted) return;
            const questionId = questions[currentQuestion].dataset.questionId;
            if (markedQuestions[questionId]) {
                delete markedQuestions[questionId];
                Swal.fire({
                    title: 'Soal Tidak Ditandai',
                    text: 'Soal ini telah dihapus dari daftar tanda.',
                    icon: 'info',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                markedQuestions[questionId] = true;
                Swal.fire({
                    title: 'Soal Ditandai',
                    text: 'Soal ini telah ditandai untuk ditinjau.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
            localStorage.setItem('markedQuestions', JSON.stringify(markedQuestions));
            updateProgress();
        });

        // Clear answer
        document.getElementById('clear-answer').addEventListener('click', () => {
            if (isExamSubmitted) return;
            const questionId = questions[currentQuestion].dataset.questionId;
            Swal.fire({
                title: 'Hapus Jawaban',
                text: 'Apakah Anda yakin ingin menghapus jawaban untuk soal ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya',
                cancelButtonText: 'Batal'
            }).then(result => {
                if (result.isConfirmed) {
                    const radios = questions[currentQuestion].querySelectorAll('input[type="radio"]');
                    radios.forEach(radio => radio.checked = false);
                    saveAnswer(questionId, '', () => {
                        Swal.fire({
                            title: 'Jawaban Dihapus',
                            text: 'Jawaban untuk soal ini telah dihapus.',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    });
                }
            });
        });

        // Navigation
        document.getElementById('prev-question').addEventListener('click', () => {
            if (isExamSubmitted) return;
            if (currentQuestion > 0) {
                const currentQuestionElement = questions[currentQuestion];
                const selectedAnswer = currentQuestionElement.querySelector('input:checked');
                if (selectedAnswer) {
                    saveAnswer(currentQuestionElement.dataset.questionId, selectedAnswer.value, () => {
                        currentQuestion--;
                        showQuestion(currentQuestion);
                    });
                } else {
                    currentQuestion--;
                    showQuestion(currentQuestion);
                }
            }
        });

        document.getElementById('next-question').addEventListener('click', () => {
            if (isExamSubmitted) return;
            const currentQuestionElement = questions[currentQuestion];
            const selectedAnswer = currentQuestionElement.querySelector('input:checked');
            if (selectedAnswer) {
                saveAnswer(currentQuestionElement.dataset.questionId, selectedAnswer.value, () => {
                    if (currentQuestion < totalQuestions - 1) {
                        currentQuestion++;
                        showQuestion(currentQuestion);
                    } else {
                        showQuestion(currentQuestion, true);
                    }
                });
            } else {
                Swal.fire({
                    title: 'Belum Dijawab',
                    text: 'Apakah Anda ingin melanjutkan tanpa menjawab soal ini?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya',
                    cancelButtonText: 'Tidak'
                }).then(result => {
                    if (result.isConfirmed) {
                        if (currentQuestion < totalQuestions - 1) {
                            currentQuestion++;
                            showQuestion(currentQuestion);
                        } else {
                            showQuestion(currentQuestion, true);
                        }
                    }
                });
            }
        });

        // Question navigation sidebar
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                if (isExamSubmitted) return;
                const newIndex = parseInt(item.dataset.questionIndex);
                const currentQuestionElement = questions[currentQuestion];
                const selectedAnswer = currentQuestionElement.querySelector('input:checked');
                if (selectedAnswer) {
                    saveAnswer(currentQuestionElement.dataset.questionId, selectedAnswer.value, () => {
                        currentQuestion = newIndex;
                        showQuestion(currentQuestion);
                    });
                } else {
                    Swal.fire({
                        title: 'Belum Dijawab',
                        text: 'Apakah Anda ingin pindah tanpa menjawab soal ini?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya',
                        cancelButtonText: 'Tidak'
                    }).then(result => {
                        if (result.isConfirmed) {
                            currentQuestion = newIndex;
                            showQuestion(currentQuestion);
                        }
                    });
                }
            });
        });

        // Review exam
        document.getElementById('review-exam').addEventListener('click', () => {
            if (isExamSubmitted) return;
            const currentQuestionElement = questions[currentQuestion];
            const selectedAnswer = currentQuestionElement.querySelector('input:checked');
            if (selectedAnswer) {
                saveAnswer(currentQuestionElement.dataset.questionId, selectedAnswer.value, () => {
                    showQuestion(currentQuestion, true);
                });
            } else {
                showQuestion(currentQuestion, true);
            }
        });

        document.getElementById('submit-exam').addEventListener('click', () => {
            if (!isExamSubmitted) {
                console.log('Submit exam button clicked');
                submitExam();
            }
        });

        // Auto-save answers on change
        questions.forEach(question => {
            const radios = question.querySelectorAll('input[type="radio"]');
            radios.forEach(radio => {
                radio.addEventListener('change', () => {
                    if (isExamSubmitted) return;
                    saveAnswer(question.dataset.questionId, radio.value, () => {
                        navItems[parseInt(question.id.split('-')[1])].classList.add('answered');
                    });
                });
            });
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (isExamSubmitted) return;
            if (e.key === 'ArrowLeft' && currentQuestion > 0) {
                document.getElementById('prev-question').click();
            } else if (e.key === 'ArrowRight' && currentQuestion < totalQuestions - 1) {
                document.getElementById('next-question').click();
            }
        });

        // Clean up timer and marked questions on page unload
        window.addEventListener('beforeunload', () => {
            if (timerInterval) {
                clearInterval(timerInterval);
                console.log('Timer cleared on page unload');
            }
        });

        // Initial setup
        console.log('Initializing exam session, sessionId=', sessionId);
        showQuestion(currentQuestion);
        updateProgress();
    </script>
</body>
</html>