<?php
session_start();

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if student is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student' || !isset($_SESSION['email'])) {
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
    header("Location: login.php");
    exit;
}

// Fetch student data
$email = $_SESSION['email'];
$sql = "SELECT id, full_name, nisn, status, admin_notes, created_at, exam_permission, major_id FROM registrations WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    error_log("Student not found for email: $email");
    $_SESSION['error'] = "Data siswa tidak ditemukan.";
    $conn->close();
    header("Location: login.php");
    exit;
}

// Fetch student documents (if any)
$student_documents = [];
$sql_docs = "SELECT document_name, document_type, file_path, uploaded_at FROM documents WHERE registration_id = ?";
$stmt_docs = $conn->prepare($sql_docs);
$stmt_docs->bind_param("i", $student['id']);
$stmt_docs->execute();
$result_docs = $stmt_docs->get_result();
while ($doc = $result_docs->fetch_assoc()) {
    $student_documents[] = $doc;
}
$stmt_docs->close();

// Fetch status history
$sql_history = "SELECT status, notes, changed_at FROM status_history WHERE registration_id = ? ORDER BY changed_at ASC";
$stmt_history = $conn->prepare($sql_history);
$stmt_history->bind_param("i", $student['id']);
$stmt_history->execute();
$result_history = $stmt_history->get_result();
$status_history = $result_history->fetch_all(MYSQLI_ASSOC);
$stmt_history->close();

$conn->close();

// Function to get timeline dot class
function getTimelineDotClass($status) {
    switch ($status) {
        case 'Terverifikasi':
            return 'bg-blue-900';
        case 'Menunggu Verifikasi':
            return 'bg-yellow-900';
        case 'Ditolak':
            return 'bg-red-900';
        case 'Diterima':
            return 'bg-green-900';
        default:
            return 'bg-gray-700';
    }
}

// Function to get status icon
function getStatusIcon($status) {
    switch ($status) {
        case 'Terverifikasi':
            return 'fa-check-circle';
        case 'Menunggu Verifikasi':
            return 'fa-clock';
        case 'Ditolak':
            return 'fa-times-circle';
        case 'Diterima':
            return 'fa-graduation-cap';
        default:
            return 'fa-question-circle';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Student Dashboard - STM Gotham City</title>
    <link rel="icon" href="https://cdn.pixabay.com/photo/2018/10/05/21/29/bat-3726896_1280.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .sidebar {
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed {
            transform: translateX(-100%);
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
        
        .btn {
            transition: all 0.3s ease;
            padding: 0.75rem 2rem;
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
        
        .logout-btn {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }
        
        .logout-btn:hover {
            background: linear-gradient(90deg, #dc2626, #ef4444);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.5);
        }
        
        .status-badge {
            padding: 0.35rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-verified {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .status-pending {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .status-accepted {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .status-rejected {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .glow-effect {
            position: relative;
        }
        
        .glow-effect::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: inherit;
            box-shadow: 0 0 25px rgba(59, 130, 246, 0.6);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .glow-effect:hover::after {
            opacity: 1;
        }
        
        .progress-container {
            width: 100%;
            height: 8px;
            background: rgba(71, 85, 105, 0.3);
            border-radius: 1rem;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
            border-radius: 1rem;
            transition: width 0.5s ease;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 30px;
            padding-bottom: 25px;
        }
        
        .timeline-item:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 24px;
            bottom: 0;
            width: 2px;
            background: rgba(71, 85, 105, 0.6);
        }
        
        .timeline-dot {
            position: absolute;
            left: 0;
            top: 4px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
        }
        
        .timeline-date {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-bottom: 2px;
        }
        
        .timeline-content {
            font-size: 0.875rem;
        }
        
        .slide-in {
            animation: slideIn 0.5s forwards;
        }
        
        .celebrate-card {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(59, 130, 246, 0.2));
            border: 1px solid rgba(16, 185, 129, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        .celebrate-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="rgba(255,255,255,0.1)"%3E%3Cpath d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.88-11.71L16 10l-1 1-1.88-1.71L12 10l-1.12-1.71L9 10l-1-1 1.88-1.71L12 6l1.12 1.29L15 6l1 1-1.12 1.29zM12 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/%3E%3C/svg%3E') repeat;
            opacity: 0.3;
            z-index: 0;
        }
        
        .celebrate-card > * {
            position: relative;
            z-index: 1;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 40;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.mobile-show {
                transform: translateX(0);
            }
            
            .content {
                margin-left: 0 !important;
            }
            
            .overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 30;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
            }
            
            .overlay.active {
                opacity: 1;
                pointer-events: auto;
            }
        }
    </style>
</head>

<body>
    <!-- Overlay for mobile menu -->
    <div id="overlay" class="overlay"></div>

    <!-- Navbar -->
    <nav id="navbar" class="fixed w-full top-0 z-50 transition-all duration-300 py-4">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <button id="sidebar-toggle" class="md:hidden text-white focus:outline-none mr-2">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <a href="../index.html" class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center shadow-lg">
                        <img src="https://cdn.pixabay.com/photo/2018/10/05/21/29/bat-3726896_1280.png" alt="Logo STM Gotham City" class="w-10 h-10 object-contain">
                    </div>
                    <div class="text-white font-bold text-xl">STM Gotham City</div>
                </a>
            </div>
            
            <div class="hidden md:flex items-center space-x-6">
                <div class="border-r border-gray-600 h-6 mx-2"></div>
                <a href="../index.html" class="text-gray-300 hover:text-white transition duration-300">
                    <i class="fas fa-home mr-2"></i> Home
                </a>
                <a href="logout.php" class="text-gray-300 hover:text-red-400 transition duration-300">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
            
            <div class="md:hidden flex items-center space-x-4">
                <button id="mobile-menu-button" class="text-white focus:outline-none">
                    <i class="fas fa-ellipsis-v text-xl"></i>
                </button>
            </div>
        </div>
        
        <div id="mobile-menu" class="md:hidden hidden bg-gray-900 shadow-lg">
            <div class="container mx-auto px-4 py-3 flex flex-col space-y-3">
                <a href="../index.html" class="text-gray-300 hover:text-white transition py-2 font-medium flex items-center">
                    <i class="fas fa-home mr-3 w-5 text-center"></i> Home
                </a>
                <a href="logout.php" class="text-gray-300 hover:text-red-400 transition py-2 font-medium flex items-center">
                    <i class="fas fa-sign-out-alt mr-3 w-5 text-center"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="flex min-h-screen pt-24">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar w-64 fixed top-20 bottom-0 p-6 hidden md:block">
            <div class="flex items-center space-x-3 mb-8">
                <div class="w-12 h-12 rounded-full bg-blue-600 flex items-center justify-center">
                    <span class="text-xl font-bold text-white">
                        <?php echo substr($student['full_name'] ?? 'S', 0, 1); ?>
                    </span>
                </div>
                <div>
                    <h3 class="font-bold text-white">
                        <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?>
                    </h3>
                    <p class="text-xs text-gray-400">Student</p>
                </div>
            </div>
            
            <div class="mb-8">
                <p class="text-xs uppercase text-gray-500 font-semibold mb-4 tracking-wider">Menu</p>
                <ul class="space-y-2">
                    <li>
                        <a href="#" class="flex items-center space-x-3 text-blue-400 bg-blue-900 bg-opacity-30 rounded-lg px-4 py-3 transition-all duration-300">
                            <i class="fas fa-tachometer-alt w-5"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="student_profile.php" class="flex items-center space-x-3 text-gray-300 hover:text-blue-400 hover:bg-blue-900 hover:bg-opacity-20 rounded-lg px-4 py-3 transition-all duration-300">
                            <i class="fas fa-user w-5"></i>
                            <span>Profile</span>
                        </a>
                    </li>
                    <li>
                        <a href="students_documents.php" class="flex items-center space-x-3 text-gray-300 hover:text-blue-400 hover:bg-blue-900 hover:bg-opacity-20 rounded-lg px-4 py-3 transition-all duration-300">
                            <i class="fas fa-book w-5"></i>
                            <span>Documents</span>
                        </a>
                    </li>
                    <li>
                        <a href="exam.php" class="flex items-center space-x-3 <?php echo $student['exam_permission'] == 1 ? 'text-gray-300 hover:text-blue-400 hover:bg-blue-900 hover:bg-opacity-20' : 'text-gray-600 cursor-not-allowed'; ?> rounded-lg px-4 py-3 transition-all duration-300" <?php echo $student['exam_permission'] == 1 ? '' : 'onclick="event.preventDefault(); Swal.fire({title: \'Akses Ditolak\', text: \'Anda belum diizinkan untuk mengikuti ujian.\', icon: \'warning\', confirmButtonText: \'OK\'});"'; ?>>
                            <i class="fas fa-graduation-cap w-5"></i>
                            <span>Exam</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <div>
                <p class="text-xs uppercase text-gray-500 font-semibold mb-4 tracking-wider">Help</p>
                <ul class="space-y-2">
                    <li>
                        <a href="support.php" class="flex items-center space-x-3 text-gray-300 hover:text-blue-400 hover:bg-blue-900 hover:bg-opacity-20 rounded-lg px-4 py-3 transition-all duration-300">
                            <i class="fas fa-question-circle w-5"></i>
                            <span>Support</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="absolute bottom-6 left-6 right-6">
                <a href="logout.php" class="logout-btn btn text-white w-full flex items-center justify-center">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Content -->
        <main class="content flex-1 p-6 md:ml-64">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-6 slide-in">
                    <div class="bg-red-900 bg-opacity-20 border border-red-600 border-opacity-20 rounded-lg p-4 text-red-100">
                        <i class="fas fa-exclamation-circle mr-2 text-red-400"></i>
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                    </div>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if ($student['status'] === 'Ditolak'): ?>
                <!-- Rejected Page -->
                <div class="max-w-4xl mx-auto">
                    <div class="card p-8 text-center slide-in">
                        <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-red-500 bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-times text-4xl text-red-500"></i>
                        </div>
                        <h2 class="text-3xl font-bold mb-4">Terima Kasih!</h2>
                        <p class="text-lg text-gray-400 mb-8 max-w-xl mx-auto">
                            Kami menghargai usaha Anda dalam mendaftar di STM Gotham City. 
                            Sayangnya, pendaftaran Anda tidak dapat diterima saat ini.
                        </p>
                        <?php
                        $latest_admin_notes = !empty($status_history) ? end($status_history)['notes'] : $student['admin_notes'];
                        if (!empty($latest_admin_notes)):
                        ?>
                            <div class="bg-red-900 bg-opacity-20 border border-red-600 border-opacity-20 rounded-lg p-4 text-red-100 mb-6">
                                <i class="fas fa-info-circle mr-2 text-red-400"></i>
                                Catatan Admin: <?php echo htmlspecialchars($latest_admin_notes); ?>
                            </div>
                        <?php endif; ?>
                        <p class="text-gray-400 mb-8">
                            Tetap semangat dan jangan menyerah untuk meraih impian Anda!
                        </p>
                        <div class="inline-block">
                            <a href="logout.php" class="logout-btn btn text-white inline-flex items-center glow-effect">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Dashboard for Menunggu Verifikasi/Terverifikasi/Diterima -->
                <div class="max-w-7xl mx-auto">
                    <!-- Congratulatory Message for Accepted Students -->
                    <?php if ($student['status'] === 'Diterima'): ?>
                        <div class="celebrate-card card p-6 mb-6 slide-in">
                            <div class="flex items-center space-x-4 mb-4">
                                <div class="w-12 h-12 rounded-full bg-green-500 bg-opacity-30 flex items-center justify-center">
                                    <i class="fas fa-graduation-cap text-2xl text-green-400"></i>
                                </div>
                                <h2 class="text-2xl font-bold text-green-100">Selamat, <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?>!</h2>
                            </div>
                            <p class="text-lg text-gray-200 mb-4">
                                Anda resmi diterima di STM Gotham City! Selamat bergabung dengan kami untuk mengejar masa depan yang cerah!
                            </p>
                            <div class="flex justify-center">
                                <a href="student_profile.php" class="btn bg-blue-700 hover:bg-blue-800 text-white">
                                    <i class="fas fa-user mr-2"></i> Lihat Profil Anda
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Welcome Section -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                        <div>
                            <h1 class="text-3xl font-bold mb-2 slide-in">Hi, <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?>!</h1>
                            <p class="text-gray-400 slide-in">Welcome to your student dashboard</p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <?php
                            $statusClass = '';
                            $statusIcon = '';
                            
                            switch($student['status']) {
                                case 'Menunggu Verifikasi':
                                    $statusClass = 'status-pending';
                                    $statusIcon = 'fa-clock';
                                    break;
                                case 'Terverifikasi':
                                    $statusClass = 'status-verified';
                                    $statusIcon = 'fa-check-circle';
                                    break;
                                case 'Diterima':
                                    $statusClass = 'status-accepted';
                                    $statusIcon = 'fa-graduation-cap';
                                    break;
                                default:
                                    $statusClass = 'status-pending';
                                    $statusIcon = 'fa-clock';
                            }
                            ?>
                            
                            <div class="<?php echo $statusClass; ?> status-badge slide-in">
                                <i class="fas <?php echo $statusIcon; ?>"></i>
                                <?php echo htmlspecialchars($student['status']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notifikasi jika dokumen belum diupload -->
                    <?php if (empty($student_documents) && $student['status'] !== 'Ditolak'): ?>
                        <div class="bg-yellow-900 bg-opacity-20 border border-yellow-600 border-opacity-20 rounded-lg p-4 text-yellow-100 mb-6 slide-in flex items-center justify-between">
                            <div>
                                <i class="fas fa-exclamation-triangle mr-2 text-yellow-400"></i>
                                Anda belum mengunggah dokumen persyaratan. Harap segera lengkapi dokumen Anda untuk melanjutkan proses pendaftaran.
                            </div>
                            <a href="students_documents.php" class="ml-4 btn bg-blue-700 hover:bg-blue-800 text-white">
                                <i class="fas fa-upload mr-2"></i> Upload Dokumen
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Registration Status -->
                    <div class="card p-6 mb-6 slide-in">
                        <div class="flex items-center space-x-4 mb-4">
                            <div class="w-10 h-10 rounded-full bg-yellow-500 bg-opacity-20 flex items-center justify-center">
                                <i class="fas fa-hourglass-half text-yellow-500"></i>
                            </div>
                            <h2 class="text-xl font-semibold">Status Pendaftaran</h2>
                        </div>
                        
                        <div class="mb-6">
                            <div class="flex justify-between mb-2">
                                <span class="text-sm font-medium text-gray-400">Proses Verifikasi</span>
                                <span class="text-sm font-medium text-blue-400">
                                    <?php echo $student['status'] === 'Diterima' ? '100%' : ($student['status'] === 'Terverifikasi' ? '75%' : '50%'); ?>
                                </span>
                            </div>
                            <div class="progress-container">
                                <div class="progress-bar" style="width: <?php echo $student['status'] === 'Diterima' ? '100%' : ($student['status'] === 'Terverifikasi' ? '75%' : '50%'); ?>"></div>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-3">Catatan Admin</h3>
                            <?php
                            $latest_notes = !empty($status_history) ? end($status_history)['notes'] : $student['admin_notes'];
                            ?>
                            <div class="bg-yellow-900 bg-opacity-20 border border-yellow-600 border-opacity-20 rounded-lg p-4 text-yellow-100">
                                <i class="fas fa-info-circle mr-2 text-yellow-400"></i>
                                <?php echo htmlspecialchars($latest_notes ?? 'Mohon tunggu proses verifikasi data Anda oleh tim admin.'); ?>
                            </div>
                        </div>
                        
                        <!-- Recent Activity Timeline -->
                        <div>
                            <h3 class="text-lg font-semibold mb-3">Riwayat Status</h3>
                            <div class="timeline">
                                <!-- Initial Registration -->
                                <div class="timeline-item">
                                    <div class="timeline-dot bg-blue-500"></div>
                                    <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($student['created_at'])); ?></div>
                                    <div class="timeline-content">Pendaftaran Dibuat</div>
                                </div>
                                <!-- Status History -->
                                <?php if (!empty($status_history)): ?>
                                    <?php foreach ($status_history as $history): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-dot <?php echo getTimelineDotClass($history['status']); ?>"></div>
                                            <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($history['changed_at'])); ?></div>
                                            <div class="timeline-content">
                                                Status: <?php echo htmlspecialchars($history['status']); ?>
                                                <?php if (!empty($history['notes'])): ?>
                                                    <br>Catatan: <?php echo htmlspecialchars($history['notes']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php elseif (!empty($student['admin_notes'])): ?>
                                    <!-- Fallback for existing data -->
                                    <div class="timeline-item">
                                        <div class="timeline-dot <?php echo getTimelineDotClass($student['status']); ?>"></div>
                                        <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($student['created_at'])); ?></div>
                                        <div class="timeline-content">
                                            Status: <?php echo htmlspecialchars($student['status']); ?>
                                            <br>Catatan: <?php echo htmlspecialchars($student['admin_notes']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Verified/Accepted Dashboard -->
                    <?php if ($student['status'] !== 'Menunggu Verifikasi'): ?>
                        <div class="card p-6 slide-in">
                            <div class="flex items-center space-x-4 mb-6">
                                <div class="w-10 h-10 rounded-full bg-green-500 bg-opacity-20 flex items-center justify-center">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <h2 class="text-xl font-semibold">
                                    <?php echo $student['status'] === 'Diterima' ? 'Selamat! Anda Diterima' : 'Pendaftaran Terverifikasi'; ?>
                                </h2>
                            </div>
                            <p class="text-gray-400 mb-6">
                                <?php echo $student['status'] === 'Diterima' ? 
                                    'Selamat datang di STM Gotham City! Pendaftaran Anda telah diterima.' : 
                                    'Verifikasi data Anda berhasil! Silakan menunggu jadwal ujian seleksi sebagai tahap selanjutnya untuk proses penerimaan.'; ?>
                            </p>
                            <!-- Exam Permission Notification -->
                            <?php if ($student['exam_permission'] == 1): ?>
                                <div class="bg-green-900 bg-opacity-20 border border-green-600 border-opacity-20 rounded-lg p-4 text-green-100 mb-6 slide-in">
                                    <i class="fas fa-check-circle mr-2 text-green-400"></i>
                                    Selamat! Admin telah mengizinkan Anda untuk mengikuti ujian.
                                </div>
                            <?php endif; ?>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <h3 class="text-lg font-semibold mb-3">Detail Pendaftaran</h3>
                                    <ul class="space-y-2 text-gray-400">
                                        <li><strong>Nama Lengkap:</strong> <?php echo htmlspecialchars($student['full_name'] ?? 'N/A'); ?></li>
                                        <li><strong>NISN:</strong> <?php echo htmlspecialchars($student['nisn'] ?? 'N/A'); ?></li>
                                        <li><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></li>
                                        <li><strong>Status:</strong> <?php echo htmlspecialchars($student['status']); ?></li>
                                    </ul>
                                </div>
                                <?php if (!empty($student_documents)): ?>
                                    <div>
                                        <h3 class="text-lg font-semibold mb-3">Dokumen Terkirim</h3>
                                        <ul class="space-y-2">
                                            <?php foreach ($student_documents as $doc): ?>
                                                <li class="flex items-center space-x-2">
                                                    <i class="fas fa-file-pdf text-red-400"></i>
                                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" class="text-blue-400 hover:underline" target="_blank">
                                                        <?php echo htmlspecialchars($doc['document_name']); ?>
                                                        <span class="ml-2 text-xs text-gray-400">(<?php echo htmlspecialchars($doc['document_type']); ?>)</span>
                                                        <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                                                    </a>
                                                    <span class="text-xs text-gray-400 ml-2">(<?php echo date('d/m/Y H:i', strtotime($doc['uploaded_at'])); ?>)</span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('navbar-fixed');
            } else {
                navbar.classList.remove('navbar-fixed');
            }
        });

        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });

        // Sidebar toggle for mobile
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-show');
            overlay.classList.toggle('active');
        });

        // Close sidebar when clicking overlay
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-show');
            overlay.classList.remove('active');
        });

        // Optional: Show SweetAlert2 popup for accepted students
        <?php if ($student['status'] === 'Diterima'): ?>
            Swal.fire({
                title: 'Selamat, <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?>!',
                text: 'Anda resmi diterima di STM Gotham City! Selamat bergabung!',
                icon: 'success',
                confirmButtonText: 'OK',
                customClass: {
                    popup: 'bg-gray-900 text-white border border-green-500',
                    title: 'text-green-400',
                    content: 'text-gray-200',
                    confirmButton: 'bg-blue-700 hover:bg-blue-800 text-white'
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>