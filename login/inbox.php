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
    $_SESSION['error'] = "Koneksi database gagal.";
    header("Location: login.php");
    exit;
}
debug_log("Database connected successfully");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

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
            echo json_encode(['status' => 'success', 'message' => 'Balasan berhasil dikirim']);
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

        .message-container {
            background: rgba(15, 23, 42, 0.8);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(71, 85, 105, 0.5);
        }

        .message-student {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
        }

        .message-admin {
            background: rgba(16, 185, 129, 0.1);
            border-left: 4px solid #10b981;
        }

        .message-status-unread {
            color: #f59e0b;
        }

        .message-status-read {
            color: #10b981;
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
                    <a href="admin_dashboard.php" class="sidebar-link">
                        <i class="fas fa-tachometer-alt w-5 text-blue-400 mr-3"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="inbox.php" class="sidebar-link active">
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
                            <h2 class="text-lg font-bold">Inbox Admin</h2>
                            <p class="text-sm text-gray-400">Selamat datang, <?php echo htmlspecialchars($_SESSION['admin_id']); ?></p>
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
                            <p class="text-gray-400 text-sm">Kelola pesan dari siswa</p>
                        </div>
                        <div class="flex space-x-3">
                            <div class="relative">
                                <button id="filter-btn" class="px-4 py-2 bg-gray-700 rounded-lg flex items-center hover:bg-gray-600">
                                    <i class="fas fa-filter mr-2"></i> Filter by Siswa
                                </button>
                                <div id="filter-menu" class="dropdown-menu">
                                    <a href="#" class="dropdown-item" data-registration-id="all">Semua Siswa</a>
                                    <?php foreach ($students as $student): ?>
                                        <a href="#" class="dropdown-item" data-registration-id="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['full_name']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button id="refresh-btn" class="px-4 py-2 bg-green-700 hover:bg-green-800 rounded-lg flex items-center text-white transition">
                                <i class="fas fa-sync-alt mr-2"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <div class="table-container mb-6">
                        <table class="data-table w-full">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama Siswa</th>
                                    <th>Email</th>
                                    <th>Subjek</th>
                                    <th>Pengirim</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="messages-table">
                                <?php if (empty($messages)): ?>
                                    <tr><td colspan="8" class="text-center">Tidak ada pesan</td></tr>
                                <?php else: ?>
                                    <?php $nomor = 1; foreach ($messages as $msg): ?>
                                        <tr>
                                            <td><?php echo $nomor++; ?></td>
                                            <td><?php echo htmlspecialchars($msg['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($msg['email']); ?></td>
                                            <td><?php echo htmlspecialchars($msg['subject']); ?></td>
                                            <td><?php echo $msg['sender']; ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($msg['sent_at'])); ?></td>
                                            <td>
                                                <span class="px-2 py-1 rounded-full text-xs <?php echo $msg['is_read'] ? 'bg-green-900 text-green-400' : 'bg-yellow-900 text-yellow-300'; ?>">
                                                    <?php echo $msg['is_read'] ? 'Dibaca' : 'Belum Dibaca'; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="flex justify-center space-x-2">
                                                    <button onclick="viewMessage(<?php echo $msg['id']; ?>, '<?php echo addslashes($msg['full_name']); ?>', '<?php echo addslashes($msg['subject']); ?>', '<?php echo addslashes($msg['message']); ?>', <?php echo $msg['registration_id']; ?>)" class="action-btn bg-blue-900 text-blue-400 hover:bg-blue-800" title="Lihat Pesan">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Reply Form (Hidden by default) -->
                    <div id="reply-form" class="dashboard-card hidden">
                        <div class="flex items-center space-x-4 mb-6">
                            <div class="w-10 h-10 rounded-full bg-blue-500 bg-opacity-20 flex items-center justify-center">
                                <i class="fas fa-reply text-blue-500"></i>
                            </div>
                            <h2 class="text-xl font-semibold">Balas Pesan</h2>
                        </div>
                        <form id="replyForm" class="space-y-6">
                            <input type="hidden" id="reply_registration_id" name="registration_id">
                            <div>
                                <label for="reply_message" class="block text-sm font-medium text-gray-300 mb-2">Pesan Balasan</label>
                                <textarea id="reply_message" name="message" class="w-full bg-gray-800 text-gray-200 border border-gray-600 rounded-lg p-3 focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" rows="6" required></textarea>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg flex items-center text-white transition">
                                    <i class="fas fa-paper-plane mr-2"></i> Kirim Balasan
                                </button>
                            </div>
                        </form>
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

        // Close filter menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!filterBtn.contains(e.target) && !filterMenu.contains(e.target)) {
                filterMenu.classList.remove('show');
            }
        });

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
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.status === 'success') {
                        const tbody = document.getElementById('messages-table');
                        tbody.innerHTML = '';
                        if (data.data.length > 0) {
                            let nomor = 1;
                            data.data.forEach(msg => {
                                const statusClass = msg.is_read ? 'bg-green-900 text-green-400' : 'bg-yellow-900 text-yellow-300';
                                const statusText = msg.is_read ? 'Dibaca' : 'Belum Dibaca';
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td>${nomor++}</td>
                                    <td>${msg.full_name}</td>
                                    <td>${msg.email}</td>
                                    <td>${msg.subject}</td>
                                    <td>${msg.sender}</td>
                                    <td>${new Date(msg.sent_at).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</td>
                                    <td><span class="px-2 py-1 rounded-full text-xs ${statusClass}">${statusText}</span></td>
                                    <td class="text-center">
                                        <div class="flex justify-center space-x-2">
                                            <button onclick="viewMessage(${msg.id}, '${msg.full_name.replace(/'/g, "\\'")}', '${msg.subject.replace(/'/g, "\\'")}', '${msg.message.replace(/'/g, "\\'")}', ${msg.registration_id})" class="action-btn bg-blue-900 text-blue-400 hover:bg-blue-800" title="Lihat Pesan">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                `;
                                tbody.appendChild(tr);
                            });
                        } else {
                            tbody.innerHTML = '<tr><td colspan="8" class="text-center">Tidak ada pesan</td></tr>';
                        }
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: 'Data pesan telah diperbarui',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: data.message,
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

        // View Message and Show Reply Form
        function viewMessage(messageId, fullName, subject, message, registrationId) {
            Swal.fire({
                title: `Pesan dari ${fullName}`,
                html: `
                    <div class="text-left">
                        <p><strong>Subjek:</strong> ${subject}</p>
                        <p><strong>Pesan:</strong> ${message}</p>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Balas',
                showCancelButton: true,
                cancelButtonText: 'Tutup',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mark message as read
                    fetch('inbox.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=mark_read&message_id=${messageId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Show reply form
                            const replyForm = document.getElementById('reply-form');
                            document.getElementById('reply_registration_id').value = registrationId;
                            replyForm.classList.remove('hidden');
                            window.scrollTo({ top: replyForm.offsetTop - 80, behavior: 'smooth' });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: data.message,
                                confirmButtonColor: '#3b82f6'
                            });
                        }
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
                .then(response => response.json())
                .then(data => {
                    Swal.fire({
                        icon: data.status,
                        title: data.status === 'success' ? 'Berhasil!' : 'Gagal',
                        text: data.message,
                        confirmButtonColor: '#3b82f6'
                    }).then(() => {
                        if (data.status === 'success') {
                            replyForm.reset();
                            document.getElementById('reply-form').classList.add('hidden');
                            location.reload();
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
            });
        }

        // Refresh Button
        document.getElementById('refresh-btn').addEventListener('click', () => {
            location.reload();
        });
    </script>
</body>
</html>