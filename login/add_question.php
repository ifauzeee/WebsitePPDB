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

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "stm_gotham";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $_SESSION['error'] = "Koneksi database gagal.";
    error_log("Database connection failed: " . $conn->connect_error);
    header("Location: exam_management.php");
    exit;
}

// Function to sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Handle question addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Token CSRF tidak valid.";
        header("Location: add_question.php");
        exit;
    }

    $major_id = (int)($_POST['major_id'] ?? 0);
    $question_text = sanitize($_POST['question_text'] ?? '');
    $option_a = sanitize($_POST['option_a'] ?? '');
    $option_b = sanitize($_POST['option_b'] ?? '');
    $option_c = sanitize($_POST['option_c'] ?? '');
    $option_d = sanitize($_POST['option_d'] ?? '');
    $correct_answer = strtoupper(trim($_POST['correct_answer'] ?? ''));

    // Validate inputs
    $errors = [];
    if ($major_id <= 0) {
        $errors[] = "Jurusan tidak valid.";
    } else {
        // Check if major_id exists in majors table
        $stmt = $conn->prepare("SELECT COUNT(*) FROM majors WHERE id = ?");
        $stmt->bind_param("i", $major_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if ($count == 0) {
            $errors[] = "Jurusan tidak ditemukan.";
        }
    }
    if (empty($question_text)) {
        $errors[] = "Teks soal harus diisi.";
    }
    if (strlen($question_text) > 65535) {
        $errors[] = "Teks soal terlalu panjang.";
    }
    if (empty($option_a) || strlen($option_a) > 255) {
        $errors[] = "Pilihan A harus diisi dan kurang dari 255 karakter.";
    }
    if (empty($option_b) || strlen($option_b) > 255) {
        $errors[] = "Pilihan B harus diisi dan kurang dari 255 karakter.";
    }
    if (empty($option_c) || strlen($option_c) > 255) {
        $errors[] = "Pilihan C harus diisi dan kurang dari 255 karakter.";
    }
    if (empty($option_d) || strlen($option_d) > 255) {
        $errors[] = "Pilihan D harus diisi dan kurang dari 255 karakter.";
    }
    if (!in_array($correct_answer, ['A', 'B', 'C', 'D'])) {
        $errors[] = "Jawaban benar harus A, B, C, atau D.";
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode(" ", $errors);
        error_log("Validation errors: " . implode(", ", $errors));
    } else {
        $sql = "INSERT INTO questions (major_id, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssss", $major_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Soal berhasil ditambahkan.";
            error_log("Question added: id=" . $conn->insert_id . ", major_id=$major_id, question_text=$question_text");
        } else {
            $_SESSION['error'] = "Gagal menambahkan soal: " . $conn->error;
            error_log("Failed to add question: " . $conn->error);
        }
        $stmt->close();
    }
    header("Location: add_question.php");
    exit;
}

// Handle question deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Token CSRF tidak valid.";
        header("Location: add_question.php");
        exit;
    }

    $question_id = (int)($_POST['question_id'] ?? 0);
    if ($question_id <= 0) {
        $_SESSION['error'] = "ID soal tidak valid.";
    } else {
        $sql = "DELETE FROM questions WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $question_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['success'] = "Soal berhasil dihapus.";
                error_log("Question deleted: id=$question_id");
            } else {
                $_SESSION['error'] = "Soal tidak ditemukan.";
            }
        } else {
            $_SESSION['error'] = "Gagal menghapus soal: " . $conn->error;
            error_log("Failed to delete question: " . $conn->error);
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

// Fetch questions with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Get sort order (default to id DESC)
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['id', 'created_at']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) && in_array($_GET['order'], ['ASC', 'DESC']) ? $_GET['order'] : 'DESC';

// Get total number of questions for pagination
$sql = "SELECT COUNT(*) FROM questions";
$result = $conn->query($sql);
$totalQuestions = $result ? $result->fetch_row()[0] : 0;
$totalPages = ceil($totalQuestions / $perPage);

// Fetch questions
$sql = "SELECT q.id, q.question_text, q.major_id, m.name AS major_name 
        FROM questions q 
        LEFT JOIN majors m ON q.major_id = m.id 
        ORDER BY q.$sort $order LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
$questions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
error_log("Fetched questions: page=$page, sort=$sort, order=$order, count=" . count($questions));

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
    <title>Tambah Soal Ujian - STM Gotham City</title>
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
        
        .table-header {
            background: rgba(51, 65, 85, 0.95);
        }
        
        .table-row:hover {
            background: rgba(45, 55, 72, 0.95);
        }
        
        .major-btn, .action-btn, .toggle-form-btn {
            transition: all 0.3s ease;
        }
        
        .major-btn:hover, .action-btn:hover, .toggle-form-btn:hover {
            transform: translateY(-2px);
        }
        
        .slide-in {
            animation: slideIn 0.5s forwards;
        }
        
        #add-question-form {
            transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
            max-height: 0;
            opacity: 0;
            overflow: hidden;
        }
        
        #add-question-form.visible {
            max-height: 1000px;
            opacity: 1;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
    <script>
        function confirmDelete(questionId) {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: 'Apakah Anda yakin ingin menghapus soal ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#4b5563',
                confirmButtonText: 'Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-form-' + questionId).submit();
                }
            });
        }

        function toggleForm() {
            const form = document.getElementById('add-question-form');
            const button = document.getElementById('toggle-form-btn');
            const icon = button.querySelector('i');
            const text = button.querySelector('span');

            if (form.classList.contains('visible')) {
                form.classList.remove('visible');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-plus');
                text.textContent = 'Tambah Soal Baru';
            } else {
                form.classList.add('visible');
                icon.classList.remove('fa-plus');
                icon.classList.add('fa-chevron-up');
                text.textContent = 'Sembunyikan Form';
            }
        }

        // Client-side validation
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('#add-question-form form');
            form.addEventListener('submit', (e) => {
                const questionText = document.getElementById('question_text').value;
                const optionA = document.getElementById('option_a').value;
                const optionB = document.getElementById('option_b').value;
                const optionC = document.getElementById('option_c').value;
                const optionD = document.getElementById('option_d').value;
                const majorId = document.getElementById('major_id').value;

                if (!questionText) {
                    e.preventDefault();
                    Swal.fire('Error', 'Teks soal harus diisi.', 'error');
                    return;
                }
                if (!optionA || !optionB || !optionC || !optionD) {
                    e.preventDefault();
                    Swal.fire('Error', 'Semua pilihan jawaban harus diisi.', 'error');
                    return;
                }
                if (!majorId) {
                    e.preventDefault();
                    Swal.fire('Error', 'Jurusan harus dipilih.', 'error');
                    return;
                }
            });

            // Show form automatically if there's an error
            <?php if (isset($_SESSION['error'])): ?>
                document.getElementById('add-question-form').classList.add('visible');
                const button = document.getElementById('toggle-form-btn');
                const icon = button.querySelector('i');
                const text = button.querySelector('span');
                icon.classList.remove('fa-plus');
                icon.classList.add('fa-chevron-up');
                text.textContent = 'Sembunyikan Form';
            <?php endif; ?>
        });
    </script>
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
                                <span class="text-sm font-medium text-gray-400">Tambah Soal Ujian</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <h1 class="text-2xl md:text-3xl font-bold slide-in">Tambah Soal Ujian</h1>
            </div>
            <div class="flex space-x-2 mt-4 md:mt-0">
                <a href="exam_management.php" class="px-3 py-2 bg-gray-600 hover:bg-gray-700 rounded-lg transition flex items-center text-sm">
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
        
        <!-- Major Buttons -->
        <div class="card p-6 mb-6 slide-in">
            <h2 class="text-xl font-semibold mb-4">Lihat Soal Berdasarkan Jurusan</h2>
            <div class="flex flex-wrap gap-4">
                <?php if (empty($majors)): ?>
                    <p class="text-gray-400">Belum ada jurusan tersedia.</p>
                <?php else: ?>
                    <?php foreach ($majors as $major): ?>
                        <a href="question_detail_by_major.php?major_id=<?php echo $major['id']; ?>" class="major-btn px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition flex items-center">
                            <i class="fas fa-book mr-2"></i> <?php echo htmlspecialchars($major['name']); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add Question Form -->
        <div class="card p-6 mb-6 slide-in">
            <h2 class="text-xl font-semibold mb-4">Form Tambah Soal</h2>
            <button id="toggle-form-btn" onclick="toggleForm()" class="toggle-form-btn px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition flex items-center mb-4">
                <i class="fas fa-plus mr-2"></i>
                <span>Tambah Soal Baru</span>
            </button>
            <div id="add-question-form" class="space-y-4">
                <form action="" method="post" class="space-y-4">
                    <input type="hidden" name="add_question" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div>
                        <label for="major_id" class="block text-sm font-medium">Jurusan</label>
                        <select id="major_id" name="major_id" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Pilih Jurusan</option>
                            <?php foreach ($majors as $major): ?>
                                <option value="<?php echo $major['id']; ?>"><?php echo htmlspecialchars($major['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="question_text" class="block text-sm font-medium">Teks Soal</label>
                        <textarea id="question_text" name="question_text" rows="4" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500" required></textarea>
                    </div>
                    <div>
                        <label for="option_a" class="block text-sm font-medium">Pilihan A</label>
                        <input type="text" id="option_a" name="option_a" maxlength="255" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label for="option_b" class="block text-sm font-medium">Pilihan B</label>
                        <input type="text" id="option_b" name="option_b" maxlength="255" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label for="option_c" class="block text-sm font-medium">Pilihan C</label>
                        <input type="text" id="option_c" name="option_c" maxlength="255" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label for="option_d" class="block text-sm font-medium">Pilihan D</label>
                        <input type="text" id="option_d" name="option_d" maxlength="255" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label for="correct_answer" class="block text-sm font-medium">Jawaban Benar</label>
                        <select id="correct_answer" name="correct_answer" class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                    <div class="flex space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">Tambah Soal</button>
                        <a href="exam_management.php" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- List of Questions -->
        <div class="card p-6 slide-in">
            <h2 class="text-xl font-semibold mb-4">Daftar Soal</h2>
            <p class="text-gray-400 text-sm mb-4">Catatan: Nomor soal ditampilkan secara berurutan untuk setiap halaman. ID asli di database mungkin memiliki celah karena penghapusan data.</p>
            <!-- Sort Options -->
            <div class="mb-4 flex space-x-2">
                <label for="sort" class="text-sm font-medium">Urutkan berdasarkan:</label>
                <select id="sort" onchange="window.location.href='?page=1&sort=' + this.value + '&order=' + document.getElementById('order').value;" class="bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2">
                    <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>ID</option>
                    <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Tanggal Dibuat</option>
                </select>
                <select id="order" onchange="window.location.href='?page=1&sort=' + document.getElementById('sort').value + '&order=' + this.value;" class="bg-gray-800 border-gray-700 text-gray-300 rounded-lg p-2">
                    <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Terbaru</option>
                    <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Terlama</option>
                </select>
            </div>
            <?php if (empty($questions)): ?>
                <div class="bg-gray-800 p-8 rounded-lg text-center">
                    <i class="fas fa-question-circle text-5xl text-gray-500 mb-4"></i>
                    <p class="text-gray-400">Belum ada soal tersedia.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="table-header">
                            <tr>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">No.</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">Teks Soal</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">Jurusan</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $row_number = ($page - 1) * $perPage + 1; ?>
                            <?php foreach ($questions as $question): ?>
                                <tr class="table-row border-b border-gray-700">
                                    <td class="px-4 py-3 text-sm"><?php echo str_pad($row_number++, 5, '0', STR_PAD_LEFT); ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars(substr($question['question_text'], 0, 50)); ?>...</td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($question['major_name'] ?? 'N/A'); ?></td>
                                    <td class="px-4 py-3 text-sm flex space-x-2">
                                        <a href="question_detail.php?question_id=<?php echo $question['id']; ?>" class="action-btn px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm flex items-center">
                                            <i class="fas fa-eye mr-2"></i> Lihat
                                        </a>
                                        <a href="edit_question.php?question_id=<?php echo $question['id']; ?>" class="action-btn px-3 py-1 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-sm flex items-center">
                                            <i class="fas fa-edit mr-2"></i> Edit
                                        </a>
                                        <form id="delete-form-<?php echo $question['id']; ?>" action="" method="post" class="inline">
                                            <input type="hidden" name="delete_question" value="1">
                                            <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <button type="button" onclick="confirmDelete(<?php echo $question['id']; ?>)" class="action-btn px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm flex items-center">
                                                <i class="fas fa-trash mr-2"></i> Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                            </table>
                </div>
                <!-- Pagination Controls -->
                <div class="flex justify-between mt-4">
                    <a href="?page=<?php echo max(1, $page - 1); ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                        Sebelumnya
                    </a>
                    <span class="text-gray-300">Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?></span>
                    <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition <?php echo $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                        Selanjutnya
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>