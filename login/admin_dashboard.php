<?php
session_start();

// Enable error logging and minimal display for debugging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);

// Debug log function
function debug_log($message) {
    file_put_contents('php_errors.log', date('Y-m-d H:i:s') . " [DEBUG] $message\n", FILE_APPEND);
}

debug_log("Admin dashboard script started");

// Check admin login
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    debug_log("Unauthorized access, redirecting to login.php");
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
    debug_log("Database connection failed: " . $conn->connect_error);
    die("Koneksi database gagal: " . $conn->connect_error);
}
debug_log("Database connected successfully");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'verify') {
        debug_log("Processing verify action for student_id: " . ($_POST['student_id'] ?? 'not set'));
        $id = $_POST['student_id'];
        $new_status = $_POST['new_status'];

        // Validate status
        $valid_statuses = ['Terverifikasi', 'Diterima', 'Ditolak'];
        if (!in_array($new_status, $valid_statuses)) {
            debug_log("Invalid status: $new_status");
            echo json_encode(['status' => 'error', 'message' => 'Status tidak valid']);
            exit;
        }

        $sql_update = "UPDATE registrations SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql_update);
        if (!$stmt) {
            debug_log("Failed to prepare update query: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyiapkan kueri: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("si", $new_status, $id);

        if ($stmt->execute()) {
            debug_log("Status updated to $new_status for student_id: $id");
            $response = ['status' => 'success', 'message' => "Status pendaftar berhasil diubah menjadi $new_status"];
        } else {
            debug_log("Update query failed: " . $stmt->error);
            $response = ['status' => 'error', 'message' => 'Gagal memperbarui status: ' . $stmt->error];
        }

        $stmt->close();
        echo json_encode($response);
        exit;
    }

    if ($_POST['action'] === 'delete') {
        debug_log("Starting delete action for student_id: " . ($_POST['student_id'] ?? 'not set'));

        // Validate student_id
        if (!isset($_POST['student_id']) || !is_numeric($_POST['student_id'])) {
            debug_log("Invalid or missing student_id");
            echo json_encode(['status' => 'error', 'message' => 'ID pendaftar tidak valid']);
            exit;
        }

        $id = (int)$_POST['student_id'];
        debug_log("Processing delete for student_id: $id");

        // Begin transaction
        $conn->begin_transaction();

        try {
            // Check if registration exists and get user_id
            $sql_check = "SELECT id, user_id FROM registrations WHERE id = ?";
            $stmt_check = $conn->prepare($sql_check);
            if (!$stmt_check) {
                debug_log("Failed to prepare check query: " . $conn->error);
                throw new Exception('Gagal memeriksa pendaftar: ' . $conn->error);
            }
            $stmt_check->bind_param("i", $id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows === 0) {
                debug_log("No registration found for student_id: $id");
                throw new Exception('Pendaftar tidak ditemukan');
            }

            $registration = $result_check->fetch_assoc();
            $user_id = $registration['user_id'];
            $stmt_check->close();

            // Delete associated documents (if any)
            $sql_delete_documents = "DELETE FROM documents WHERE registration_id = ?";
            $stmt_documents = $conn->prepare($sql_delete_documents);
            if (!$stmt_documents) {
                debug_log("Failed to prepare documents delete query: " . $conn->error);
                throw new Exception('Gagal menyiapkan kueri hapus dokumen: ' . $conn->error);
            }
            $stmt_documents->bind_param("i", $id);
            if (!$stmt_documents->execute()) {
                debug_log("Documents delete query failed: " . $stmt_documents->error);
                throw new Exception('Gagal menghapus dokumen: ' . $stmt_documents->error);
            }
            $stmt_documents->close();
            debug_log("Attempted to delete associated documents for registration_id: $id (table may be empty)");

            // Delete user if no other registrations
            $sql_check_user = "SELECT COUNT(*) as count FROM registrations WHERE user_id = ? AND id != ?";
            $stmt_check_user = $conn->prepare($sql_check_user);
            if (!$stmt_check_user) {
                debug_log("Failed to prepare user check query: " . $conn->error);
                throw new Exception('Gagal memeriksa pengguna: ' . $conn->error);
            }
            $stmt_check_user->bind_param("ii", $user_id, $id);
            $stmt_check_user->execute();
            $count = $stmt_check_user->get_result()->fetch_assoc()['count'];
            $stmt_check_user->close();

            if ($count == 0) {
                $sql_delete_user = "DELETE FROM users WHERE user_id = ?";
                $stmt_user = $conn->prepare($sql_delete_user);
                if (!$stmt_user) {
                    debug_log("Failed to prepare user delete query: " . $conn->error);
                    throw new Exception('Gagal menyiapkan kueri hapus pengguna: ' . $conn->error);
                }
                $stmt_user->bind_param("i", $user_id);
                if (!$stmt_user->execute()) {
                    debug_log("User delete query failed: " . $stmt_user->error);
                    throw new Exception('Gagal menghapus pengguna: ' . $stmt_user->error);
                }
                $stmt_user->close();
                debug_log("Deleted user_id: $user_id");
            } else {
                debug_log("User_id: $user_id has other registrations, not deleted");
            }

            // Delete registration
            $sql_delete = "DELETE FROM registrations WHERE id = ?";
            $stmt = $conn->prepare($sql_delete);
            if (!$stmt) {
                debug_log("Failed to prepare delete query: " . $conn->error);
                throw new Exception('Gagal menyiapkan kueri hapus: ' . $conn->error);
            }
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                debug_log("Delete query failed: " . $stmt->error);
                throw new Exception('Gagal menghapus pendaftar: ' . $stmt->error);
            }

            $stmt->close();
            $conn->commit();
            debug_log("Successfully deleted registration and user for student_id: $id");
            echo json_encode(['status' => 'success', 'message' => 'Pendaftar berhasil dihapus']);
        } catch (Exception $e) {
            $conn->rollback();
            debug_log("Error during delete: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        $conn->close();
        exit;
    }

    if ($_POST['action'] === 'filter_verifikasi') {
        debug_log("Processing filter_verifikasi action with status: " . ($_POST['status'] ?? 'not set'));
        $status = $_POST['status'];
        $sql_all = "SELECT id, full_name, email, program, phone, created_at, status FROM registrations";
        if ($status !== 'Semua Status') {
            $sql_all .= " WHERE status = ?";
        }
        $sql_all .= " ORDER BY created_at DESC LIMIT 50";

        $stmt = $conn->prepare($sql_all);
        if (!$stmt) {
            debug_log("Failed to prepare filter query: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyiapkan kueri: ' . $conn->error]);
            exit;
        }
        if ($status !== 'Semua Status') {
            $stmt->bind_param("s", $status);
        }
        if (!$stmt->execute()) {
            debug_log("Filter query execution failed: " . $stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menjalankan kueri: ' . $stmt->error]);
            $stmt->close();
            exit;
        }
        $result_all = $stmt->get_result();
        $rows = [];

        while ($row = $result_all->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();
        echo json_encode(['status' => 'success', 'data' => $rows]);
        exit;
    }
}

// Handle export CSV
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    debug_log("Processing export_csv action");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pendaftar_verifikasi.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['#', 'Nama Lengkap', 'Email', 'NISN', 'Program', 'No. Telepon', 'Tanggal Daftar', 'Status']);

    $sql_all = "SELECT id, full_name, email, nisn, program, phone, created_at, status FROM registrations ORDER BY created_at DESC";
    $result_all = $conn->query($sql_all);
    $nomor = 1;

    while ($row = $result_all->fetch_assoc()) {
        fputcsv($output, [
            $nomor++,
            $row['full_name'],
            $row['email'],
            $row['nisn'],
            $row['program'],
            $row['phone'],
            date('d/m/Y', strtotime($row['created_at'])),
            $row['status']
        ]);
    }

    fclose($output);
    $conn->close();
    exit;
}

// Optimized dashboard queries
try {
    // Total pendaftar
    $sql_total = "SELECT COUNT(*) as total FROM registrations";
    $result_total = $conn->query($sql_total);
    if (!$result_total) {
        debug_log("Total query failed: " . $conn->error);
        throw new Exception('Gagal mengambil total pendaftar');
    }
    $total_pendaftar = $result_total->fetch_assoc()['total'];

    // Pendaftar per program
    $sql_program = "SELECT program, COUNT(*) as jumlah FROM registrations GROUP BY program";
    $result_program = $conn->query($sql_program);
    if (!$result_program) {
        debug_log("Program query failed: " . $conn->error);
        throw new Exception('Gagal mengambil data program');
    }
    $pendaftar_per_program = [];
    while ($row = $result_program->fetch_assoc()) {
        $pendaftar_per_program[$row['program']] = $row['jumlah'];
    }

    // Pendaftar berdasarkan status
    $sql_verified = "SELECT COUNT(*) as verified FROM registrations WHERE status = 'Terverifikasi'";
    $result_verified = $conn->query($sql_verified);
    if (!$result_verified) {
        debug_log("Verified query failed: " . $conn->error);
        throw new Exception('Gagal mengambil data terverifikasi');
    }
    $verified_count = $result_verified->fetch_assoc()['verified'];

    $sql_unverified = "SELECT COUNT(*) as unverified FROM registrations WHERE status = 'Menunggu Verifikasi'";
    $result_unverified = $conn->query($sql_unverified);
    if (!$result_unverified) {
        debug_log("Unverified query failed: " . $conn->error);
        throw new Exception('Gagal mengambil data belum verifikasi');
    }
    $unverified_count = $result_unverified->fetch_assoc()['unverified'];

    // Table data (optimized with specific columns and limit)
    $sql_all = "SELECT id, full_name, email, program, phone, created_at, status FROM registrations ORDER BY created_at DESC LIMIT 50";
    $result_all = $conn->query($sql_all);
    if (!$result_all) {
        debug_log("Table query failed: " . $conn->error);
        throw new Exception('Gagal mengambil data tabel');
    }

    // Monthly data
    $sql_monthly = "SELECT MONTH(created_at) as bulan, COUNT(*) as jumlah 
                    FROM registrations 
                    WHERE YEAR(created_at) = YEAR(CURRENT_DATE()) 
                    GROUP BY MONTH(created_at) 
                    ORDER BY bulan";
    $result_monthly = $conn->query($sql_monthly);
    if (!$result_monthly) {
        debug_log("Monthly query failed: " . $conn->error);
        throw new Exception('Gagal mengambil data bulanan');
    }
    $monthly_data = array_fill(1, 12, 0);
    $bulan_names = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    while ($row = $result_monthly->fetch_assoc()) {
        $monthly_data[$row['bulan']] = (int)$row['jumlah'];
    }

} catch (Exception $e) {
    debug_log("Dashboard query error: " . $e->getMessage());
    $conn->close();
    die("Error loading dashboard: " . htmlspecialchars($e->getMessage()));
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - STM Gotham City</title>
    <link rel="icon" href="https://cdn.pixabay.com/photo/2018/10/05/21/29/bat-3726896_1280.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
            margin: 0;
            overflow-x: hidden;
        }

        .sidebar {
            background: #1e293b;
            transition: transform 0.3s ease;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.2);
            width: 18rem;
        }

        .sidebar.hidden {
            transform: translateX(-18rem);
        }

        .sidebar-link {
            transition: background 0.2s ease, border-left-color 0.2s ease;
            border-left: 3px solid transparent;
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
        }

        .sidebar-link:hover, .sidebar-link.active {
            background: #2c3e50;
            border-left-color: #3b82f6;
        }

        .dashboard-card {
            background: #1e293b;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            padding: 1.5rem;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(59, 130, 246, 0.2);
        }

        .table-container {
            background: #1e293b;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .data-table th {
            background: #2d3748;
            padding: 0.75rem 1.5rem;
            text-align: left;
            font-weight: 600;
        }

        .data-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #374151;
        }

        .data-table tr:hover {
            background: #2d3748;
        }

        .action-btn {
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-2px);
        }

        .nav-bar {
            background: #1e293b;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            background: #1e293b;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            z-index: 10;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            display: block;
            padding: 0.5rem 1rem;
            color: #e2e8f0;
            transition: background 0.2s ease;
        }

        .dropdown-item:hover {
            background: #2d3748;
        }

        .loading-spinner {
            border: 4px solid #3b82f6;
            border-top: 4px solid transparent;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-18rem);
            }
            .sidebar.hidden {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar fixed top-0 left-0 h-screen overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center space-x-4 mb-8">
                    <img src="https://cdn.pixabay.com/photo/2018/10/05/21/29/bat-3726896_1280.png" alt="Logo STM Gotham City" class="w-10 h-10">
                    <div>
                        <h1 class="text-lg font-bold text-white">STM Gotham City</h1>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Menu</p>
                    
                    <a href="#dashboard" class="sidebar-link active">
                        <i class="fas fa-tachometer-alt w-5 text-blue-400 mr-3"></i>
                        <span>Dashboard</span>
                    </a>

                    <a href="inbox.php" class="sidebar-link">
                        <i class="fas fa-envelope w-5 text-blue-400 mr-3"></i>
                        <span>Inbox</span>
                    </a>

                    <a href="exam_management.php" class="sidebar-link">
                        <i class="fas fa-file-alt w-5 text-blue-400 mr-3"></i>
                        <span>Exam</span>
                    </a>
                </div>

                <div class="absolute bottom-0 left-0 w-full p-6">
                    <a href="logout.php" class="flex items-center justify-center space-x-2 py-2 px-4 bg-red-900 hover:bg-red-800 rounded-lg transition">
                        <i class="fas fa-sign-out-alt text-red-400"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 main-content ml-72 transition-all duration-300">
            <!-- Top Navigation -->
            <nav class="nav-bar p-4 sticky top-0 z-10">
                <div class="container mx-auto flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <button id="toggle-sidebar" class="p-2 rounded-lg hover:bg-gray-700">
                            <i class="fas fa-bars text-xl text-white"></i>
                        </button>
                        <div>
                            <h2 class="text-lg font-bold">Dashboard Admin</h2>
                            <p class="text-sm text-gray-400">Selamat datang, <?php echo htmlspecialchars($_SESSION['admin_id']); ?></p>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Dashboard Content -->
            <div class="container mx-auto p-6">
                <!-- Dashboard Overview -->
                <section id="dashboard" class="mb-12">
                    <h3 class="text-2xl font-bold mb-6">Ikhtisar Dashboard</h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- Total Pendaftar Card -->
                        <div class="dashboard-card">
                            <div class="flex justify-between items-start">
                                <div class="bg-blue-900 rounded-lg p-3">
                                    <i class="fas fa-users text-blue-400 text-xl"></i>
                                </div>
                                <span class="text-xs font-semibold bg-blue-900 text-blue-400 px-2 py-1 rounded-full">24 Jam</span>
                            </div>
                            <h4 class="text-xl font-bold mt-4"><?php echo $total_pendaftar; ?></h4>
                            <p class="text-gray-400 text-sm">Total Pendaftar</p>
                            <div class="flex items-center mt-2 text-green-400 text-xs">
                                <i class="fas fa-arrow-up mr-1"></i>
                                <span>12% dibanding minggu lalu</span>
                            </div>
                        </div>
                        <!-- Pendaftar Terverifikasi Card -->
                        <div class="dashboard-card">
                            <div class="flex justify-between items-start">
                                <div class="bg-green-900 rounded-lg p-3">
                                    <i class="fas fa-check-circle text-blue-400 text-xl"></i>
                                </div>
                                <span class="text-xs font-semibold bg-green-900 text-green-400 px-2 py-1 rounded-full">24 Jam</span>
                            </div>
                            <h4 class="text-xl font-bold mt-4"><?php echo $verified_count; ?></h4>
                            <p class="text-gray-400 text-sm">Pendaftar Terverifikasi</p>
                            <div class="flex items-center mt-2 text-green-400 text-xs">
                                <i class="fas fa-arrow-up mr-1"></i>
                                <span>8% dibanding minggu lalu</span>
                            </div>
                        </div>
                        <!-- Pendaftar Belum Verifikasi Card -->
                        <div class="dashboard-card">
                            <div class="flex justify-between items-start">
                                <div class="bg-yellow-900 rounded-lg p-3">
                                    <i class="fas fa-clock text-yellow-400 text-xl"></i>
                                </div>
                                <span class="text-xs font-semibold bg-yellow-900 text-yellow-400 px-2 py-1 rounded-full">24 Jam</span>
                            </div>
                            <h4 class="text-xl font-bold mt-4"><?php echo $unverified_count; ?></h4>
                            <p class="text-gray-400 text-sm">Menunggu Verifikasi</p>
                            <div class="flex items-center mt-2 text-yellow-400 text-xs">
                                <i class="fas fa-clock mr-1"></i>
                                <span>Perlu ditindaklanjuti</span>
                            </div>
                        </div>
                        <!-- Program Terpopuler Card -->
                        <div class="dashboard-card">
                            <div class="flex justify-between items-start">
                                <div class="bg-purple-900 rounded-lg p-3">
                                    <i class="fas fa-star text-purple-400 text-xl"></i>
                                </div>
                                <span class="text-xs font-semibold bg-purple-900 text-purple-400 px-2 py-1 rounded-full">24 Jam</span>
                            </div>
                            <?php
                            $program_populer = !empty($pendaftar_per_program) ? array_search(max($pendaftar_per_program), $pendaftar_per_program) : 'Tidak ada data';
                            ?>
                            <h4 class="text-xl font-bold mt-4"><?php echo htmlspecialchars($program_populer); ?></h4>
                            <p class="text-gray-400 text-sm">Program Terpopuler</p>
                            <div class="flex items-center mt-2 text-blue-400 text-xs">
                                <i class="fas fa-chart-line mr-1"></i>
                                <span><?php echo !empty($pendaftar_per_program) ? max($pendaftar_per_program) : 0; ?> pendaftar</span>
                            </div>
                        </div>
                    </div>
                    <!-- Charts Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Graph Card -->
                        <div class="dashboard-card">
                            <div class="flex justify-between items-center mb-4">
                                <h5 class="font-bold text-lg">Trend Pendaftaran Bulanan</h5>
                                <div class="flex space-x-2">
                                    <button class="px-3 py-1 text-sm bg-blue-900 text-blue-400 rounded-md hover:bg-blue-800">Harian</button>
                                    <button class="px-3 py-1 text-sm bg-gray-700 text-gray-400 rounded-md hover:bg-gray-600">Mingguan</button>
                                    <button class="px-3 py-1 text-sm bg-gray-700 text-gray-400 rounded-md hover:bg-gray-600">Bulanan</button>
                                </div>
                            </div>
                            <div class="relative h-64">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                        <!-- Distribution Card -->
                        <div class="dashboard-card">
                            <div class="flex justify-between items-center mb-4">
                                <h5 class="font-bold text-lg">Distribusi Pendaftar per Program</h5>
                                <button class="px-3 py-1 text-sm bg-gray-700 text-gray-400 rounded-md hover:bg-gray-600">
                                    <i class="fas fa-download mr-1"></i> Export
                                </button>
                            </div>
                            <div class="relative h-64">
                                <canvas id="programChart"></canvas>
                            </div>
                        </div>
                    </div>
                </section>
                <!-- Verifikasi Pendaftar -->
                <section id="verifikasi" class="mb-12">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-2xl font-bold">Verifikasi Pendaftar</h3>
                            <p class="text-gray-400 text-sm">Kelola dan verifikasi semua pendaftar</p>
                        </div>
                        <div class="flex space-x-3">
                            <div class="relative">
                                <button id="filter-btn" class="px-4 py-2 bg-gray-700 rounded-lg flex items-center hover:bg-gray-600">
                                    <i class="fas fa-filter mr-2"></i> Filter
                                </button>
                                <div id="filter-menu" class="dropdown-menu">
                                    <a href="#" class="dropdown-item" data-status="Semua Status">Semua Status</a>
                                    <a href="#" class="dropdown-item" data-status="Menunggu Verifikasi">Menunggu Verifikasi</a>
                                    <a href="#" class="dropdown-item" data-status="Terverifikasi">Terverifikasi</a>
                                    <a href="#" class="dropdown-item" data-status="Diterima">Diterima</a>
                                    <a href="#" class="dropdown-item" data-status="Ditolak">Ditolak</a>
                                </div>
                            </div>
                            <a href="?action=export_csv" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg flex items-center">
                                <i class="fas fa-download mr-2"></i> Export
                            </a>
                            <button id="refresh-btn" class="px-4 py-2 bg-green-700 hover:bg-green-800 rounded-lg flex items-center text-white transition">
                                <i class="fas fa-sync-alt mr-2"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="data-table w-full">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama Lengkap</th>
                                    <th>Email</th>
                                    <th>Program</th>
                                    <th>No. Telepon</th>
                                    <th>Tanggal Daftar</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="verifikasi-registrations">
                                <?php
                                if ($result_all->num_rows > 0) {
                                    $nomor = 1;
                                    while ($row = $result_all->fetch_assoc()) {
                                        $status_class = match ($row['status']) {
                                            'Terverifikasi' => 'bg-green-900 text-green-400',
                                            'Diterima' => 'bg-blue-900 text-blue-400',
                                            'Ditolak' => 'bg-red-900 text-red-400',
                                            'Menunggu Verifikasi' => 'bg-yellow-900 text-yellow-300',
                                            default => 'bg-yellow-900 text-yellow-300',
                                        };
                                        $status_display = $row['status'] ?: 'Menunggu Verifikasi';
                                        $program_display = htmlspecialchars($row['program']);
                                        echo '<tr>';
                                        echo '<td>' . $nomor++ . '</td>';
                                        echo '<td>' . htmlspecialchars($row['full_name']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                                        echo '<td>' . $program_display . '</td>';
                                        echo '<td>' . htmlspecialchars($row['phone']) . '</td>';
                                        echo '<td>' . date('d/m/Y', strtotime($row['created_at'])) . '</td>';
                                        echo '<td><span class="px-2 py-1 rounded-full text-xs ' . $status_class . '">' . htmlspecialchars($status_display) . '</span></td>';
                                        echo '<td class="text-center">';
                                        echo '<div class="flex justify-center space-x-2">';
                                        echo '<a href="./student_detail.php?id=' . $row['id'] . '" class="action-btn bg-blue-900 text-blue-400 hover:bg-blue-800" title="Lihat Detail"><i class="fas fa-eye"></i></a>';
                                        if ($row['status'] === 'Menunggu Verifikasi') {
                                            echo '<button onclick="verifyStudent(' . $row['id'] . ')" class="action-btn bg-green-900 text-green-400 hover:bg-green-800" title="Verifikasi"><i class="fas fa-check"></i></button>';
                                        }
                                        echo '<button onclick="deleteStudent(' . $row['id'] . ')" class="action-btn bg-red-900 text-red-400 hover:bg-red-800" title="Hapus"><i class="fas fa-trash"></i></button>';
                                        echo '</div>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="8" class="text-center">Tidak ada data pendaftar</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Footer -->
            <footer class="bg-gray-900 p-6 text-center text-gray-400">
                <p>© 2025 STM Gotham City. Hak Cipta Dilindungi.</p>
            </footer>
        </div>
    </div>

    <script defer>
        // Sidebar Toggle
        const toggleSidebar = document.getElementById('toggle-sidebar');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');

        toggleSidebar.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('ml-72');
            mainContent.classList.toggle('ml-0');
        });

        // Smooth Scroll for Sidebar Links
        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                if (href.startsWith('#')) {
                    e.preventDefault();
                    const targetId = href.substring(1);
                    const targetElement = document.getElementById(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });
                        document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
                        link.classList.add('active');
                    }
                }
            });
        });

        // Verify Student Function
        function verifyStudent(studentId) {
            Swal.fire({
                title: 'Verifikasi Pendaftar',
                text: 'Pilih status baru untuk pendaftar ini:',
                icon: 'question',
                input: 'select',
                inputOptions: {
                    'Terverifikasi': 'Terverifikasi',
                    'Diterima': 'Diterima',
                    'Ditolak': 'Ditolak'
                },
                inputPlaceholder: 'Pilih Status',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Anda harus memilih status!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses...',
                        html: '<div class="loading-spinner"></div>',
                        allowOutsideClick: false,
                        showConfirmButton: false
                    });
                    fetch('admin_dashboard.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=verify&student_id=${studentId}&new_status=${encodeURIComponent(result.value)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.fire({
                            icon: data.status,
                            title: data.status === 'success' ? 'Berhasil!' : 'Gagal',
                            text: data.message,
                            confirmButtonColor: '#3b82f6'
                        }).then(() => {
                            if (data.status === 'success') {
                                // Refresh Verifikasi Pendaftar
                                fetch('admin_dashboard.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: 'action=filter_verifikasi&status=Semua Status'
                                })
                                .then(res => res.json())
                                .then(verifikasiData => {
                                    const verifikasiTbody = document.getElementById('verifikasi-registrations');
                                    verifikasiTbody.innerHTML = '';
                                    if (verifikasiData.data.length > 0) {
                                        const statusClasses = {
                                            'Terverifikasi': 'bg-green-900 text-green-400',
                                            'Diterima': 'bg-blue-900 text-blue-400',
                                            'Ditolak': 'bg-red-900 text-red-400',
                                            'Menunggu Verifikasi': 'bg-yellow-900 text-yellow-300'
                                        };
                                        let nomor = 1;
                                        verifikasiData.data.forEach(row => {
                                            const statusClass = statusClasses[row.status] || 'bg-yellow-900 text-yellow-300';
                                            const statusDisplay = row.status || 'Menunggu Verifikasi';
                                            const programDisplay = row.program;
                                            const tr = document.createElement('tr');
                                            tr.innerHTML = `
                                                <td>${nomor++}</td>
                                                <td>${row.full_name}</td>
                                                <td>${row.email}</td>
                                                <td>${programDisplay}</td>
                                                <td>${row.phone}</td>
                                                <td>${new Date(row.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' })}</td>
                                                <td><span class="px-2 py-1 rounded-full text-xs ${statusClass}">${statusDisplay}</span></td>
                                                <td class="text-center">
                                                    <div class="flex justify-center space-x-2">
                                                        <a href="./student_detail.php?id=${row.id}" class="action-btn bg-blue-900 text-blue-400 hover:bg-blue-800" title="Lihat Detail"><i class="fas fa-eye"></i></a>
                                                        ${row.status === 'Menunggu Verifikasi' ? `<button onclick="verifyStudent(${row.id})" class="action-btn bg-green-900 text-green-400 hover:bg-green-800" title="Verifikasi"><i class="fas fa-check"></i></button>` : ''}
                                                        <button onclick="deleteStudent(${row.id})" class="action-btn bg-red-900 text-red-400 hover:bg-red-800" title="Hapus"><i class="fas fa-trash"></i></button>
                                                    </div>
                                                </td>
                                            `;
                                            verifikasiTbody.appendChild(tr);
                                        });
                                    } else {
                                        verifikasiTbody.innerHTML = '<tr><td colspan="8" class="text-center">Tidak ada data pendaftar</td></tr>';
                                    }
                                });
                            }
                        });
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan: ' + error.message,
                            confirmButtonColor: '#3b82f6'
                        });
                    });
                }
            });
        }

        // Delete Student Function
        function deleteStudent(studentId) {
            Swal.fire({
                title: 'Hapus Pendaftar',
                text: 'Apakah Anda yakin ingin menghapus pendaftar ini? Tindakan ini tidak dapat dibatalkan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses...',
                        html: '<div class="loading-spinner"></div>',
                        allowOutsideClick: false,
                        showConfirmButton: false
                    });
                    fetch('admin_dashboard.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete&student_id=${studentId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.fire({
                            icon: data.status,
                            title: data.status === 'success' ? 'Berhasil!' : 'Gagal',
                            text: data.message,
                            confirmButtonColor: '#3b82f6'
                        }).then(() => {
                            if (data.status === 'success') location.reload();
                        });
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan: ' + error.message,
                            confirmButtonColor: '#3b82f6'
                        });
                    });
                }
            });
        }

        // Filter Menu Toggle
        const filterBtn = document.getElementById('filter-btn');
        const filterMenu = document.getElementById('filter-menu');

        filterBtn.addEventListener('click', () => {
            filterMenu.classList.toggle('show');
        });

        // Close filter menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!filterBtn.contains(e.target) && !filterMenu.contains(e.target)) {
                filterMenu.classList.remove('show');
            }
        });

        // Filter Verifikasi Function
        document.querySelectorAll('#filter-menu .dropdown-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const status = item.getAttribute('data-status');
                filterMenu.classList.remove('show');
                
                Swal.fire({
                    title: 'Memuat Data...',
                    html: '<div class="loading-spinner"></div>',
                    allowOutsideClick: false,
                    showConfirmButton: false
                });
                
                fetch('admin_dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=filter_verifikasi&status=${encodeURIComponent(status)}`
                })
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.status === 'success') {
                        const tbody = document.getElementById('verifikasi-registrations');
                        tbody.innerHTML = '';
                        if (data.data.length > 0) {
                            const statusClasses = {
                                'Terverifikasi': 'bg-green-900 text-green-400',
                                'Diterima': 'bg-blue-900 text-blue-400',
                                'Ditolak': 'bg-red-900 text-red-400',
                                'Menunggu Verifikasi': 'bg-yellow-900 text-yellow-300'
                            };
                            let nomor = 1;
                            data.data.forEach(row => {
                                const statusClass = statusClasses[row.status] || 'bg-yellow-900 text-yellow-300';
                                const statusDisplay = row.status || 'Menunggu Verifikasi';
                                const programDisplay = row.program;
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td>${nomor++}</td>
                                    <td>${row.full_name}</td>
                                    <td>${row.email}</td>
                                    <td>${programDisplay}</td>
                                    <td>${row.phone}</td>
                                    <td>${new Date(row.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' })}</td>
                                    <td><span class="px-2 py-1 rounded-full text-xs ${statusClass}">${statusDisplay}</span></td>
                                    <td class="text-center">
                                        <div class="flex justify-center space-x-2">
                                            <a href="./student_detail.php?id=${row.id}" class="action-btn bg-blue-900 text-blue-400 hover:bg-blue-800" title="Lihat Detail"><i class="fas fa-eye"></i></a>
                                            ${row.status === 'Menunggu Verifikasi' ? `<button onclick="verifyStudent(${row.id})" class="action-btn bg-green-900 text-green-400 hover:bg-green-800" title="Verifikasi"><i class="fas fa-check"></i></button>` : ''}
                                            <button onclick="deleteStudent(${row.id})" class="action-btn bg-red-900 text-red-400 hover:bg-red-800" title="Hapus"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                `;
                                tbody.appendChild(tr);
                            });
                        } else {
                            tbody.innerHTML = '<tr><td colspan="8" class="text-center">Tidak ada data pendaftar</td></tr>';
                        }
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: 'Data telah difilter',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: 'Gagal memuat data',
                            confirmButtonColor: '#3b82f6'
                        });
                    }
                })
                .catch(error => {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Terjadi kesalahan: ' + error.message,
                        confirmButtonColor: '#3b82f6'
                    });
                });
            });
        });

        // Chart.js Setup
        const monthlyChart = new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_values($bulan_names)); ?>,
                datasets: [{
                    label: 'Pendaftar per Bulan',
                    data: <?php echo json_encode(array_values($monthly_data)); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#e2e8f0' },
                        grid: { color: '#374151' }
                    },
                    x: {
                        ticks: { color: '#e2e8f0' },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { labels: { color: '#e2e8f0' } }
                }
            }
        });

        const programChart = new Chart(document.getElementById('programChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($pendaftar_per_program)); ?>,
                datasets: [{
                    label: 'Pendaftar per Program',
                    data: <?php echo json_encode(array_values($pendaftar_per_program)); ?>,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b'],
                    borderColor: '#1e293b',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#e2e8f0' }
                    }
                }
            }
        });

        // Refresh Button Functionality
        document.getElementById('refresh-btn').addEventListener('click', function() {
            location.reload();
        });
    </script>
</body>
</html>