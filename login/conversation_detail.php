<?php
session_start();

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Disable display errors (enable temporarily for debugging if needed)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

// Check if admin is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Validate GET parameters
if (!isset($_GET['student_id']) || !is_numeric($_GET['student_id']) || !isset($_GET['subject'])) {
    header("Location: inbox.php");
    exit;
}

$student_id = (int)$_GET['student_id'];
$subject = urldecode($_GET['subject']);

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "stm_gotham";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5); // Set timeout to 5 seconds
if ($conn->connect_error) {
    error_log("Koneksi database gagal: " . $conn->connect_error, 3, __DIR__ . '/error.log');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal']);
        exit;
    }
    die("Koneksi database gagal");
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Log POST data for debugging
    error_log("POST data: " . print_r($_POST, true), 3, __DIR__ . '/debug.log');

    $response = ['status' => 'error', 'message' => 'Aksi tidak valid'];

    // Handle send_reply action
    if (isset($_POST['action']) && $_POST['action'] === 'send_reply') {
        if (!isset($_POST['message_id']) || !is_numeric($_POST['message_id'])) {
            $response['message'] = 'ID pesan tidak valid';
            echo json_encode($response);
            exit;
        }

        if (!isset($_POST['reply']) || empty(trim($_POST['reply']))) {
            $response['message'] = 'Balasan tidak boleh kosong';
            echo json_encode($response);
            exit;
        }

        $message_id = (int)$_POST['message_id'];
        $reply = trim($_POST['reply']);

        // Verify message_id
        $sql_verify = "SELECT id FROM messages WHERE id = ? AND sender_type = 'student'";
        $stmt_verify = $conn->prepare($sql_verify);
        $stmt_verify->bind_param("i", $message_id);
        $stmt_verify->execute();
        $result_verify = $stmt_verify->get_result();
        if ($result_verify->num_rows === 0) {
            $response['message'] = 'Pesan tidak ditemukan atau bukan dari siswa';
            echo json_encode($response);
            $stmt_verify->close();
            exit;
        }
        $stmt_verify->close();

        // Start transaction
        $conn->begin_transaction();
        try {
            // Insert admin reply
            $sql_reply = "INSERT INTO messages (student_id, subject, message, status, sender_type, created_at, updated_at) 
                          VALUES (?, ?, ?, 'replied', 'admin', NOW(), NOW())";
            $stmt_reply = $conn->prepare($sql_reply);
            $stmt_reply->bind_param("iss", $student_id, $subject, $reply);

            // Update original message status
            $sql_update = "UPDATE messages SET status = 'replied', updated_at = NOW() WHERE id = ? AND sender_type = 'student'";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("i", $message_id);

            if ($stmt_reply->execute() && $stmt_update->execute()) {
                $conn->commit();
                $response = ['status' => 'success', 'message' => 'Balasan berhasil dikirim'];
            } else {
                throw new Exception("Gagal mengeksekusi query");
            }

            $stmt_reply->close();
            $stmt_update->close();
        } catch (Exception $e) {
            $conn->rollback();
            $response['message'] = 'Gagal menyimpan balasan';
            error_log("Transaction error: " . $e->getMessage(), 3, __DIR__ . '/error.log');
        }

        echo json_encode($response);
        exit;
    }

    // Handle delete_chat action
    if (isset($_POST['action']) && $_POST['action'] === 'delete_chat') {
        $sql_delete = "DELETE FROM messages WHERE student_id = ? AND subject = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("is", $student_id, $subject);

        if ($stmt_delete->execute()) {
            $response = ['status' => 'success', 'message' => 'Percakapan berhasil dihapus', 'redirect' => 'inbox.php'];
        } else {
            $response['message'] = 'Gagal menghapus percakapan';
            error_log("Delete error: " . $stmt_delete->error, 3, __DIR__ . '/error.log');
        }

        $stmt_delete->close();
        echo json_encode($response);
        exit;
    }

    echo json_encode($response);
    exit;
}

// Fetch conversation history
$sql_conversation = "SELECT 
                    m.id, m.student_id, m.subject, m.message, m.status, m.sender_type, m.created_at,
                    r.full_name
                  FROM messages m 
                  JOIN registrations r ON m.student_id = r.id 
                  WHERE m.student_id = ? AND m.subject = ?
                  ORDER BY m.created_at ASC";
$stmt = $conn->prepare($sql_conversation);
$stmt->bind_param("is", $student_id, $subject);
$stmt->execute();
$result_conversation = $stmt->get_result();
$messages = $result_conversation->fetch_all(MYSQLI_ASSOC);
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
    <title>Detail Percakapan - STM Gotham City</title>
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
            background: #1e293b;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .timeline-container {
            background: #1e293b;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            position: relative;
        }

        .timeline-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            width: 4px;
            height: 100%;
            background: #374151;
            transform: translateX(-50%);
        }

        .timeline-item {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            position: relative;
        }

        .timeline-item.student {
            justify-content: flex-start;
        }

        .timeline-item.admin {
            justify-content: flex-end;
        }

        .timeline-content {
            background: #2d3748;
            border-radius: 0.5rem;
            padding: 1.5rem;
            width: 45%;
            position: relative;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .timeline-content.student {
            margin-right: auto;
        }

        .timeline-content.admin {
            margin-left: auto;
        }

        .timeline-content::before {
            content: '';
            position: absolute;
            top: 50%;
            width: 0;
            height: 0;
            border: 10px solid transparent;
        }

        .timeline-content.student::before {
            right: -20px;
            border-left-color: #2d3748;
            transform: translateY(-50%);
        }

        .timeline-content.admin::before {
            left: -20px;
            border-right-color: #2d3748;
            transform: translateY(-50%);
        }

        .timeline-dot {
            width: 16px;
            height: 16px;
            background: #3b82f6;
            border-radius: 50%;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1;
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
            .timeline-container::before {
                left: 20px;
            }
            .timeline-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .timeline-content {
                width: 80%;
                margin: 1rem 0;
            }
            .timeline-content.student, .timeline-content.admin {
                margin-left: 40px;
            }
            .timeline-content.student::before, .timeline-content.admin::before {
                left: -20px;
                border-right-color: #2d3748;
                border-left-color: transparent;
            }
            .timeline-dot {
                left: 20px;
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
                            <h2 class="text-lg font-bold">Detail Percakapan</h2>
                            <p class="text-sm text-gray-400">Selamat datang, <?php echo htmlspecialchars($_SESSION['admin_id']); ?></p>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Conversation Content -->
            <div class="container mx-auto p-6">
                <section id="conversation">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-2xl font-bold">Percakapan dengan <?php echo htmlspecialchars($messages[0]['full_name'] ?? 'Pengguna'); ?></h3>
                            <p class="text-gray-400 text-sm">Subjek: <?php echo htmlspecialchars($subject); ?></p>
                        </div>
                        <div class="flex space-x-2">
                            <button id="delete-chat" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg flex items-center">
                                <i class="fas fa-trash-alt mr-2"></i> Hapus Percakapan
                            </button>
                            <a href="inbox.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg flex items-center">
                                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Inbox
                            </a>
                        </div>
                    </div>
                    
                    <!-- Timeline -->
                    <div class="timeline-container">
                        <?php if (empty($messages)): ?>
                            <p class="text-center text-gray-400">Tidak ada pesan dalam percakapan ini.</p>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="timeline-item <?php echo $message['sender_type'] === 'student' ? 'student' : 'admin'; ?>">
                                    <div class="timeline-content <?php echo $message['sender_type'] === 'student' ? 'student' : 'admin'; ?>">
                                        <div class="timeline-dot"></div>
                                        <p class="font-semibold">
                                            <?php echo $message['sender_type'] === 'student' ? htmlspecialchars($message['full_name']) : 'Anda (Admin)'; ?>
                                        </p>
                                        <p class="text-gray-300"><?php echo htmlspecialchars($message['message']); ?></p>
                                        <p class="text-sm text-gray-400 mt-2"><?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Reply Form -->
                    <?php if (!empty($messages)): ?>
                    <div class="mt-8">
                        <h4 class="text-lg font-semibold mb-4">Balas Pesan</h4>
                        <form id="reply-form" method="POST">
                            <input type="hidden" name="action" value="send_reply">
                            <input type="hidden" name="message_id" value="<?php echo htmlspecialchars($messages[0]['id']); ?>">
                            <div class "mb-4">
                                <label for="reply-subject" class="block text-sm font-medium text-gray-300 mb-2">Subjek</label>
                                <input type="text" id="reply-subject" name="subject" value="Re: <?php echo htmlspecialchars($subject); ?>" class="w-full p-2 bg-gray-800 border border-gray-600 rounded-lg text-gray-200" readonly>
                            </div>
                            <div class="mb-4">
                                <label for="reply-message" class="block text-sm font-medium text-gray-300 mb-2">Balasan</label>
                                <textarea id="reply-message" name="reply" class="w-full p-2 bg-gray-800 border border-gray-600 rounded-lg text-gray-200" rows="4" required></textarea>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg">Kirim Balasan</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
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

        // Reply Form Submission
        const replyForm = document.getElementById('reply-form');
        if (replyForm) {
            replyForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(replyForm);

                console.log('Reply form data:');
                for (const [key, value] of formData.entries()) {
                    console.log(`${key}: ${value}`);
                }

                Swal.fire({
                    title: 'Mengirim Balasan...',
                    html: '<div class="loading-spinner"></div>',
                    allowOutsideClick: false,
                    showConfirmButton: false
                });

                fetch('', {
                    method: 'POST',
                    body: formData,
                    signal: AbortSignal.timeout(10000) // Timeout after 10 seconds
                })
                .then(response => {
                    console.log('Reply response status:', response.status);
                    console.log('Reply response headers:', [...response.headers.entries()]);
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.log('Reply response text:', text.substring(0, 200));
                            throw new Error(`HTTP error! Status: ${response.status}, Response: ${text.substring(0, 100)}...`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.status || !data.message) {
                        throw new Error('Respons server tidak valid');
                    }
                    Swal.fire({
                        icon: data.status,
                        title: data.status === 'success' ? 'Berhasil!' : 'Gagal',
                        text: data.message,
                        confirmButtonColor: '#3b82f6'
                    }).then(() => {
                        if (data.status === 'success') {
                            window.location.reload();
                        }
                    });
                })
                .catch(error => {
                    console.error('Reply fetch error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Terjadi kesalahan: ' + error.message,
                        confirmButtonColor: '#3b82f6'
                    });
                });
            });
        }

        // Delete Chat Button
        const deleteChatButton = document.getElementById('delete-chat');
        if (deleteChatButton) {
            deleteChatButton.addEventListener('click', () => {
                Swal.fire({
                    title: 'Hapus Percakapan',
                    text: 'Apakah Anda yakin ingin menghapus seluruh percakapan ini? Tindakan ini tidak dapat dibatalkan.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const formData = new FormData();
                        formData.append('action', 'delete_chat');

                        console.log('Delete chat form data:');
                        for (const [key, value] of formData.entries()) {
                            console.log(`${key}: ${value}`);
                        }

                        Swal.fire({
                            title: 'Menghapus Percakapan...',
                            html: '<div class="loading-spinner"></div>',
                            allowOutsideClick: false,
                            showConfirmButton: false
                        });

                        fetch('', {
                            method: 'POST',
                            body: formData,
                            signal: AbortSignal.timeout(10000) // Timeout after 10 seconds
                        })
                        .then(response => {
                            console.log('Delete response status:', response.status);
                            console.log('Delete response headers:', [...response.headers.entries()]);
                            if (!response.ok) {
                                return response.text().then(text => {
                                    console.log('Delete response text:', text.substring(0, 200));
                                    throw new Error(`HTTP error! Status: ${response.status}, Response: ${text.substring(0, 100)}...`);
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (!data.status || !data.message) {
                                throw new Error('Respons server tidak valid');
                            }
                            Swal.fire({
                                icon: data.status,
                                title: data.status === 'success' ? 'Berhasil!' : 'Gagal',
                                text: data.message,
                                confirmButtonColor: '#3b82f6'
                            }).then(() => {
                                if (data.status === 'success' && data.redirect) {
                                    window.location.href = data.redirect;
                                }
                            });
                        })
                        .catch(error => {
                            console.error('Delete fetch error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Terjadi kesalahan: ' + error.message,
                                confirmButtonColor: '#3b82f6'
                            });
                        });
                    }
                });
            });
        }
    </script>
</body>
</html>