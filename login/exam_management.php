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

// Initialize variables for search, filter, sort, and pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$permission_filter = isset($_GET['permission']) ? $_GET['permission'] : 'all';
$exam_status_filter = isset($_GET['exam_status']) ? $_GET['exam_status'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;

// Build the WHERE clause
$where_clauses = ["r.status = 'Terverifikasi'"];
$params = [];
$types = '';

if ($search) {
    $where_clauses[] = "(r.full_name LIKE ? OR r.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if ($permission_filter !== 'all') {
    $where_clauses[] = "r.exam_permission = ?";
    $params[] = $permission_filter === 'allowed' ? 1 : 0;
    $types .= 'i';
}

if ($exam_status_filter !== 'all') {
    if ($exam_status_filter === 'tested') {
        $where_clauses[] = "es.score IS NOT NULL";
    } else {
        $where_clauses[] = "es.score IS NULL";
    }
}

// Build the ORDER BY clause
$order_by = 'r.full_name ASC';
switch ($sort) {
    case 'name_desc':
        $order_by = 'r.full_name DESC';
        break;
    case 'id_asc':
        $order_by = 'r.id ASC';
        break;
    case 'id_desc':
        $order_by = 'r.id DESC';
        break;
    case 'score_desc':
        $order_by = 'es.score DESC';
        break;
    case 'score_asc':
        $order_by = 'es.score ASC';
        break;
}

// Count total records for pagination
$count_sql = "SELECT COUNT(DISTINCT r.id) as total 
              FROM registrations r 
              LEFT JOIN exam_sessions es ON r.id = es.registration_id AND es.status = 'Completed'";
if ($where_clauses) {
    $count_sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $per_page);
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

// Fetch verified students with their exam scores
$sql = "SELECT r.id, r.full_name, r.email, r.program, r.exam_permission, es.score 
        FROM registrations r 
        LEFT JOIN exam_sessions es ON r.id = es.registration_id AND es.status = 'Completed'";
if ($where_clauses) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY $order_by LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch stats for cards
$total_students_sql = "SELECT COUNT(*) as count FROM registrations";
$total_verified_sql = "SELECT COUNT(*) as count FROM registrations WHERE status = 'Terverifikasi'";
$total_tested_sql = "SELECT COUNT(DISTINCT r.id) as count 
                     FROM registrations r 
                     JOIN exam_sessions es ON r.id = es.registration_id 
                     WHERE es.status = 'Completed'";
$avg_score_sql = "SELECT AVG(es.score) as avg_score 
                  FROM exam_sessions es 
                  WHERE es.status = 'Completed'";

$total_students = $conn->query($total_students_sql)->fetch_assoc()['count'];
$total_verified = $conn->query($total_verified_sql)->fetch_assoc()['count'];
$total_tested = $conn->query($total_tested_sql)->fetch_assoc()['count'];
$avg_score_result = $conn->query($avg_score_sql)->fetch_assoc()['avg_score'];
$avg_score = $avg_score_result ? number_format($avg_score_result, 1) : 0;

// Fetch data for chart
$chart_sql = "SELECT score, COUNT(*) as count 
              FROM exam_sessions 
              WHERE status = 'Completed' 
              GROUP BY score 
              ORDER BY score";
$chart_result = $conn->query($chart_sql);
$chart_data = [];
while ($row = $chart_result->fetch_assoc()) {
    $chart_data[(int)$row['score']] = $row['count'];
}

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            backdrop-filter: blur(10px);
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
        
        .gradient-button {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            transition: all 0.3s ease;
        }
        
        .gradient-button:hover {
            background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
        }
        
        .search-input {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
        }
        
        .search-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
        }
        
        .stats-card {
            border-left: 4px solid transparent;
        }
        
        .stats-card.blue {
            border-left-color: #3b82f6;
        }
        
        .stats-card.purple {
            border-left-color: #8b5cf6;
        }
        
        .stats-card.green {
            border-left-color: #10b981;
        }
        
        .stats-card.red {
            border-left-color: #ef4444;
        }
        
        .pagination-btn {
            background: rgba(51, 65, 85, 0.8);
            transition: all 0.2s ease;
        }
        
        .pagination-btn:hover:not(.pagination-active) {
            background: rgba(71, 85, 105, 0.9);
        }
        
        .pagination-active {
            background: #3b82f6;
        }
        
        .glow {
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .glow::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(
                circle at center,
                rgba(59, 130, 246, 0.1) 0%,
                transparent 70%
            );
            opacity: 0;
            z-index: -1;
            animation: glowPulse 3s infinite;
        }
        
        @keyframes glowPulse {
            0% { opacity: 0; }
            50% { opacity: 1; }
            100% { opacity: 0; }
        }
        
        .shimmer {
            position: relative;
            overflow: hidden;
        }
        
        .shimmer::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(
                to right,
                transparent 0%,
                rgba(255, 255, 255, 0.05) 50%,
                transparent 100%
            );
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 150%; }
        }
        
        .table-row:nth-child(odd) {
            background: rgba(30, 41, 59, 0.5);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        
        .status-badge i {
            margin-right: 0.25rem;
        }
        
        .tooltip {
            position: relative;
        }
        
        .tooltip .tooltip-text {
            visibility: hidden;
            position: absolute;
            z-index: 100;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(15, 23, 42, 0.95);
            color: #e2e8f0;
            text-align: center;
            border-radius: 6px;
            padding: 0.5rem 1rem;
            width: max-content;
            max-width: 250px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        .filter-dropdown {
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Styles for vertical Quick Actions */
        .quick-actions-container {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;  /* Mengisi tinggi penuh */
            gap: 1rem;
            padding: 0.5rem 0;
        }

        .card.glow {
            height: 100%;  /* Menyesuaikan tinggi dengan chart card */
            display: flex;
            flex-direction: column;
        }

        .quick-action-btn {
            padding: 1.5rem;  /* Padding yang lebih besar */
            font-size: 1.1rem;
            line-height: 1.5;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            text-align: left;
            border-radius: 0.75rem;
            width: 100%;
            font-weight: 500;
            transition: all 0.3s ease;
            flex: 1;  /* Membuat tombol mengisi ruang yang tersedia */
        }

        .quick-action-btn i {
            font-size: 1.5rem;  /* Ikon yang lebih besar */
            margin-right: 1rem;
            width: 32px;
        }

        /* Tambahkan style untuk chart container */
        .chart-container {
            max-height: 300px; /* Ubah dari 200px menjadi 300px */
            min-height: 300px; /* Tambahkan min-height */
            overflow: hidden;
            padding: 1rem;    /* Tambahkan padding */
            margin: 0.5rem 0; /* Tambahkan margin */
        }

        /* Tambahkan style untuk card yang berisi chart */
        .card.chart-card {
            min-height: 400px; /* Minimal tinggi card */
        }
    </style>
</head>

<body>
    <div class="container mx-auto px-4 py-8">
        <!-- Header with breadcrumbs -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
            <div>
                <nav class="flex mb-4" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="admin_dashboard.php" class="inline-flex items-center text-sm font-medium text-blue-400 hover:text-blue-300 transition">
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
                <h1 class="text-3xl md:text-4xl font-bold slide-in flex items-center">
                    <i class="fas fa-clipboard-check text-blue-400 mr-3"></i>
                    Manajemen Ujian
                </h1>
                <p class="text-gray-400 mt-2 slide-in">Kelola ujian dan izin siswa STM Gotham City</p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <a href="admin_dashboard.php" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition flex items-center text-sm">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali
                </a>
                <button id="refreshBtn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition flex items-center text-sm">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh
                </button>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <div id="notificationArea" class="mb-6">
            <?php if (isset($_SESSION['success'])): ?>
                <div id="successMsg" class="bg-green-900/60 text-green-300 p-4 rounded-lg mb-4 flex items-center justify-between slide-in backdrop-blur-sm border border-green-600/30">
                    <div class="flex items-center">
                        <div class="bg-green-600/30 p-2 rounded-full mr-3">
                            <i class="fas fa-check text-green-300"></i>
                        </div>
                        <div>
                            <h4 class="font-medium">Berhasil!</h4>
                            <p id="successText" class="text-sm opacity-90"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
                        </div>
                    </div>
                    <button onclick="document.getElementById('successMsg').style.display='none'" class="text-green-300 hover:text-white p-1">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div id="errorMsg" class="bg-red-900/60 text-red-300 p-4 rounded-lg mb-4 flex items-center justify-between slide-in backdrop-blur-sm border border-red-600/30">
                    <div class="flex items-center">
                        <div class="bg-red-600/30 p-2 rounded-full mr-3">
                            <i class="fas fa-exclamation-triangle text-red-300"></i>
                        </div>
                        <div>
                            <h4 class="font-medium">Error!</h4>
                            <p id="errorText" class="text-sm opacity-90"><?php echo htmlspecialchars($_SESSION['error']); ?></p>
                        </div>
                    </div>
                    <button onclick="document.getElementById('errorMsg').style.display='none'" class="text-red-300 hover:text-white p-1">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        </div>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="card p-6 stats-card blue shimmer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Total Siswa Terdaftar</p>
                        <h3 class="text-2xl font-bold"><?php echo $total_students; ?></h3>
                    </div>
                    <div class="bg-blue-500/20 p-3 rounded-full">
                        <i class="fas fa-users text-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="card p-6 stats-card purple shimmer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Siswa Terverifikasi</p>
                        <h3 class="text-2xl font-bold"><?php echo $total_verified; ?></h3>
                    </div>
                    <div class="bg-purple-500/20 p-3 rounded-full">
                        <i class="fas fa-user-check text-purple-400 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="card p-6 stats-card green shimmer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Siswa Telah Ujian</p>
                        <h3 class="text-2xl font-bold"><?php echo $total_tested; ?></h3>
                    </div>
                    <div class="bg-green-500/20 p-3 rounded-full">
                        <i class="fas fa-clipboard-check text-green-400 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="card p-6 stats-card red shimmer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Rata-rata Nilai</p>
                        <h3 class="text-2xl font-bold"><?php echo $avg_score; ?></h3>
                    </div>
                    <div class="bg-red-500/20 p-3 rounded-full">
                        <i class="fas fa-chart-bar text-red-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions & Chart -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Quick Actions -->
            <div class="card p-6 glow lg:col-span-1">
                <h2 class="text-xl font-semibold mb-6 flex items-center">
                    <i class="fas fa-bolt text-yellow-400 mr-2"></i> 
                    Aksi Cepat
                </h2>
                <div class="quick-actions-container">
                    <a href="add_question.php" class="gradient-button quick-action-btn text-white transition">
                        <i class="fas fa-plus-circle"></i> Tambah Soal
                    </a>
                    <a href="bulk_import.php" class="bg-purple-600 hover:bg-purple-700 quick-action-btn text-white transition">
                        <i class="fas fa-file-import"></i> Import Soal
                    </a>
                    <a href="export_results.php" class="bg-amber-600 hover:bg-amber-700 quick-action-btn text-white transition">
                        <i class="fas fa-file-export"></i> Export Hasil
                    </a>
                </div>
            </div>
            
            <!-- Chart -->
            <div class="card p-6 lg:col-span-2 chart-card">
                <h2 class="text-xl font-semibold mb-6 flex items-center">
                    <i class="fas fa-chart-line text-blue-400 mr-2"></i> 
                    Statistik Nilai Ujian
                </h2>
                <div class="bg-gray-800/50 p-4 rounded-lg border border-gray-700/50 chart-container">
                    <canvas id="examScoresChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Search & Filter -->
        <div class="flex flex-col md:flex-row items-center justify-between mb-4 gap-4">
            <div class="w-full md:w-1/3">
                <div class="relative">
                    <input type="text" id="searchInput" placeholder="Cari nama atau email siswa..." value="<?php echo htmlspecialchars($search); ?>" class="search-input w-full px-4 py-2 pl-10 rounded-lg text-white focus:outline-none transition">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>
            
            <div class="flex flex-wrap gap-3">
                <div class="relative">
                    <button id="filterBtn" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition flex items-center text-sm">
                        <i class="fas fa-filter mr-2"></i> Filter
                        <i class="fas fa-chevron-down ml-2 text-xs"></i>
                    </button>
                    <div id="filterDropdown" class="absolute right-0 mt-2 w-48 filter-dropdown rounded-lg shadow-lg z-10 hidden">
                        <form id="filterForm" action="" method="get">
                            <div class="p-3">
                                <h4 class="font-medium mb-2 border-b border-gray-700 pb-1">Status Izin</h4>
                                <div class="space-y-2">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="permission" value="all" class="form-radio bg-gray-700 border-gray-600 text-blue-500" <?php echo $permission_filter === 'all' ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm">Semua</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="permission" value="allowed" class="form-radio bg-gray-700 border-gray-600 text-blue-500" <?php echo $permission_filter === 'allowed' ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm">Diizinkan</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="permission" value="not_allowed" class="form-radio bg-gray-700 border-gray-600 text-blue-500" <?php echo $permission_filter === 'not_allowed' ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm">Tidak Diizinkan</span>
                                    </label>
                                </div>
                                
                                <h4 class="font-medium mb-2 mt-3 border-b border-gray-700 pb-1">Status Ujian</h4>
                                <div class="space-y-2">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="exam_status" value="all" class="form-radio bg-gray-700 border-gray-600 text-blue-500" <?php echo $exam_status_filter === 'all' ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm">Semua</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="exam_status" value="tested" class="form-radio bg-gray-700 border-gray-600 text-blue-500" <?php echo $exam_status_filter === 'tested' ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm">Sudah Ujian</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="exam_status" value="not_tested" class="form-radio bg-gray-700 border-gray-600 text-blue-500" <?php echo $exam_status_filter === 'not_tested' ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm">Belum Ujian</span>
                                    </label>
                                </div>
                                
                                <div class="mt-3 flex justify-end">
                                    <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 rounded text-xs">Terapkan</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="relative">
                    <button id="sortBtn" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition flex items-center text-sm">
                        <i class="fas fa-sort mr-2"></i> Urutkan
                        <i class="fas fa-chevron-down ml-2 text-xs"></i>
                    </button>
                    <div id="sortDropdown" class="absolute right-0 mt-2 w-48 filter-dropdown rounded-lg shadow-lg z-10 hidden">
                        <div class="p-3 space-y-2">
                            <a href="?sort=name_asc<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&permission=<?php echo $permission_filter; ?>&exam_status=<?php echo $exam_status_filter; ?>" class="w-full text-left px-3 py-2 hover:bg-gray-700 rounded text-sm flex items-center">
                                <i class="fas fa-sort-alpha-down mr-2"></i> Nama (A-Z)
                            </a>
                            <a href="?sort=name_desc<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&permission=<?php echo $permission_filter; ?>&exam_status=<?php echo $exam_status_filter; ?>" class="w-full text-left px-3 py-2 hover:bg-gray-700 rounded text-sm flex items-center">
                                <i class="fas fa-sort-alpha-up mr-2"></i> Nama (Z-A)
                            </a>
                            <a href="?sort=id_asc<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&permission=<?php echo $permission_filter; ?>&exam_status=<?php echo $exam_status_filter; ?>" class="w-full text-left px-3 py-2 hover:bg-gray-700 rounded text-sm flex items-center">
                                <i class="fas fa-sort-numeric-down mr-2"></i> ID (Terkecil)
                            </a>
                            <a href="?sort=id_desc<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&permission=<?php echo $permission_filter; ?>&exam_status=<?php echo $exam_status_filter; ?>" class="w-full text-left px-3 py-2 hover:bg-gray-700 rounded text-sm flex items-center">
                                <i class="fas fa-sort-numeric-up mr-2"></i> ID (Terbesar)
                            </a>
                            <a href="?sort=score_desc<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&permission=<?php echo $permission_filter; ?>&exam_status=<?php echo $exam_status_filter; ?>" class="w-full text-left px-3 py-2 hover:bg-gray-700 rounded text-sm flex items-center">
                                <i class="fas fa-sort-numeric-down mr-2"></i> Nilai (Tertinggi)
                            </a>
                            <a href="?sort=score_asc<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&permission=<?php echo $permission_filter; ?>&exam_status=<?php echo $exam_status_filter; ?>" class="w-full text-left px-3 py-2 hover:bg-gray-700 rounded text-sm flex items-center">
                                <i class="fas fa-sort-numeric-up mr-2"></i> Nilai (Terendah)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Table of verified students -->
        <div class="card p-6 slide-in">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold flex items-center">
                    <i class="fas fa-user-graduate text-blue-400 mr-2"></i> 
                    Daftar Siswa Terverifikasi
                </h2>
                <span class="bg-blue-900/40 text-blue-300 px-3 py-1 rounded-full text-xs"><?php echo $total_verified; ?> Siswa</span>
            </div>
            
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
                                <tr class="table-row border-b border-gray-700/50">
                                    <td class="px-4 py-3 text-sm"><?php echo str_pad($student['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                    <td class="px-4 py-3 text-sm font-medium"><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="bg-blue-900/40 text-blue-300 px-2 py-1 rounded text-xs"><?php echo htmlspecialchars($student['program'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <?php if ($student['exam_permission']): ?>
                                            <span class="inline-flex items-center px-2 py-1 bg-green-900/40 text-green-300 rounded-full text-xs">
                                                <i class="fas fa-check-circle mr-1"></i> Diizinkan
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 bg-red-900/40 text-red-300 rounded-full text-xs">
                                                <i class="fas fa-times-circle mr-1"></i> Tidak Diizinkan
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <?php if (!is_null($student['score'])): ?>
                                            <span class="inline-flex items-center px-2 py-1 bg-blue-900/40 text-blue-300 rounded-full text-xs">
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
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="flex justify-center mt-6">
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&permission=<?php echo $permission_filter; ?>&exam_status><?php echo $exam_status_filter; ?>" class="pagination-btn px-3 py-1 rounded-lg text-sm">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&permission=<?php echo $permission_filter; ?>&exam_status><?php echo $exam_status_filter; ?>" class="pagination-btn px-3 py-1 rounded-lg text-sm <?php echo $i === $page ? 'pagination-active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&permission=<?php echo $permission_filter; ?>&exam_status><?php echo $exam_status_filter; ?>" class="pagination-btn px-3 py-1 rounded-lg text-sm">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Initialize Chart.js
        const ctx = document.getElementById('examScoresChart').getContext('2d');
        const chartData = <?php echo json_encode($chart_data); ?>;
        const labels = Array.from({length: 101}, (_, i) => i);
        const data = labels.map(score => chartData[score] || 0);
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Distribusi Nilai',
                    data: data,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        title: { display: true, text: 'Nilai', color: '#e2e8f0' },
                        grid: { borderColor: '#4b5563' }
                    },
                    y: {
                        title: { display: true, text: 'Jumlah Siswa', color: '#e2e8f0' },
                        beginAtZero: true,
                        grid: { borderColor: '#4b5563' }
                    }
                },
                plugins: {
                    legend: { labels: { color: '#e2e8f0' } }
                }
            }
        });

        // Toggle filter dropdown
        document.getElementById('filterBtn').addEventListener('click', () => {
            const dropdown = document.getElementById('filterDropdown');
            dropdown.classList.toggle('hidden');
        });

        // Toggle sort dropdown
        document.getElementById('sortBtn').addEventListener('click', () => {
            const dropdown = document.getElementById('sortDropdown');
            dropdown.classList.toggle('hidden');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#filterBtn') && !e.target.closest('#filterDropdown')) {
                document.getElementById('filterDropdown').classList.add('hidden');
            }
            if (!e.target.closest('#sortBtn') && !e.target.closest('#sortDropdown')) {
                document.getElementById('sortDropdown').classList.add('hidden');
            }
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const query = this.value;
            window.location.href = `?search=${encodeURIComponent(query)}&sort=<?php echo $sort; ?>&permission=<?php echo $permission_filter; ?>&exam_status><?php echo $exam_status_filter; ?>`;
        });

        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', () => {
            window.location.href = 'exam_management.php';
        });
    </script>
</body>
</html>