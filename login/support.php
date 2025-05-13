<?php
ob_start(); // Start output buffering
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
    error_log("Database connection failed: " . $conn->connect_error);
    $_SESSION['error'] = "Koneksi database gagal.";
    header("Location: login.php");
    exit;
}

// Fetch student data
$email = $_SESSION['email'];
$sql = "SELECT id, full_name, exam_permission FROM registrations WHERE email = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed for SELECT registrations: " . $conn->error);
    $_SESSION['error'] = "Gagal menyiapkan query.";
    $conn->close();
    header("Location: login.php");
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    error_log("No student found for email: " . $email);
    $_SESSION['error'] = "Data siswa tidak ditemukan.";
    $conn->close();
    header("Location: login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $registration_id = $student['id'];

    $response = ['status' => 'error', 'message' => 'Gagal mengirim pesan'];

    // Validate inputs
    if (empty($subject) || empty($message)) {
        $response['message'] = 'Subjek dan pesan tidak boleh kosong';
    } else {
        // Insert message into database
        $sql = "INSERT INTO messages (registration_id, subject, message, sender, is_read, sent_at) VALUES (?, ?, ?, 'Student', 0, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for INSERT messages: " . $conn->error);
            $response['message'] = 'Gagal menyiapkan query.';
        } else {
            $stmt->bind_param("iss", $registration_id, $subject, $message);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Pesan berhasil dikirim ke admin'];
            } else {
                error_log("Execute failed for INSERT messages: " . $stmt->error);
                $response['message'] = 'Gagal menyimpan pesan ke database';
            }
            $stmt->close();
        }
    }

    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

// Fetch message history
$sql = "SELECT id, subject, message, sender, is_read, sent_at 
        FROM messages 
        WHERE registration_id = ? 
        ORDER BY sent_at ASC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed for SELECT messages: " . $conn->error);
    $_SESSION['error'] = "Gagal mengambil riwayat pesan.";
    $conn->close();
    header("Location: login.php");
    exit;
}
$stmt->bind_param("i", $student['id']);
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);
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
    <title>Support - STM Gotham City</title>
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
        
        .input-field {
            transition: all 0.3s ease;
            background: rgba(15, 23, 42, 0.8);
            border: 2px solid rgba(71, 85, 105, 0.8);
            border-radius: 0.5rem;
            padding: 0.75rem 1.25rem;
            color: #e2e8f0;
        }
        
        .input-field:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
            outline: none;
            background: rgba(30, 41, 59, 0.9);
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
        
        .submit-btn {
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
        }
        
        .submit-btn:hover {
            background: linear-gradient(90deg, #2563eb, #3b82f6);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.5);
        }
        
        .logout-btn {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }
        
        .logout-btn:hover {
            background: linear-gradient(90deg, #dc2626, #ef4444);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.5);
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
        
        .message-status-replied {
            color: #3b82f6;
        }
        
        .slide-in {
            animation: slideIn 0.5s forwards;
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
                        <a href="student_dashboard.php" class="flex items-center space-x-3 text-gray-300 hover:text-blue-400 hover:bg-blue-900 hover:bg-opacity-20 rounded-lg px-4 py-3 transition-all duration-300">
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
                        <a href="support.php" class="flex items-center space-x-3 text-blue-400 bg-blue-900 bg-opacity-30 rounded-lg px-4 py-3 transition-all duration-300">
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
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                    <div>
                        <h1 class="text-3xl font-bold mb-2 slide-in">Hubungi Admin</h1>
                        <p class="text-gray-400 slide-in">Kirim pesan atau pertanyaan Anda kepada admin</p>
                    </div>
                </div>

                <!-- Support Form -->
                <div class="card p-6 mb-6 slide-in">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-10 h-10 rounded-full bg-blue-500 bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-envelope text-blue-500"></i>
                        </div>
                        <h2 class="text-xl font-semibold">Formulir Kontak</h2>
                    </div>

                    <form id="supportForm" method="POST" class="space-y-6">
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-300 mb-2">Subjek</label>
                            <input type="text" id="subject" name="subject" class="input-field w-full" required>
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-300 mb-2">Pesan</label>
                            <textarea id="message" name="message" class="input-field w-full" rows="6" required></textarea>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="submit-btn btn text-white">
                                <i class="fas fa-paper-plane mr-2"></i> Kirim Pesan
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Message History -->
                <div class="card p-6 slide-in">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-10 h-10 rounded-full bg-green-500 bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-history text-green-500"></i>
                        </div>
                        <h2 class="text-xl font-semibold">Riwayat Pesan</h2>
                    </div>

                    <?php if (empty($messages)): ?>
                        <p class="text-gray-400">Belum ada pesan yang dikirim.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($messages as $msg): ?>
                                <div class="message-container <?php echo $msg['sender'] === 'Student' ? 'message-student' : 'message-admin'; ?>">
                                    <div class="flex justify-between items-center mb-2">
                                        <h3 class="text-lg font-semibold <?php echo $msg['sender'] === 'Student' ? 'text-blue-400' : 'text-green-400'; ?>">
                                            <?php echo $msg['sender'] === 'Student' ? htmlspecialchars($msg['subject']) : 'Balasan Admin'; ?>
                                        </h3>
                                        <span class="text-sm text-gray-400"><?php echo date('d M Y, H:i', strtotime($msg['sent_at'])); ?></span>
                                    </div>
                                    <p class="text-gray-300"><?php echo htmlspecialchars($msg['message']); ?></p>
                                    <?php if ($msg['sender'] === 'Student'): ?>
                                        <div class="mt-2">
                                            <span class="text-sm <?php echo $msg['is_read'] ? 'message-status-read' : 'message-status-unread'; ?>">
                                                Status: <?php echo $msg['is_read'] ? 'Dibaca' : 'Belum dibaca'; ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-2">
                                            <span class="text-sm message-status-replied">
                                                Status: Dibalas
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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

        // Form submission with AJAX
        const supportForm = document.getElementById('supportForm');
        if (supportForm) {
            supportForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(supportForm);
                fetch('', {
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
                    Swal.fire({
                        icon: data.status,
                        title: data.status === 'success' ? 'Berhasil!' : 'Gagal',
                        text: data.message,
                        confirmButtonColor: '#3b82f6'
                    }).then(() => {
                        if (data.status === 'success') {
                            supportForm.reset();
                            window.location.reload();
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
    </script>
</body>
</html>