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

// Handle export to CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_csv'])) {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $program_filter = isset($_GET['program']) ? $_GET['program'] : 'all';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';

    // Build the WHERE clause
    $where_clauses = ["es.status = 'Completed'"];
    $params = [];
    $types = '';

    if ($search) {
        $where_clauses[] = "(r.full_name LIKE ? OR r.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ss';
    }

    if ($program_filter !== 'all') {
        $where_clauses[] = "r.program = ?";
        $params[] = $program_filter;
        $types .= 's';
    }

    // Build the ORDER BY clause
    $order_by = 'r.full_name ASC';
    switch ($sort) {
        case 'name_desc':
            $order_by = 'r.full_name DESC';
            break;
        case 'score_asc':
            $order_by = 'es.score ASC';
            break;
        case 'score_desc':
            $order_by = 'es.score DESC';
            break;
        case 'date_asc':
            $order_by = 'es.completed_at ASC';
            break;
        case 'date_desc':
            $order_by = 'es.completed_at DESC';
            break;
    }

    // Fetch data for export
    $sql = "SELECT r.id, r.full_name, r.email, r.program, es.score, es.completed_at 
            FROM registrations r 
            JOIN exam_sessions es ON r.id = es.registration_id";
    if ($where_clauses) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }
    $sql .= " ORDER BY $order_by";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="exam_results_' . date('Ymd_His') . '.csv"');

    // Create CSV output
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Nama', 'Email', 'Program', 'Nilai', 'Tanggal Selesai']);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            str_pad($row['id'], 5, '0', STR_PAD_LEFT),
            $row['full_name'],
            $row['email'],
            $row['program'],
            $row['score'],
            date('d M Y H:i', strtotime($row['completed_at']))
        ]);
    }

    fclose($output);
    $stmt->close();
    exit;
}

// Initialize variables for search, filter, sort, and pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$program_filter = isset($_GET['program']) ? $_GET['program'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;

// Build the WHERE clause
$where_clauses = ["es.status = 'Completed'"];
$params = [];
$types = '';

if ($search) {
    $where_clauses[] = "(r.full_name LIKE ? OR r.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if ($program_filter !== 'all') {
    $where_clauses[] = "r.program = ?";
    $params[] = $program_filter;
    $types .= 's';
}

// Build the ORDER BY clause
$order_by = 'r.full_name ASC';
switch ($sort) {
    case 'name_desc':
        $order_by = 'r.full_name DESC';
        break;
    case 'score_asc':
        $order_by = 'es.score ASC';
        break;
    case 'score_desc':
        $order_by = 'es.score DESC';
        break;
    case 'date_asc':
        $order_by = 'es.completed_at ASC';
        break;
    case 'date_desc':
        $order_by = 'es.completed_at DESC';
        break;
}

// Count total records for pagination
$count_sql = "SELECT COUNT(DISTINCT r.id) as total 
              FROM registrations r 
              JOIN exam_sessions es ON r.id = es.registration_id";
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

// Fetch exam results
$sql = "SELECT r.id, r.full_name, r.email, r.program, es.score, es.completed_at 
        FROM registrations r 
        JOIN exam_sessions es ON r.id = es.registration_id";
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
$results = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch stats for cards
$total_results_sql = "SELECT COUNT(DISTINCT r.id) as count 
                     FROM registrations r 
                     JOIN exam_sessions es ON r.id = es.registration_id 
                     WHERE es.status = 'Completed'";
$avg_score_sql = "SELECT AVG(es.score) as avg_score 
                  FROM exam_sessions es 
                  WHERE es.status = 'Completed'";
$programs_sql = "SELECT DISTINCT program FROM registrations";

$total_results = $conn->query($total_results_sql)->fetch_assoc()['count'];
$avg_score_result = $conn->query($avg_score_sql)->fetch_assoc()['avg_score'];
$avg_score = $avg_score_result ? number_format($avg_score_result, 1) : 0;
$programs_result = $conn->query($programs_sql);
$programs = [];
while ($row = $programs_result->fetch_assoc()) {
    $programs[] = $row['program'];
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
    <title>Export Hasil Ujian - STM Gotham City</title>
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
        
        .gradient-button {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            transition: all 0.3s ease;
        }
        
        .gradient-button:hover {
            background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
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
        
        .filter-dropdown {
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
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
                                <a href="exam_management.php" class="text-sm font-medium text-blue-400 hover:text-blue-300">Manajemen Ujian</a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-500 mx-2 text-sm"></i>
                                <span class="text-sm font-medium text-gray-400">Export Hasil Ujian</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <h1 class="text-3xl md:text-4xl font-bold slide-in flex items-center">
                    <i class="fas fa-file-export text-blue-400 mr-3"></i>
                    Export Hasil Ujian
                </h1>
                <p class="text-gray-400 mt-2 slide-in">Ekspor hasil ujian siswa STM Gotham City ke format CSV</p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <a href="exam_management.php" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition flex items-center text-sm">
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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="card p-6 stats-card blue shimmer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Total Hasil Ujian</p>
                        <h3 class="text-2xl font-bold"><?php echo $total_results; ?></h3>
                    </div>
                    <div class="bg-blue-500/20 p-3 rounded-full">
                        <i class="fas fa-clipboard-check text-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="card p-6 stats-card purple shimmer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Rata-rata Nilai</p>
                        <h3 class="text-2xl font-bold"><?php echo $avg_score; ?></h3>
                    </div>
                    <div class="bg-purple-500/20 p-3 rounded-full">
                        <i class="fas fa-chart-bar text-purple-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Button -->
        <div class="mb-6">
            <form action="" method="post">
                <input type="hidden" name="export_csv" value="1">
                <button type="submit" class="gradient-button px-4 py-2 rounded-lg text-white flex items-center">
                    <i class="fas fa-file-download mr-2"></i> Export ke CSV
                </button>
            </form>
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
                                <h4 class="font-medium mb-2 border-b border-gray-700 pb-1">Program</h4>
                                <div class="space-y-2">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="program" value="all" class="form-radio bg-gray-700 border-gray-600 text-blue-500" <?php echo $program_filter === 'all' ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm">Semua</span>
                                    </label>
                                    <?php foreach ($programs as $program): ?>
                                        <label class="flex items-center cursor-pointer">
                                            <input type="radio" name="program" value="<?php echo htmlspecialchars($program); ?>" class="form-radio bg-gray-700 border-gray-600 text-blue-500" <?php echo $program_filter === $program ? 'checked' : ''; ?>>
                                            <span class="ml-2 text-sm"><?php echo htmlspecialchars($program); ?></span>
                                        </label>
                                    <?php endforeach; ?>
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
                            <a href="?sort=name_asc<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&program=<?php echo $program_filter; ?>" class="w-full text-left px-3 py-2 hover:bg-gray-700 rounded text-sm flex items-center">
                                <i class="fas fa-sort-alpha-down mr-2"></i> Nama (A-Z)
                            </a>
                            <a href="?sort=name_desc<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&program=<?php echo $program_filter; ?>" class="w-full text-left px-3 py-2 hover:bg-gray-700 rounded text-sm flex items-center">
                                <i class="fas fa-sort-alpha-up mr-2"></i> Nama (Z-A)
                            </a>
                            <a href="?sort=score_asc<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&program=<?php echo $program_filter; ?>" class="w-full text-left px-3 py-2 hover:bg-gray-700 rounded text-sm flex items-center">
                                <i class="fas fa-sort-numeric-up mr-2"></i> Nilai (Terendah)
                            </a>
                            <a href="?sort=score_desc<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&program=<?php echo $program_filter; ?>" class="w-full text-left px-3 py-2 hover:bg-gray-700 rounded text-sm flex items-center">
                                <i class="fas fa-sort-numeric-down mr-2"></i> Nilai (Tertinggi)
                            </a>
                            <a href="?sort=date_asc<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&program=<?php echo $program_filter; ?>" class="w-full text-left px-3 py-2 hover:bg-gray-700 rounded text-sm flex items-center">
                                <i class="fas fa-sort-numeric-up mr-2"></i> Tanggal (Terlama)
                            </a>
                            <a href="?sort=date_desc<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&program=<?php echo $program_filter; ?>" class="w-full text-left px-3 py-2 hover:bg-gray-700 rounded text-sm flex items-center">
                                <i class="fas fa-sort-numeric-down mr-2"></i> Tanggal (Terbaru)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table of Exam Results -->
        <div class="card p-6 slide-in">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold flex items-center">
                    <i class="fas fa-clipboard-list text-blue-400 mr-2"></i> 
                    Daftar Hasil Ujian
                </h2>
                <span class="bg-blue-900/40 text-blue-300 px-3 py-1 rounded-full text-xs"><?php echo $total_results; ?> Hasil</span>
            </div>
            
            <?php if (empty($results)): ?>
                <div class="bg-gray-800 p-8 rounded-lg text-center">
                    <i class="fas fa-clipboard-list text-5xl text-gray-500 mb-4"></i>
                    <p class="text-gray-400">Belum ada hasil ujian yang tersedia.</p>
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
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">Nilai</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-300">Tanggal Selesai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr class="table-row border-b border-gray-700/50">
                                    <td class="px-4 py-3 text-sm"><?php echo str_pad($result['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                    <td class="px-4 py-3 text-sm font-medium"><?php echo htmlspecialchars($result['full_name']); ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($result['email']); ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="bg-blue-900/40 text-blue-300 px-2 py-1 rounded text-xs"><?php echo htmlspecialchars($result['program'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="status-badge bg-blue-900/40 text-blue-300"><?php echo htmlspecialchars($result['score']); ?>/100</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm"><?php echo date('d M Y H:i', strtotime($result['completed_at'])); ?></td>
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
                                <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&program=<?php echo $program_filter; ?>" class="pagination-btn px-3 py-1 rounded-lg text-sm">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&program=<?php echo $program_filter; ?>" class="pagination-btn px-3 py-1 rounded-lg text-sm <?php echo $i === $page ? 'pagination-active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&program=<?php echo $program_filter; ?>" class="pagination-btn px-3 py-1 rounded-lg text-sm">
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
            window.location.href = `?search=${encodeURIComponent(query)}&sort=<?php echo $sort; ?>&program=<?php echo $program_filter; ?>`;
        });

        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', () => {
            window.location.href = 'export_results.php';
        });
    </script>
</body>
</html>