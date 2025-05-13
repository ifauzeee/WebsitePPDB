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
    header("Location: admin_dashboard.php");
    exit;
}

// Handle exam permission update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permission'])) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $exam_permission = (int)($_POST['exam_permission'] ?? 0);
    
    // Validate inputs
    if ($student_id <= 0 || !in_array($exam_permission, [0, 1])) {
        $_SESSION['error'] = "Data tidak valid.";
    } else {
        // Update exam permission
        $sql = "UPDATE registrations SET exam_permission = ? WHERE id = ? AND status = 'Terverifikasi'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $exam_permission, $student_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['success'] = "Izin ujian berhasil diperbarui.";
            } else {
                $_SESSION['error'] = "Siswa tidak ditemukan atau bukan status Terverifikasi.";
            }
        } else {
            $_SESSION['error'] = "Gagal memperbarui izin ujian.";
        }
        $stmt->close();
    }
    header("Location: exam_management.php");
    exit;
}

// Fetch verified students with their exam scores
$sql = "SELECT r.id, r.full_name, r.email, r.program, r.exam_permission, es.score 
        FROM registrations r 
        LEFT JOIN exam_sessions es ON r.id = es.registration_id AND es.status = 'Completed' 
        WHERE r.status = 'Terverifikasi' 
        ORDER BY r.full_name ASC";
$result = $conn->query($sql);
$students = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

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
    <title>Manajemen Ujian - STM Gotham City</title>
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
        
        .permission-btn {
            transition: all 0.3s ease;
        }
        
        .permission-btn:hover {
            transform: translateY(-2px);
        }
        
        .disabled-btn {
            background: rgba(71, 85, 105, 0.5);
            color: #94a3b8;
            cursor: not-allowed;
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
                                <span class="text-sm font-medium text-gray-400">Manajemen Ujian</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <h1 class="text-2xl md:text-3xl font-bold slide-in">Manajemen Ujian</h1>
            </div>
            <div class="flex space-x-2 mt-4 md:mt-0">
                <a href="admin_dashboard.php" class="px-3 py-2 bg-gray-600 hover:bg-gray-700 rounded-lg transition flex items-center text-sm">
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
        
        <!-- Button to Add Question -->
        <div class="card p-6 mb-6 slide-in">
            <h2 class="text-xl font-semibold mb-4">Tambah Soal Ujian</h2>
            <a href="add_question.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition inline-flex items-center">
                <i class="fas fa-plus-circle mr-2"></i> Tambah Soal Baru
            </a>
        </div>
        
        <!-- Table of verified students -->
        <div class="card p-6 slide-in">
            <h2 class="text-xl font-semibold mb-4">Daftar Siswa Terverifikasi</h2>
            <?php if (empty($students)): ?>
                <div class="bg-gray-800 p-8 rounded-lg text-center">
                    <i class="fas fa-users text-5xl text-gray-500 mb-4"></i>
                    <p class="text-gray-400">Belum ada siswa dengan status Terverifikasi.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="table-header">
                            <tr>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">ID</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">Nama</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">Email</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">Program</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">Izin Ujian</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">Hasil</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr class="table-row border-b border-gray-700">
                                    <td class="px-4 py-3 text-sm"><?php echo str_pad($student['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($student['program'] ?? 'N/A'); ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <?php if ($student['exam_permission']): ?>
                                            <span class="inline-flex items-center px-2 py-1 bg-green-900 text-green-300 rounded-full text-xs">
                                                <i class="fas fa-check-circle mr-1"></i> Diizinkan
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 bg-red-900 text-red-300 rounded-full text-xs">
                                                <i class="fas fa-times-circle mr-1"></i> Tidak Diizinkan
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <?php if (!is_null($student['score'])): ?>
                                            <span class="inline-flex items-center px-2 py-1 bg-blue-900 text-blue-300 rounded-full text-xs">
                                                <i class="fas fa-check-circle mr-1"></i> <?php echo htmlspecialchars($student['score']); ?>/100
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 bg-gray-700 text-gray-300 rounded-full text-xs">
                                                <i class="fas fa-minus-circle mr-1"></i> Belum Ujian
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <?php if (!is_null($student['score'])): ?>
                                            <span class="inline-flex items-center px-3 py-1 bg-gray-700 text-gray-400 rounded-lg text-sm disabled-btn">
                                                <i class="fas fa-check mr-2"></i> Siswa Telah Ujian
                                            </span>
                                        <?php else: ?>
                                            <form action="" method="post" class="inline">
                                                <input type="hidden" name="update_permission" value="1">
                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                <input type="hidden" name="exam_permission" value="<?php echo $student['exam_permission'] ? 0 : 1; ?>">
                                                <button type="submit" class="permission-btn px-3 py-1 <?php echo $student['exam_permission'] ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'; ?> text-white rounded-lg text-sm flex items-center">
                                                    <i class="fas <?php echo $student['exam_permission'] ? 'fa-ban' : 'fa-check'; ?> mr-2"></i>
                                                    <?php echo $student['exam_permission'] ? 'Cabut Izin' : 'Izinkan Ujian'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
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