<?php
session_start();

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if admin is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
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
    $_SESSION['error'] = "Koneksi database gagal.";
    header("Location: add_question.php");
    exit;
}

// Fetch question details
$question_id = (int)($_GET['question_id'] ?? 0);
if ($question_id <= 0) {
    $_SESSION['error'] = "ID soal tidak valid.";
    header("Location: add_question.php");
    exit;
}

$sql = "SELECT q.id, q.major_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer 
        FROM questions q 
        WHERE q.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $question_id);
$stmt->execute();
$result = $stmt->get_result();
$question = $result->fetch_assoc();
$stmt->close();

if (!$question) {
    $_SESSION['error'] = "Soal tidak ditemukan.";
    header("Location: add_question.php");
    exit;
}

// Handle question update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_question'])) {
    $major_id = (int)($_POST['major_id'] ?? 0);
    $question_text = trim($_POST['question_text'] ?? '');
    $option_a = trim($_POST['option_a'] ?? '');
    $option_b = trim($_POST['option_b'] ?? '');
    $option_c = trim($_POST['option_c'] ?? '');
    $option_d = trim($_POST['option_d'] ?? '');
    $correct_answer = strtoupper(trim($_POST['correct_answer'] ?? ''));

    // Validate inputs
    $errors = [];
    if ($major_id <= 0) {
        $errors[] = "Jurusan tidak valid.";
    }
    if (empty($question_text)) {
        $errors[] = "Teks soal harus diisi.";
    }
    if (empty($option_a)) {
        $errors[] = "Pilihan A harus diisi.";
    }
    if (empty($option_b)) {
        $errors[] = "Pilihan B harus diisi.";
    }
    if (empty($option_c)) {
        $errors[] = "Pilihan C harus diisi.";
    }
    if (empty($option_d)) {
        $errors[] = "Pilihan D harus diisi.";
    }
    if (!in_array($correct_answer, ['A', 'B', 'C', 'D'])) {
        $errors[] = "Jawaban benar harus A, B, C, atau D.";
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode(" ", $errors);
    } else {
        $sql = "UPDATE questions SET major_id = ?, question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssi", $major_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $question_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['success'] = "Soal berhasil diperbarui.";
            } else {
                $_SESSION['error'] = "Tidak ada perubahan pada soal.";
            }
        } else {
            $_SESSION['error'] = "Gagal memperbarui soal: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: add_question.php");
    exit;
}

// Fetch majors
$sql = "SELECT id, name FROM majors ORDER BY name ASC";
$result = $conn->query($sql);
$majors = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

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
    <title>Edit Soal Ujian - STM Gotham City</title>
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
        
        .card {
            background: rgba(30, 41, 59, 0.8);
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        
        .slide-in {
            animation: slideIn 0.5s forwards;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>

<body>
    <div class="container mx-auto p-4 md:p-6">
        <!-- Header with breadcrumbs -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <div>
                <nav class="flex mb-4" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="admin_dashboard.php" class="inline-flex items-center text-sm font-medium text-blue-400 hover:text-blue-300">
                                <i class="fas fa-home mr-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-500 mx-2 text-sm"></i>
                                <a href="exam_management.php" class="text-sm font-medium text-blue-400 hover:text-blue-300">Manajemen Ujian</a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-500 mx-2 text-sm"></i>
                                <a href="add_question.php" class="text-sm font-medium text-blue-400 hover:text-blue-300">Tambah Soal Ujian</a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-500 mx-2 text-sm"></i>
                                <span class="text-sm font-medium text-gray-400">Edit Soal Ujian</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <h1 class="text-2xl md:text-3xl font-bold slide-in">Edit Soal Ujian</h1>
            </div>
            <div class="flex space-x-2 mt-4 md:mt-0">
                <a href="add_question.php" class="px-3 py-2 bg-gray-600 hover:bg-gray-700 rounded-lg transition flex items-center text-sm">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali
                </a>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-900 text-green-300 p-4 rounded-lg mb-6 flex items-center justify-between slide-in">
                <div><i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['success']); ?></div>
                <button onclick="this.parentElement.style.display='none'" class="text-green-300 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-900 text-red-300 p-4 rounded-lg mb-6 flex items-center justify-between slide-in">
                <div><i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['error']); ?></div>
                <button onclick="this.parentElement.style.display='none'" class="text-red-300 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Edit Question Form -->
        <div class="card p-6 slide-in">
            <h2 class="text-xl font-semibold mb-4">Form Edit Soal #<?php echo str_pad($question['id'], 5, '0', STR_PAD_LEFT); ?></h2>
            <form action="" method="post" class="space-y-4">
                <input type="hidden" name="edit_question" value="1">
                <div>
                    <label for="major_id" class="block text-sm font-medium">Jurusan</label>
                    <select id="major_id" name="major_id" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ($majors as $major): ?>
                            <option value="<?php echo $major['id']; ?>" <?php echo $major['id'] == $question['major_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($major['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="question_text" class="block text-sm font-medium">Teks Soal</label>
                    <textarea id="question_text" name="question_text" rows="4" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                </div>
                <div>
                    <label for="option_a" class="block text-sm font-medium">Pilihan A</label>
                    <input type="text" id="option_a" name="option_a" value="<?php echo htmlspecialchars($question['option_a']); ?>" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                <div>
                    <label for="option_b" class="block text-sm font-medium">Pilihan B</label>
                    <input type="text" id="option_b" name="option_b" value="<?php echo htmlspecialchars($question['option_b']); ?>" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                <div>
                    <label for="option_c" class="block text-sm font-medium">Pilihan C</label>
                    <input type="text" id="option_c" name="option_c" value="<?php echo htmlspecialchars($question['option_c']); ?>" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                <div>
                    <label for="option_d" class="block text-sm font-medium">Pilihan D</label>
                    <input type="text" id="option_d" name="option_d" value="<?php echo htmlspecialchars($question['option_d']); ?>" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                <div>
                    <label for="correct_answer" class="block text-sm font-medium">Jawaban Benar</label>
                    <select id="correct_answer" name="correct_answer" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="A" <?php echo $question['correct_answer'] == 'A' ? 'selected' : ''; ?>>A</option>
                        <option value="B" <?php echo $question['correct_answer'] == 'B' ? 'selected' : ''; ?>>B</option>
                        <option value="C" <?php echo $question['correct_answer'] == 'C' ? 'selected' : ''; ?>>C</option>
                        <option value="D" <?php echo $question['correct_answer'] == 'D' ? 'selected' : ''; ?>>D</option>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">Simpan Perubahan</button>
                    <a href="add_question.php" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>