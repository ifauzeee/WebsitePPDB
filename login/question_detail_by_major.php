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

// Handle question deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
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
            } else {
                $_SESSION['error'] = "Soal tidak ditemukan.";
            }
        } else {
            $_SESSION['error'] = "Gagal menghapus soal: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: question_detail_by_major.php?major_id=" . (int)($_GET['major_id'] ?? 0));
    exit;
}

// Fetch major and questions
$major_id = (int)($_GET['major_id'] ?? 0);
if ($major_id <= 0) {
    $_SESSION['error'] = "ID jurusan tidak valid.";
    header("Location: add_question.php");
    exit;
}

// Get major name
$sql = "SELECT name FROM majors WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $major_id);
$stmt->execute();
$result = $stmt->get_result();
$major = $result->fetch_assoc();
$stmt->close();

if (!$major) {
    $_SESSION['error'] = "Jurusan tidak ditemukan.";
    header("Location: add_question.php");
    exit;
}

// Fetch questions for the major
$sql = "SELECT q.id, q.question_text 
        FROM questions q 
        WHERE q.major_id = ? 
        ORDER BY q.id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $major_id);
$stmt->execute();
$result = $stmt->get_result();
$questions = $result->fetch_all(MYSQLI_ASSOC);
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
    <title>Soal Ujian - <?php echo htmlspecialchars($major['name']); ?> - STM Gotham City</title>
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
        
        .action-btn {
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .slide-in {
            animation: slideIn 0.5s forwards;
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
                                <a href="add_question.php" class="text-sm font-medium text-blue-400 hover:text-blue-300">Tambah Soal Ujian</a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-500 mx-2 text-sm"></i>
                                <span class="text-sm font-medium text-gray-400">Soal Ujian - <?php echo htmlspecialchars($major['name']); ?></span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <h1 class="text-2xl md:text-3xl font-bold slide-in">Soal Ujian - <?php echo htmlspecialchars($major['name']); ?></h1>
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
        
        <!-- List of Questions -->
        <div class="card p-6 slide-in">
            <h2 class="text-xl font-semibold mb-4">Daftar Soal untuk <?php echo htmlspecialchars($major['name']); ?></h2>
            <?php if (empty($questions)): ?>
                <div class="bg-gray-800 p-8 rounded-lg text-center">
                    <i class="fas fa-question-circle text-5xl text-gray-500 mb-4"></i>
                    <p class="text-gray-400">Belum ada soal untuk jurusan ini.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="table-header">
                            <tr>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">ID</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">Teks Soal</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($questions as $question): ?>
                                <tr class="table-row border-b border-gray-700">
                                    <td class="px-4 py-3 text-sm"><?php echo str_pad($question['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars(substr($question['question_text'], 0, 50)); ?>...</td>
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
            <?php endif; ?>
        </div>
    </div>
</body>
</html>