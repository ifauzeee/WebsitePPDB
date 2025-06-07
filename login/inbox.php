<?php
ob_start(); // Start output buffering
session_start();

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Debug log function
function debug_log($message) {
    file_put_contents('php_errors.log', date('Y-m-d H:i:s') . " [DEBUG] $message\n", FILE_APPEND);
}

debug_log("Inbox script started");

// Check if admin is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin' || !isset($_SESSION['admin_id'])) {
    debug_log("Unauthorized access, user_type: " . ($_SESSION['user_type'] ?? 'not set') . ", admin_id: " . ($_SESSION['admin_id'] ?? 'not set'));
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
    $_SESSION['error'] = "Koneksi database gagal.";
    header("Location: login.php");
    exit;
}
debug_log("Database connected successfully");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    ob_clean(); // Clear any buffered output

    // Mark message as read
    if ($_POST['action'] === 'mark_read') {
        debug_log("Processing mark_read action for message_id: " . ($_POST['message_id'] ?? 'not set'));
        $message_id = (int)($_POST['message_id'] ?? 0);

        if ($message_id <= 0) {
            debug_log("Invalid message_id");
            echo json_encode(['status' => 'error', 'message' => 'ID pesan tidak valid']);
            exit;
        }

        $sql_update = "UPDATE messages SET is_read = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql_update);
        if (!$stmt) {
            debug_log("Failed to prepare mark_read query: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyiapkan kueri: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $message_id);
        if ($stmt->execute()) {
            debug_log("Message marked as read for message_id: $message_id");
            echo json_encode(['status' => 'success', 'message' => 'Pesan ditandai sebagai dibaca']);
        } else {
            debug_log("Mark_read query failed: " . $stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui status: ' . $stmt->error]);
        }
        $stmt->close();
        exit;
    }

    // Send reply
    if ($_POST['action'] === 'send_reply') {
        debug_log("Processing send_reply action for registration_id: " . ($_POST['registration_id'] ?? 'not set'));
        $registration_id = (int)($_POST['registration_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if ($registration_id <= 0 || empty($message)) {
            debug_log("Invalid registration_id or empty message");
            echo json_encode(['status' => 'error', 'message' => 'Data tidak valid']);
            exit;
        }

        $sql_insert = "INSERT INTO messages (registration_id, subject, message, sender, is_read, sent_at) VALUES (?, 'Balasan Admin', ?, 'Admin', 0, NOW())";
        $stmt = $conn->prepare($sql_insert);
        if (!$stmt) {
            debug_log("Failed to prepare send_reply query: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyiapkan kueri: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("is", $registration_id, $message);
        if ($stmt->execute()) {
            debug_log("Reply sent for registration_id: $registration_id");
            $new_message_id = $stmt->insert_id;
            $sql_new_message = "SELECT sent_at FROM messages WHERE id = ?";
            $stmt_new = $conn->prepare($sql_new_message);
            $stmt_new->bind_param("i", $new_message_id);
            $stmt_new->execute();
            $result_new = $stmt_new->get_result();
            $new_message = $result_new->fetch_assoc();
            echo json_encode([
                'status' => 'success',
                'message' => 'Balasan berhasil dikirim',
                'data' => [
                    'id' => $new_message_id,
                    'registration_id' => $registration_id,
                    'subject' => 'Balasan Admin',
                    'message' => $message,
                    'sender' => 'Admin',
                    'is_read' => 0,
                    'sent_at' => $new_message['sent_at']
                ]
            ]);
            $stmt_new->close();
        } else {
            debug_log("Send_reply query failed: " . $stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengirim balasan: ' . $stmt->error]);
        }
        $stmt->close();
        exit;
    }

    // Filter messages by student
    if ($_POST['action'] === 'filter_messages') {
        debug_log("Processing filter_messages action for registration_id: " . ($_POST['registration_id'] ?? 'all'));
        $registration_id = $_POST['registration_id'] === 'all' ? null : (int)$_POST['registration_id'];

        $sql = "SELECT m.id, m.registration_id, m.subject, m.message, m.sender, m.is_read, m.sent_at, r.full_name, r.email
                FROM messages m
                JOIN registrations r ON m.registration_id = r.id";
        if ($registration_id) {
            $sql .= " WHERE m.registration_id = ?";
        }
        $sql .= " ORDER BY m.sent_at DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            debug_log("Failed to prepare filter_messages query: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyiapkan kueri: ' . $conn->error]);
            exit;
        }
        if ($registration_id) {
            $stmt->bind_param("i", $registration_id);
        }
        if (!$stmt->execute()) {
            debug_log("Filter_messages query failed: " . $stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menjalankan kueri: ' . $stmt->error]);
            $stmt->close();
            exit;
        }
        $result = $stmt->get_result();
        $messages = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        debug_log("Fetched " . count($messages) . " messages");
        echo json_encode(['status' => 'success', 'data' => $messages]);
        exit;
    }

    // Get conversation history
    if ($_POST['action'] === 'get_conversation') {
        debug_log("Processing get_conversation action for registration_id: " . ($_POST['registration_id'] ?? 'not set'));
        $registration_id = (int)($_POST['registration_id'] ?? 0);

        if ($registration_id <= 0) {
            debug_log("Invalid registration_id");
            echo json_encode(['status' => 'error', 'message' => 'ID registrasi tidak valid']);
            exit;
        }

        $sql = "SELECT m.id, m.subject, m.message, m.sender, m.is_read, m.sent_at
                FROM messages m
                WHERE m.registration_id = ?
                ORDER BY m.sent_at ASC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            debug_log("Failed to prepare get_conversation query: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyiapkan kueri: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $registration_id);
        if (!$stmt->execute()) {
            debug_log("Get_conversation query failed: " . $stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menjalankan kueri: ' . $stmt->error]);
            $stmt->close();
            exit;
        }
        $result = $stmt->get_result();
        $messages = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        debug_log("Fetched " . count($messages) . " conversation messages");
        echo json_encode(['status' => 'success', 'data' => $messages]);
        exit;
    }

    // Fallback for invalid actions
    debug_log("Invalid AJAX action: " . ($_POST['action'] ?? 'not set'));
    echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid']);
    exit;
}

// Fetch all messages for initial display
$sql_messages = "SELECT m.id, m.registration_id, m.subject, m.message, m.sender, m.is_read, m.sent_at, r.full_name, r.email
                 FROM messages m
                 JOIN registrations r ON m.registration_id = r.id
                 ORDER BY m.sent_at DESC";
$result_messages = $conn->query($sql_messages);
if (!$result_messages) {
    debug_log("Messages query failed: " . $conn->error);
    $_SESSION['error'] = "Gagal mengambil pesan.";
    $conn->close();
    header("Location: admin_dashboard.php");
    exit;
}
$messages = $result_messages->fetch_all(MYSQLI_ASSOC);

// Fetch all students for filter dropdown
$sql_students = "SELECT id, full_name FROM registrations ORDER BY full_name";
$result_students = $conn->query($sql_students);
if (!$result_students) {
    debug_log("Students query failed: " . $conn->error);
    $_SESSION['error'] = "Gagal mengambil data siswa.";
    $conn->close();
    header("Location: admin_dashboard.php");
    exit;
}
$students = $result_students->fetch_all(MYSQLI_ASSOC);

// Count unread messages
$sql_unread_count = "SELECT COUNT(*) as count FROM messages WHERE is_read = 0 AND sender != 'Admin'";
$result_unread = $conn->query($sql_unread_count);
$unread_count = $result_unread ? $result_unread->fetch_assoc()['count'] : 0;

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
    <title>Inbox - STM Gotham City</title>
    <link rel="icon" href="https://cdn.pixabay.com/photo/2018/10/05/21/29/bat-3726896_1280.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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

        .nav-bar {
            background: rgba(30, 41, 59, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .dashboard-card {
            background: linear-gradient(145deg, #1e293b, #1a2436);
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            padding: 1.5rem;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 150%;
            height: 150%;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%) scale(0);
            opacity: 0;
            transition: transform 0.6s, opacity 0.6s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.4);
        }

        .btn-primary:hover::after {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #4b5563, #374151);
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(75, 85, 99, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.3);
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            background: #1e293b;
            border-radius: 0.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 20;
            overflow: hidden;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(75, 85, 99, 0.2);
            max-height: 300px;
            overflow-y: auto;
        }

        .dropdown-menu.show {
            display: block;
            animation: fadeIn 0.2s ease-out;
        }

        .dropdown-item {
            display: block;
            padding: 0.75rem 1rem;
            color: #e2e8f0;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .dropdown-item:hover {
            background: #2d3748;
            border-left-color: #3b82f6;
            padding-left: 1.25rem;
        }

        .loading-spinner {
            border: 4px solid rgba(59, 130, 246, 0.3);
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        .message-card {
            background: rgba(30, 41, 59, 0.7);
            border-radius: 0.75rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(71, 85, 105, 0.3);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .message-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .message-card.unread {
            border-left: 4px solid #f59e0b;
        }

        .message-card.read {
            border-left: 4px solid transparent;
        }

        .conversation-container {
            height: 400px;
            overflow-y: auto;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 0.75rem;
            border: 1px solid rgba(71, 85, 105, 0.3);
        }

        .message-bubble {
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 1rem;
            max-width: 80%;
            position: relative;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .message-student {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.3);
            margin-right: auto;
            border-bottom-left-radius: 0.25rem;
        }

        .message-admin {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            margin-left: auto;
            border-bottom-right-radius: 0.25rem;
        }

        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }

        .search-input {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(71, 85, 105, 0.5);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            color: #e2e8f0;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            background: rgba(30, 41, 59, 0.8);
            border-color: rgba(59, 130, 246, 0.5);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }

        .tab-button {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .tab-button::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: #3b82f6;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .tab-button:hover::after {
            width: 30%;
        }

        .tab-button.active {
            color: #fff;
        }

        .tab-button.active::after {
            width: 80%;
        }

        .conversation-header {
            background: linear-gradient(90deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.9) 100%);
            border-bottom: 1px solid rgba(71, 85, 105, 0.3);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        .badge {
            font-size: 0.65rem;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-weight: 600;
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .pulse-animation {
            position: relative;
        }

        .pulse-animation::before {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #f59e0b;
            top: 0;
            right: 0;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(0.8);
                opacity: 0.8;
            }
            50% {
                transform: scale(1.2);
                opacity: 1;
            }
            100% {
                transform: scale(0.8);
                opacity: 0.8;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-18rem);
                position: fixed;
                height: 100vh;
            }
            
            .sidebar.hidden {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100%;
            }
            
            .message-bubble {
                max-width: 90%;
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
                    <a href="admin_dashboard.php" class="sidebar-link">
                        <i class="fas fa-tachometer-alt w-5 text-blue-400 mr-3"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="inbox.php" class="sidebar-link active">
                        <i class="fas fa-envelope w-5 text-blue-400 mr-3"></i>
                        <span>Inbox</span>
                        <?php if($unread_count > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
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
            <nav class="nav-bar p-4 sticky top-0 z-20">
                <div class="container mx-auto flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <button id="toggle-sidebar" class="p-2 rounded-lg hover:bg-gray-700 transition-all">
                            <i class="fas fa-bars text-xl text-white"></i>
                        </button>
                        <div>
                            <h2 class="text-xl font-bold">Manajemen Pesan</h2>
                            <p class="text-sm text-gray-400">
                                <span id="current-date"></span>
                            </p>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Inbox Content -->
            <div class="container mx-auto p-6">
                <section id="inbox">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-2xl font-bold">Pesan Masuk</h3>
                            <p class="text-gray-400 text-sm">Kelola komunikasi dengan siswa secara efisien</p>
                        </div>
                        <div class="flex space-x-3 items-center">
                            <div class="relative">
                                <button id="filter-btn" class="px-4 py-2 btn-secondary rounded-lg flex items-center">
                                    <i class="fas fa-filter mr-2"></i> Filter by Siswa
                                </button>
                                <div id="filter-menu" class="dropdown-menu mt-2">
                                    <a href="#" class="dropdown-item" data-registration-id="all">Semua Siswa</a>
                                    <?php foreach ($students as $student): ?>
                                        <a href="#" class="dropdown-item" data-registration-id="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['full_name']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button id="refresh-btn" class="px-4 py-2 btn-success rounded-lg flex items-center">
                                <i class="fas fa-sync-alt mr-2"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="mb-6">
                        <div class="flex border-b border-gray-700">
                            <button class="tab-button active" data-tab="message-list">Daftar Pesan</button>
                            <button class="tab-button" data-tab="conversation">Percakapan</button>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div id="message-list" class="tab-content active">
                        <div class="mb-6 relative">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="search-messages" class="search-input w-full" placeholder="Cari berdasarkan nama atau email...">
                        </div>
                        <div id="messages-container">
                            <?php if (empty($messages)): ?>
                                <div class="dashboard-card text-center py-8">
                                    <i class="fas fa-envelope-open-text text-3xl text-gray-500 mb-2"></i>
                                    <p class="text-gray-400">Tidak ada pesan</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <div class="message-card mb-4 <?php echo $msg['is_read'] ? 'read' : 'unread'; ?>" data-registration-id="<?php echo $msg['registration_id']; ?>" data-message-id="<?php echo $msg['id']; ?>">
                                        <div class="p-4 cursor-pointer" onclick="viewConversation(<?php echo $msg['registration_id']; ?>, '<?php echo addslashes($msg['full_name']); ?>')">
                                            <div class="flex justify-between items-start">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-10 h-10 rounded-full bg-blue-500 bg-opacity-20 flex items-center justify-center">
                                                        <i class="fas fa-user text-blue-500"></i>
                                                    </div>
                                                    <div>
                                                        <h4 class="font-semibold"><?php echo htmlspecialchars($msg['full_name']); ?></h4>
                                                        <p class="text-sm text-gray-400"><?php echo htmlspecialchars($msg['email']); ?></p>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-xs text-gray-400"><?php echo date('d/m/Y H:i', strtotime($msg['sent_at'])); ?></p>
                                                    <span class="badge <?php echo $msg['is_read'] ? 'badge-success' : 'badge-warning'; ?>">
                                                        <?php echo $msg['is_read'] ? 'Dibaca' : 'Belum Dibaca'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <p class="mt-2 text-sm text-gray-300"><?php echo htmlspecialchars($msg['subject']); ?></p>
                                            <p class="mt-1 text-sm text-gray-400 line-clamp-2"><?php echo htmlspecialchars($msg['message']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="conversation" class="tab-content">
                        <div class="dashboard-card">
                            <div class="conversation-header p-4 flex justify-between items-center">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-full bg-blue-500 bg-opacity-20 flex items-center justify-center">
                                        <i class="fas fa-user text-blue-500"></i>
                                    </div>
                                    <div>
                                        <h4 id="conversation-student" class="font-semibold">Pilih Siswa</h4>
                                        <p class="text-sm text-gray-400">Klik pesan untuk melihat percakapan</p>
                                    </div>
                                </div>
                                <button onclick="switchTab('message-list')" class="btn-secondary px-4 py-2 rounded-lg">
                                    <i class="fas fa-arrow-left mr-2"></i> Kembali
                                </button>
                            </div>
                            <div class="conversation-container" id="conversation-messages">
                                <div class="text-center py-8 text-gray-400">
                                    <i class="fas fa-comments text-3xl mb-2"></i>
                                    <p>Pilih pesan dari daftar untuk memulai percakapan</p>
                                </div>
                            </div>
                            <div class="p-4 border-t border-gray-700">
                                <form id="replyForm" class="flex space-x-3">
                                    <input type="hidden" id="reply_registration_id" name="registration_id">
                                    <textarea id="reply_message" name="message" class="flex-1 bg-gray-800 text-gray-200 border border-gray-600 rounded-lg p-3 focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 resize-none" rows="2" placeholder="Ketik balasan..." required></textarea>
                                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg flex items-center">
                                        <i class="fas fa-paper-plane mr-2"></i> Kirim
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Footer -->
            <footer class="bg-gray-900 p-6 text-center text-gray-400">
                <p>© 2025 STM Gotham City. Hak Cipta Dilindungi.</p>
            </footer>
        </div>
    </div>

    <script>
        // Sidebar Toggle
        const toggleSidebar = document.getElementById('toggle-sidebar');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');

        toggleSidebar.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('ml-72');
            mainContent.classList.toggle('ml-0');
        });

        // Filter Menu Toggle
        const filterBtn = document.getElementById('filter-btn');
        const filterMenu = document.getElementById('filter-menu');

        filterBtn.addEventListener('click', () => {
            filterMenu.classList.toggle('show');
        });

        document.addEventListener('click', (e) => {
            if (!filterBtn.contains(e.target) && !filterMenu.contains(e.target)) {
                filterMenu.classList.remove('show');
            }
        });

        // Tab Switching
        function switchTab(tabId) {
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.addEventListener('click', () => {
                switchTab(btn.getAttribute('data-tab'));
            });
        });

        // Dynamic Date
        const currentDate = document.getElementById('current-date');
        const now = new Date();
        currentDate.textContent = now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        // Error Handling Function
        function handleAjaxError(error, action) {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: `Terjadi kesalahan saat ${action}: ${error.message}`,
                confirmButtonColor: '#3b82f6'
            });
            console.error(`Error during ${action}:`, error);
        }

        // Filter Messages
        document.querySelectorAll('#filter-menu .dropdown-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const registration_id = item.getAttribute('data-registration-id');
                filterMenu.classList.remove('show');

                Swal.fire({
                    title: 'Memuat Data...',
                    html: '<div class="loading-spinner"></div>',
                    allowOutsideClick: false,
                    showConfirmButton: false
                });

                fetch('inbox.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=filter_messages&registration_id=${encodeURIComponent(registration_id)}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    Swal.close();
                    if (data.status === 'success') {
                        const container = document.getElementById('messages-container');
                        container.innerHTML = '';
                        if (data.data.length > 0) {
                            data.data.forEach(msg => {
                                const card = document.createElement('div');
                                card.className = `message-card mb-4 ${msg.is_read ? 'read' : 'unread'}`;
                                card.dataset.registrationId = msg.registration_id;
                                card.dataset.messageId = msg.id;
                                card.innerHTML = `
                                    <div class="p-4 cursor-pointer" onclick="viewConversation(${msg.registration_id}, '${msg.full_name.replace(/'/g, "\\'")}')">
                                        <div class="flex justify-between items-start">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-10 h-10 rounded-full bg-blue-500 bg-opacity-20 flex items-center justify-center">
                                                    <i class="fas fa-user text-blue-500"></i>
                                                </div>
                                                <div>
                                                    <h4 class="font-semibold">${msg.full_name}</h4>
                                                    <p class="text-sm text-gray-400">${msg.email}</p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-xs text-gray-400">${new Date(msg.sent_at).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                                                <span class="badge ${msg.is_read ? 'badge-success' : 'badge-warning'}">${msg.is_read ? 'Dibaca' : 'Belum Dibaca'}</span>
                                            </div>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-300">${msg.subject}</p>
                                        <p class="mt-1 text-sm text-gray-400 line-clamp-2">${msg.message}</p>
                                    </div>
                                `;
                                container.appendChild(card);
                            });
                        } else {
                            container.innerHTML = `
                                <div class="dashboard-card text-center py-8">
                                    <i class="fas fa-envelope-open-text text-3xl text-gray-500 mb-2"></i>
                                    <p class="text-gray-400">Tidak ada pesan</p>
                                </div>
                            `;
                        }
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: 'Data pesan telah diperbarui',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        handleAjaxError(new Error(data.message), 'memfilter pesan');
                    }
                })
                .catch(error => {
                    handleAjaxError(error, 'memfilter pesan');
                });
            });
        });

        // View Conversation
        function viewConversation(registrationId, studentName) {
            switchTab('conversation');
            document.getElementById('conversation-student').textContent = studentName;
            document.getElementById('reply_registration_id').value = registrationId;

            Swal.fire({
                title: 'Memuat Percakapan...',
                html: '<div class="loading-spinner"></div>',
                allowOutsideClick: false,
                showConfirmButton: false
            });

            fetch('inbox.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_conversation&registration_id=${registrationId}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                Swal.close();
                const container = document.getElementById('conversation-messages');
                container.innerHTML = '';
                if (data.status === 'success' && data.data.length > 0) {
                    data.data.forEach(msg => {
                        const isAdmin = msg.sender === 'Admin';
                        const bubble = document.createElement('div');
                        bubble.className = `message-bubble ${isAdmin ? 'message-admin' : 'message-student'}`;
                        bubble.innerHTML = `
                            <p class="text-sm">${msg.message}</p>
                            <p class="message-time">${new Date(msg.sent_at).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                        `;
                        container.appendChild(bubble);

                        if (!msg.is_read && !isAdmin) {
                            fetch('inbox.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=mark_read&message_id=${msg.id}`
                            }).catch(error => {
                                console.error('Error marking message as read:', error);
                            });
                        }
                    });
                    container.scrollTop = container.scrollHeight;
                } else {
                    container.innerHTML = `
                        <div class="text-center py-8 text-gray-400">
                            <i class="fas fa-comments text-3xl mb-2"></i>
                            <p>Tidak ada pesan dalam percakapan ini</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                handleAjaxError(error, 'memuat percakapan');
            });
        }

        // Reply Form Submission
        const replyForm = document.getElementById('replyForm');
        if (replyForm) {
            replyForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(replyForm);
                formData.append('action', 'send_reply');

                Swal.fire({
                    title: 'Mengirim Balasan...',
                    html: '<div class="loading-spinner"></div>',
                    allowOutsideClick: false,
                    showConfirmButton: false
                });

                fetch('inbox.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    Swal.close();
                    if (data.status === 'success') {
                        const container = document.getElementById('conversation-messages');
                        const bubble = document.createElement('div');
                        bubble.className = 'message-bubble message-admin';
                        bubble.innerHTML = `
                            <p class="text-sm">${data.data.message}</p>
                            <p class="message-time">${new Date(data.data.sent_at).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                        `;
                        container.appendChild(bubble);
                        container.scrollTop = container.scrollHeight;
                        replyForm.reset();
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        handleAjaxError(new Error(data.message), 'mengirim balasan');
                    }
                })
                .catch(error => {
                    handleAjaxError(error, 'mengirim balasan');
                });
            });
        }

        // Refresh Button
        document.getElementById('refresh-btn').addEventListener('click', () => {
            Swal.fire({
                title: 'Memuat Data...',
                html: '<div class="loading-spinner"></div>',
                allowOutsideClick: false,
                showConfirmButton: false
            });

            fetch('inbox.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=filter_messages&registration_id=all'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                Swal.close();
                if (data.status === 'success') {
                    const container = document.getElementById('messages-container');
                    container.innerHTML = '';
                    if (data.data.length > 0) {
                        data.data.forEach(msg => {
                            const card = document.createElement('div');
                            card.className = `message-card mb-4 ${msg.is_read ? 'read' : 'unread'}`;
                            card.dataset.registrationId = msg.registration_id;
                            card.dataset.messageId = msg.id;
                            card.innerHTML = `
                                <div class="p-4 cursor-pointer" onclick="viewConversation(${msg.registration_id}, '${msg.full_name.replace(/'/g, "\\'")}')">
                                    <div class="flex justify-between items-start">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 rounded-full bg-blue-500 bg-opacity-20 flex items-center justify-center">
                                                <i class="fas fa-user text-blue-500"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-semibold">${msg.full_name}</h4>
                                                <p class="text-sm text-gray-400">${msg.email}</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs text-gray-400">${new Date(msg.sent_at).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                                            <span class="badge ${msg.is_read ? 'badge-success' : 'badge-warning'}">${msg.is_read ? 'Dibaca' : 'Belum Dibaca'}</span>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-300">${msg.subject}</p>
                                    <p class="mt-1 text-sm text-gray-400 line-clamp-2">${msg.message}</p>
                                </div>
                            `;
                            container.appendChild(card);
                        });
                    } else {
                        container.innerHTML = `
                            <div class="dashboard-card text-center py-8">
                                <i class="fas fa-envelope-open-text text-3xl text-gray-500 mb-2"></i>
                                <p class="text-gray-400">Tidak ada pesan</p>
                            </div>
                        `;
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: 'Data pesan telah diperbarui',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    handleAjaxError(new Error(data.message), 'merefresh pesan');
                }
            })
            .catch(error => {
                handleAjaxError(error, 'merefresh pesan');
            });
        });

        // Search Messages
        const searchInput = document.getElementById('search-messages');
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase();
            const cards = document.querySelectorAll('#messages-container .message-card');
            cards.forEach(card => {
                const name = card.querySelector('h4').textContent.toLowerCase();
                const email = card.querySelector('p.text-gray-400').textContent.toLowerCase();
                card.style.display = (name.includes(query) || email.includes(query)) ? '' : 'none';
            });
        });
    </script>
</body>
</html>