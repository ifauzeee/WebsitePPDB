<?php
session_start();

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is logged in
if (!isset($_SESSION['user_type']) || !isset($_SESSION['email'])) {
    $_SESSION['error'] = "Sesi tidak valid. Silakan login kembali.";
    header("Location: login.php");
    exit;
}

// Check if user is a student
if ($_SESSION['user_type'] !== 'student') {
    $_SESSION['error'] = "Akses ditolak. Halaman ini hanya untuk siswa.";
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
    $_SESSION['error'] = "Koneksi database gagal: " . $conn->connect_error;
    header("Location: login.php");
    exit;
}

// Fetch student data
$email = $_SESSION['email'];
$sql = "SELECT id, full_name, nisn, status, exam_permission, major_id, program FROM registrations WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    $_SESSION['error'] = "Data siswa tidak ditemukan untuk email: " . htmlspecialchars($email);
    $conn->close();
    header("Location: login.php");
    exit;
}

// Check exam permission and status
if ($student['exam_permission'] != 1) {
    $_SESSION['error'] = "Anda belum diizinkan untuk mengikuti ujian. Hubungi admin.";
    header("Location: student_dashboard.php");
    $conn->close();
    exit;
}

if (!in_array($student['status'], ['Terverifikasi', 'Diterima'])) {
    $_SESSION['error'] = "Status Anda (" . htmlspecialchars($student['status']) . ") tidak memungkinkan untuk mengikuti ujian.";
    header("Location: student_dashboard.php");
    $conn->close();
    exit;
}

if (!$student['major_id']) {
    $_SESSION['error'] = "Jurusan tidak ditemukan untuk program: " . htmlspecialchars($student['program']) . ". Silakan perbarui profil Anda.";
    header("Location: student_dashboard.php");
    $conn->close();
    exit;
}

// Check if exam already completed
$sql = "SELECT id FROM exam_sessions WHERE registration_id = ? AND status = 'Completed' ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student['id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $session = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    header("Location: exam_results.php?session_id=" . $session['id']);
    exit;
}
$stmt->close();

// Fetch major name for display
$sql = "SELECT name FROM majors WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student['major_id']);
$stmt->execute();
$result = $stmt->get_result();
$major = $result->fetch_assoc();
$major_name = $major ? htmlspecialchars($major['name']) : 'Unknown';
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
    <title>Exam Dashboard - STM Gotham City</title>
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
            transition: transform 0.3s ease;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .card {
            background: rgba(30, 41, 59, 0.8);
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        .btn {
            transition: all 0.3s ease;
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

        .btn-primary {
            background: #2563eb;
            color: #fff;
            padding: 0.75rem 2rem;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.5);
        }

        .btn-primary:disabled {
            background: #64748b;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-check {
            padding: 0.75rem 2.5rem;
            font-size: 0.9rem;
        }

        .btn-start {
            padding: 1rem 3rem;
            font-size: 1.1rem;
        }

        .btn-start:not(:disabled) {
            animation: pulse 2s infinite;
        }

        .logout-btn {
            background: linear-gradient(90deg, #ef4444, #f87171);
            padding: 0.75rem 2rem;
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
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .progress-circle {
            position: relative;
            width: 110px;
            height: 110px;
            filter: drop-shadow(0 0 2px rgba(0, 0, 0, 0.1));
        }

        .progress-circle-bg {
            fill: none;
            stroke: #475569;
            stroke-width: 4;
        }

        .progress-circle-value {
            fill: none;
            stroke: #10b981;
            stroke-linecap: round;
            stroke-width: 4;
            transition: stroke-dashoffset 0.3s ease;
        }

        .progress-circle-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.25rem;
            color: #10b981;
        }

        .device-check-fail {
            stroke: #ef4444;
        }

        .slide-in {
            animation: slideIn 0.5s forwards;
        }

        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                width: 256px;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 40;
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

            .btn-start {
                padding: 0.75rem 2rem;
                font-size: 1rem;
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
                        <a href="exam.php" class="flex items-center space-x-3 text-blue-400 bg-blue-900 bg-opacity-30 rounded-lg px-4 py-3 transition-all duration-300">
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
            <div class="max-w-7xl mx-auto">
                <!-- Welcome Section -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 slide-in">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">Exam Dashboard</h1>
                        <p class="text-gray-400">Prepare for your online exam at STM Gotham City (Program: <?php echo $major_name; ?>)</p>
                    </div>
                    <div class="mt-4 md:mt-0 flex items-center space-x-4">
                        <div class="status-badge">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($student['status']); ?>
                        </div>
                    </div>
                </div>

                <!-- Exam Requirements -->
                <div class="card p-6 mb-6 slide-in">
                    <div class="flex items-center space-x-4 mb-4">
                        <div class="w-10 h-10 rounded-full bg-blue-500 bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-laptop-code text-blue-500"></i>
                        </div>
                        <h2 class="text-xl font-semibold">Persyaratan Ujian</h2>
                    </div>
                    <p class="text-gray-400 mb-4">Pastikan Anda memenuhi persyaratan berikut untuk mengikuti ujian online:</p>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                            <span><strong>Perangkat:</strong> Laptop atau PC dengan RAM minimal 4GB dan prosesor Intel i3 atau setara.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                            <span><strong>Kamera:</strong> Webcam aktif dengan resolusi minimal 720p untuk pengawasan.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                            <span><strong>Mikrofon:</strong> Mikrofon aktif untuk komunikasi jika diperlukan.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                            <span><strong>Koneksi Internet:</strong> Kecepatan minimal 5 Mbps (download/upload) yang stabil.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                            <span><strong>Browser:</strong> Gunakan Google Chrome atau Mozilla Firefox versi terbaru.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                            <span><strong>Lingkungan:</strong> Ruangan yang tenang, terang, dan bebas dari gangguan.</span>
                        </li>
                    </ul>
                </div>

                <!-- Exam Rules -->
                <div class="card p-6 mb-6 slide-in">
                    <div class="flex items-center space-x-4 mb-4">
                        <div class="w-10 h-10 rounded-full bg-yellow-500 bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-gavel text-yellow-500"></i>
                        </div>
                        <h2 class="text-xl font-semibold">Peraturan Ujian</h2>
                    </div>
                    <p class="text-gray-400 mb-4">Patuhi peraturan berikut selama ujian online:</p>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-start">
                            <i class="fas fa-ban text-red-400 mr-2 mt-1"></i>
                            <span><strong>Tidak Diizinkan:</strong> Menggunakan bahan referensi (buku, catatan, atau situs web) kecuali diizinkan.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-ban text-red-400 mr-2 mt-1"></i>
                            <span><strong>Komunikasi:</strong> Dilarang berkomunikasi dengan orang lain selama ujian.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-ban text-red-400 mr-2 mt-1"></i>
                            <span><strong>Perekaman:</strong> Dilarang merekam atau menyebarkan soal ujian.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                            <span><strong>Identitas:</strong> Siapkan kartu identitas (KTP/SIM) untuk verifikasi.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                            <span><strong>Waktu:</strong> Masuk ke platform ujian 15 menit sebelum waktu mulai.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                            <span><strong>Pengawasan:</strong> Kamera harus tetap menyala selama ujian untuk pengawasan.</span>
                        </li>
                    </ul>
                </div>

                <!-- System Readiness -->
                <div class="card p-6 mb-6 slide-in">
                    <div class="flex items-center space-x-4 mb-4">
                        <div class="w-10 h-10 rounded-full bg-green-500 bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-tools text-green-500"></i>
                        </div>
                        <h2 class="text-xl font-semibold">System Readiness</h2>
                    </div>
                    <p class="text-gray-400 mb-6">Check your device to ensure it's ready for the exam:</p>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6 mb-8">
                        <div class="text-center">
                            <div class="progress-circle mx-auto">
                                <svg viewBox="0 0 36 36" class="w-full h-full">
                                    <path class="progress-circle-bg" stroke-dasharray="100, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
                                    <path id="cameraCheckCircle" class="progress-circle-value" stroke-dasharray="100, 100" stroke-dashoffset="100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
                                </svg>
                                <div class="progress-circle-text">
                                    <i class="fas fa-video"></i>
                                </div>
                            </div>
                            <p class="text-sm mt-2">Camera</p>
                        </div>
                        <div class="text-center">
                            <div class="progress-circle mx-auto">
                                <svg viewBox="0 0 36 36" class="w-full h-full">
                                    <path class="progress-circle-bg" stroke-dasharray="100, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
                                    <path id="micCheckCircle" class="progress-circle-value" stroke-dasharray="100, 100" stroke-dashoffset="100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
                                </svg>
                                <div class="progress-circle-text">
                                    <i class="fas fa-microphone"></i>
                                </div>
                            </div>
                            <p class="text-sm mt-2">Microphone</p>
                            <button id="runAllChecksBtn" class="btn btn-primary btn-check w-full md:w-auto mx-auto mt-4">Run All Checks</button>
                        </div>
                        <div class="text-center">
                            <div class="progress-circle mx-auto">
                                <svg viewBox="0 0 36 36" class="w-full h-full">
                                    <path class="progress-circle-bg" stroke-dasharray="100, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
                                    <path id="browserCheckCircle" class="progress-circle-value" stroke-dasharray="100, 100" stroke-dashoffset="100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
                                </svg>
                                <div class="progress-circle-text">
                                    <i class="fas fa-globe"></i>
                                </div>
                            </div>
                            <p class="text-sm mt-2">Browser</p>
                        </div>
                    </div>
                    <div class="flex justify-center">
                        <button id="startExamBtn" class="btn btn-primary btn-start w-full md:w-auto" disabled>Start Exam</button>
                    </div>
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

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-show');
            overlay.classList.remove('active');
        });

        // System Check Handlers
        const systemChecks = {
            camera: {
                circle: document.getElementById('cameraCheckCircle'),
                stream: null,
                async run() {
                    try {
                        this.stream = await navigator.mediaDevices.getUserMedia({ video: true });
                        this.circle.setAttribute('stroke-dashoffset', '0');
                        return true;
                    } catch (err) {
                        this.circle.setAttribute('stroke-dashoffset', '100');
                        this.circle.classList.add('device-check-fail');
                        Swal.fire({
                            title: 'Error Kamera',
                            text: 'Pastikan kamera terhubung dan izinkan akses.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
                },
                cleanup() {
                    if (this.stream) {
                        this.stream.getTracks().forEach(track => track.stop());
                    }
                }
            },
            mic: {
                circle: document.getElementById('micCheckCircle'),
                stream: null,
                async run() {
                    try {
                        this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        this.circle.setAttribute('stroke-dashoffset', '0');
                        return true;
                    } catch (err) {
                        this.circle.setAttribute('stroke-dashoffset', '100');
                        this.circle.classList.add('device-check-fail');
                        Swal.fire({
                            title: 'Error Mikrofon',
                            text: 'Pastikan mikrofon terhubung dan izinkan akses.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
                },
                cleanup() {
                    if (this.stream) {
                        this.stream.getTracks().forEach(track => track.stop());
                    }
                }
            },
            browser: {
                circle: document.getElementById('browserCheckCircle'),
                run() {
                    const userAgent = navigator.userAgent;
                    const isCompatible = /Chrome|Firefox|Edge/.test(userAgent);
                    if (isCompatible) {
                        this.circle.setAttribute('stroke-dashoffset', '0');
                        return true;
                    } else {
                        this.circle.setAttribute('stroke-dashoffset', '100');
                        this.circle.classList.add('device-check-fail');
                        Swal.fire({
                            title: 'Browser Tidak Didukung',
                            text: 'Gunakan Chrome, Firefox, atau Edge versi terbaru.',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
                }
            }
        };

        // Track system check progress
        let checkProgress = { camera: false, mic: false, browser: false };

        // Update Start Exam button state
        function updateSystemCheckProgress() {
            const completedChecks = Object.values(checkProgress).filter(status => status).length;
            document.getElementById('startExamBtn').disabled = completedChecks !== 3;
        }

        // Run all system checks
        document.getElementById('runAllChecksBtn').addEventListener('click', async () => {
            const runAllChecksBtn = document.getElementById('runAllChecksBtn');
            runAllChecksBtn.disabled = true;
            runAllChecksBtn.textContent = 'Memeriksa...';

            checkProgress = { camera: false, mic: false, browser: false };
            updateSystemCheckProgress();

            for (const key of Object.keys(systemChecks)) {
                checkProgress[key] = await systemChecks[key].run();
                await new Promise(resolve => setTimeout(resolve, 500));
            }

            runAllChecksBtn.disabled = false;
            runAllChecksBtn.textContent = 'Run All Checks';
            updateSystemCheckProgress();
        });

        // Start Exam button handler
        document.getElementById('startExamBtn').addEventListener('click', () => {
            Swal.fire({
                title: 'Mulai Ujian',
                text: 'Anda akan memulai ujian. Pastikan Anda siap.',
                icon: 'info',
                confirmButtonText: 'Mulai',
                cancelButtonText: 'Batal',
                showCancelButton: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'exam_session.php';
                }
            });
        });

        // Cleanup media streams on page unload
        window.addEventListener('beforeunload', () => {
            systemChecks.camera.cleanup();
            systemChecks.mic.cleanup();
        });
    </script>
</body>
</html>