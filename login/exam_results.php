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
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student' || !isset($_SESSION['email']) || !isset($_SESSION['student_id'])) {
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
    header("Location: student_dashboard.php");
    exit;
}

// Validate session_id
$session_id = (int)($_GET['session_id'] ?? 0);
if ($session_id <= 0) {
    error_log("Invalid session_id: $session_id");
    $_SESSION['error'] = "Sesi ujian tidak valid.";
    header("Location: student_dashboard.php");
    exit;
}

// Fetch student data
$email = $_SESSION['email'];
$sql = "SELECT id, full_name FROM registrations WHERE email = ?";
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

if (!$student || $student['id'] !== $_SESSION['student_id']) {
    error_log("Student not found or ID mismatch: email=$email, student_id={$_SESSION['student_id']}");
    $_SESSION['error'] = "Data siswa tidak ditemukan.";
    $conn->close();
    header("Location: login.php");
    exit;
}

// Fetch exam session
$sql = "SELECT score, registration_id, start_time, end_time FROM exam_sessions WHERE id = ? AND status = 'Completed'";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed for exam session: " . $conn->error);
    $_SESSION['error'] = "Kesalahan server.";
    $conn->close();
    header("Location: student_dashboard.php");
    exit;
}
$stmt->bind_param("i", $session_id);
$stmt->execute();
$result = $stmt->get_result();
$session = $result->fetch_assoc();
$stmt->close();

if (!$session || $session['registration_id'] != $_SESSION['student_id']) {
    error_log("Invalid or incomplete session: session_id=$session_id, student_id={$_SESSION['student_id']}");
    $_SESSION['error'] = "Sesi ujian tidak valid atau tidak ditemukan.";
    $conn->close();
    header("Location: student_dashboard.php");
    exit;
}

// Fetch exam results (use DISTINCT to avoid duplicates)
$sql = "SELECT DISTINCT er.question_id, er.selected_answer, er.is_correct, q.question_text, q.correct_answer, q.option_a, q.option_b, q.option_c, q.option_d 
        FROM exam_results er 
        JOIN questions q ON er.question_id = q.id 
        WHERE er.exam_session_id = ? 
        ORDER BY er.question_id";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed for exam results: " . $conn->error);
    $_SESSION['error'] = "Kesalahan server.";
    $conn->close();
    header("Location: student_dashboard.php");
    exit;
}
$stmt->bind_param("i", $session_id);
$stmt->execute();
$result = $stmt->get_result();
$exam_results = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// Calculate statistics
$total_questions = count($exam_results);
$correct_answers = array_sum(array_column($exam_results, 'is_correct'));
$incorrect_answers = $total_questions - $correct_answers;
$score_percentage = $session['score'];

// Calculate duration
$start_time = strtotime($session['start_time']);
$end_time = strtotime($session['end_time']);
$duration_seconds = $end_time - $start_time;
$duration_formatted = sprintf("%02d:%02d:%02d", ($duration_seconds / 3600), ($duration_seconds % 3600 / 60), ($duration_seconds % 60));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Hasil Ujian - STM Gotham City</title>
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

        .progress-circle {
            width: 150px;
            height: 150px;
            position: relative;
        }

        .progress-circle svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }

        .progress-circle circle {
            fill: none;
            stroke-width: 10;
        }

        .progress-circle .bg {
            stroke: #4b5563;
        }

        .progress-circle .progress {
            stroke: #10b981;
            stroke-linecap: round;
            transition: stroke-dashoffset 0.5s ease;
        }

        .progress-circle span {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.5rem;
            font-weight: bold;
            color: #e2e8f0;
        }

        .result-item {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }

        .result-item.correct {
            border-color: #10b981;
        }

        .result-item.incorrect {
            border-color: #ef4444;
        }

        .fade {
            animation: fade 0.3s ease-in-out;
        }

        @keyframes fade {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
                <span class="text-gray-300">Hai, <?php echo htmlspecialchars($student['full_name']); ?></span>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container mx-auto p-6 pt-24">
        <div class="card p-6 fade">
            <h1 class="text-3xl font-bold mb-6">Hasil Ujian</h1>

            <!-- Summary -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div>
                    <h2 class="text-xl font-semibold mb-4">Ringkasan</h2>
                    <div class="space-y-2">
                        <p><span class="font-medium">Nama:</span> <?php echo htmlspecialchars($student['full_name']); ?></p>
                        <p><span class="font-medium">Waktu Mulai:</span> <?php echo date('d-m-Y H:i:s', strtotime($session['start_time'])); ?></p>
                        <p><span class="font-medium">Waktu Selesai:</span> <?php echo date('d-m-Y H:i:s', strtotime($session['end_time'])); ?></p>
                        <p><span class="font-medium">Durasi:</span> <?php echo $duration_formatted; ?></p>
                    </div>
                </div>
                <div class="flex items-center justify-center">
                    <div class="progress-circle">
                        <svg>
                            <circle class="bg" cx="75" cy="75" r="70"></circle>
                            <circle class="progress" cx="75" cy="75" r="70" stroke-dasharray="440" stroke-dashoffset="<?php echo 440 - ($score_percentage / 100 * 440); ?>"></circle>
                        </svg>
                        <span><?php echo round($score_percentage, 1); ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
                <div class="bg-gray-800 p-4 rounded-lg text-center">
                    <p class="text-lg font-semibold"><?php echo $total_questions; ?></p>
                    <p class="text-sm text-gray-400">Total Soal</p>
                </div>
                <div class="bg-gray-800 p-4 rounded-lg text-center">
                    <p class="text-lg font-semibold"><?php echo $correct_answers + $incorrect_answers; ?></p>
                    <p class="text-sm text-gray-400">Dijawab</p>
                </div>
                <div class="bg-gray-800 p-4 rounded-lg text-center">
                    <p class="text-lg font-semibold"><?php echo $correct_answers; ?></p>
                    <p class="text-sm text-gray-400">Benar</p>
                </div>
                <div class="bg-gray-800 p-4 rounded-lg text-center">
                    <p class="text-lg font-semibold"><?php echo $incorrect_answers; ?></p>
                    <p class="text-sm text-gray-400">Salah</p>
                </div>
            </div>

            <!-- Question Breakdown -->
            <h2 class="text-xl font-semibold mb-4">Detail Jawaban</h2>
            <div class="space-y-4">
                <?php foreach ($exam_results as $index => $result): ?>
                    <div class="result-item p-4 rounded-lg <?php echo $result['is_correct'] ? 'correct' : 'incorrect'; ?>">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold">Soal <?php echo $index + 1; ?>: <?php echo htmlspecialchars($result['question_text']); ?></p>
                            <span class="font-semibold <?php echo $result['is_correct'] ? 'text-green-400' : 'text-red-400'; ?>">
                                <?php echo $result['is_correct'] ? 'Benar' : 'Salah'; ?>
                            </span>
                        </div>
                        <div class="mt-2">
                            <p class="text-gray-400">
                                <span class="font-medium">Jawaban Anda:</span> 
                                <?php echo $result['selected_answer'] ? htmlspecialchars($result['option_' . strtolower($result['selected_answer'])]) : 'Tidak Dijawab'; ?>
                            </p>
                            <p class="text-gray-400">
                                <span class="font-medium">Jawaban Benar:</span> 
                                <?php echo htmlspecialchars($result['option_' . strtolower($result['correct_answer'])]); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Back to Dashboard -->
            <div class="flex justify-end mt-6">
                <a href="student_dashboard.php" class="btn btn-primary">Kembali ke Dashboard</a>
            </div>
        </div>
    </main>

    <script>
        // Display welcome message
        Swal.fire({
            title: 'Ujian Selesai',
            text: 'Berikut adalah hasil ujian Anda. Silakan tinjau detailnya.',
            icon: 'success',
            timer: 3000,
            showConfirmButton: false
        });
    </script>
</body>
</html>