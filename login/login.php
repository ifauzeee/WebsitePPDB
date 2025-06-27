<?php
session_start();

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Debug log function
function debug_log($message) {
    file_put_contents('php_errors.log', date('Y-m-d H:i:s') . " [DEBUG] $message\n", FILE_APPEND);
}

debug_log("Script started");

// Check if already logged in
if (isset($_SESSION['user_type']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    debug_log("Already logged in, user_type: " . $_SESSION['user_type']);
    header('Content-Type: application/json');
    if ($_SESSION['user_type'] === 'admin') {
        echo json_encode(['status' => 'success', 'message' => 'Sudah login', 'redirect' => 'admin_dashboard.php']);
    } elseif ($_SESSION['user_type'] === 'student') {
        echo json_encode(['status' => 'success', 'message' => 'Sudah login', 'redirect' => 'student_dashboard.php']);
    }
    exit;
}
if (isset($_SESSION['user_type'])) {
    debug_log("Redirecting logged-in user, user_type: " . $_SESSION['user_type']);
    if ($_SESSION['user_type'] === 'admin') {
        header("Location: admin_dashboard.php");
        exit;
    } elseif ($_SESSION['user_type'] === 'student') {
        header("Location: student_dashboard.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debug_log("Processing POST request");

    // Koneksi ke database
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "stm_gotham";

    debug_log("Attempting database connection");
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        debug_log("Database connection failed: " . $conn->connect_error);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal: ' . $conn->connect_error]);
        exit;
    }
    debug_log("Database connected successfully");

    $response = ['status' => 'error', 'message' => 'Login gagal'];

    if (isset($_POST['login_type']) && $_POST['login_type'] === 'student') {
        debug_log("Processing student login");
        $email = trim($_POST['student_email'] ?? '');
        $password = trim($_POST['student_password'] ?? '');

        // Validasi input
        if (empty($email) || empty($password)) {
            debug_log("Student login: Empty email or password");
            $response['message'] = 'Email dan kata sandi harus diisi';
        } else {
            // Cek kredensial siswa dari tabel users
            debug_log("Preparing student query for email: $email");
            $sql = "SELECT user_id, email, password_hash FROM users WHERE email = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                debug_log("Student query executed, rows: " . $result->num_rows);

                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password_hash'])) {
                        debug_log("Student login successful for email: $email");
                        $_SESSION['user_type'] = 'student';
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['email'] = $email;
                        $response = [
                            'status' => 'success',
                            'message' => 'Login siswa berhasil',
                            'redirect' => 'student_dashboard.php'
                        ];
                    } else {
                        debug_log("Student login failed: Incorrect password for email: $email");
                        $response['message'] = 'Kata sandi salah';
                    }
                } else {
                    debug_log("Student login failed: Email not found: $email");
                    $response['message'] = 'Email tidak ditemukan';
                }
                $stmt->close();
            } else {
                debug_log("Student query preparation failed: " . $conn->error);
                $response['message'] = 'Gagal menyiapkan query: ' . $conn->error;
            }
        }
    } elseif (isset($_POST['login_type']) && $_POST['login_type'] === 'admin') {
        debug_log("Processing admin login");
        $admin_id = trim($_POST['admin_id'] ?? '');
        $admin_password = trim($_POST['admin_password'] ?? '');

        // Validasi input
        if (empty($admin_id) || empty($admin_password)) {
            debug_log("Admin login: Empty ID or password");
            $response['message'] = 'ID dan kata sandi harus diisi';
        } else {
            // Cek kredensial admin dari tabel admins
            debug_log("Checking admin credentials for ID: $admin_id");
            $sql = "SELECT admin_id, password_hash FROM admins WHERE admin_id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $admin_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $admin = $result->fetch_assoc();
                    if (password_verify($admin_password, $admin['password_hash'])) {
                        debug_log("Admin login successful for ID: $admin_id");
                        $_SESSION['user_type'] = 'admin';
                        $_SESSION['admin_id'] = $admin_id;
                        $response = [
                            'status' => 'success',
                            'message' => 'Login admin berhasil',
                            'redirect' => 'admin_dashboard.php'
                        ];
                    } else {
                        debug_log("Admin login failed: Incorrect password for ID: $admin_id");
                        $response['message'] = 'ID atau kata sandi admin salah';
                    }
                } else {
                    debug_log("Admin login failed: ID not found: $admin_id");
                    $response['message'] = 'ID atau kata sandi admin salah';
                }
                $stmt->close();
            } else {
                debug_log("Admin query preparation failed: " . $conn->error);
                $response['message'] = 'Gagal menyiapkan query: ' . $conn->error;
            }
        }
    } else {
        debug_log("Invalid login type");
        $response['message'] = 'Tipe login tidak valid';
    }

    debug_log("Closing database connection");
    $conn->close();
    debug_log("Sending response: " . json_encode($response));
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Serve HTML untuk GET request
debug_log("Serving HTML for GET request");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - STM Gotham City</title>
    <link rel="icon" href="https://cdn.pixabay.com/photo/2018/10/05/21/29/bat-3726896_1280.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            scroll-behavior: smooth;
            background: #0f172a;
            color: #e2e8f0;
            overflow-x: hidden;
        }
        .navbar-fixed {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }
        .hero-section {
            position: relative;
            color: white;
            min-height: 100vh;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #1e3a8a, #1e40af, #0f172a);
            background-size: 400% 400%;
            animation: gradientFlow 20s ease infinite;
            overflow: hidden;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.4));
            z-index: 1;
        }
        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            animation: floatParticle 25s infinite ease-in-out;
        }
        @keyframes gradientFlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        @keyframes floatParticle {
            0% { transform: translateY(0) translateX(0); opacity: 0.6; }
            50% { opacity: 0.2; }
            100% { transform: translateY(-200vh) translateX(30px); opacity: 0; }
        }
        .form-container {
            background: #1e293b;
            border-radius: 1.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            transition: transform 0.5s ease, box-shadow 0.5s ease;
        }
        .form-container:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 80px rgba(59, 130, 246, 0.4);
        }
        .form-input {
            transition: all 0.3s ease;
            border: 2px solid #475569;
            border-radius: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: #0f172a;
            color: #e2e8f0;
        }
        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
            outline: none;
            background: #1e293b;
        }
        .form-input::placeholder {
            color: #94a3b8;
        }
        .submit-btn {
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
            transition: all 0.4s ease;
            padding: 0.75rem 2.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .submit-btn:hover {
            background: linear-gradient(90deg, #1e40af, #3b82f6);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.5);
        }
        .tab {
            cursor: pointer;
            padding: 1rem 2rem;
            font-weight: 600;
            color: #94a3b8;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        .tab.active {
            color: #e2e8f0;
            border-bottom: 3px solid #3b82f6;
        }
        .tab:hover {
            color: #e2e8f0;
        }
        .tab-content {
            display: none;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        .tab-content.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        .animate-pulse-slow {
            animation: pulse 5s ease-in-out infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        .animate-float {
            animation: float 4s ease-in-out infinite;
        }
        @keyframes float {
            0% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0); }
        }
        .navbar-link {
            position: relative;
            transition: color 0.4s ease;
        }
        .navbar-link::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 0;
            height: 3px;
            background: #3b82f6;
            transition: width 0.4s ease;
        }
        .navbar-link:hover::after {
            width: 100%;
        }
        .footer {
            background: linear-gradient(135deg, #0f172a, #1e3a8a);
            color: #e2e8f0;
            padding: 5rem 0;
        }
        @media (max-width: 768px) {
            .hero-section {
                min-height: 100vh;
            }
            .hero-section h1 {
                font-size: 3rem;
            }
            .form-container {
                padding: 2.5rem;
            }
            .tab {
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
            }
        }
        @media (max-width: 640px) {
            .hero-section h1 {
                font-size: 2.5rem;
            }
            .hero-section p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav id="navbar" class="fixed w-full top-0 z-50 transition-all duration-300 py-4">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <a href="../index.html" class="flex items-center space-x-3">
                <div class="w-14 h-14 rounded-full bg-white flex items-center justify-center">
                    <img src="https://cdn.pixabay.com/photo/2018/10/05/21/29/bat-3726896_1280.png" alt="Logo STM Gotham City" class="w-12 h-12 object-contain">
                </div>
                <div class="text-white font-bold text-2xl">STM Gotham City</div>
            </a>
            <div class="hidden md:flex space-x-10 items-center">
                <a href="../index.html#beranda" class="text-white navbar-link font-medium">Beranda</a>
                <a href="../index.html#tentang" class="text-white navbar-link font-medium">Tentang</a>
                <a href="../index.html#keunggulan" class="text-white navbar-link font-medium">Keunggulan</a>
                <a href="../index.html#galeri" class="text-white navbar-link font-medium">Galeri</a>
                <a href="../index.html#lokasi" class="text-white navbar-link font-medium">Lokasi</a>
                <a href="login.php" class="text-white navbar-link font-medium">Login</a>
                <a href="../pendaftaran/pendaftaran.php" class="text-white navbar-link font-medium">Pendaftaran</a>
            </div>
            <div class="md:hidden">
                <button id="mobile-menu-button" class="text-white focus:outline-none">
                    <i class="fas fa-bars text-3xl"></i>
                </button>
            </div>
        </div>
        <div id="mobile-menu" class="md:hidden hidden bg-gray-900 transition-all duration-300">
            <div class="container mx-auto px-4 py-6 flex flex-col space-y-5">
                <a href="../index.html#beranda" class="text-white hover:text-blue-400 transition py-2 font-medium">Beranda</a>
                <a href="../index.html#tentang" class="text-white hover:text-blue-400 transition py-2 font-medium">Tentang</a>
                <a href="../index.html#keunggulan" class="text-white hover:text-blue-400 transition py-2 font-medium">Keunggulan</a>
                <a href="../index.html#galeri" class="text-white hover:text-blue-400 transition py-2 font-medium">Galeri</a>
                <a href="../index.html#lokasi" class="text-white hover:text-blue-400 transition py-2 font-medium">Lokasi</a>
                <a href="login.php" class="text-white hover:text-blue-400 transition py-2 font-medium">Login</a>
                <a href="../pendaftaran/pendaftaran.php" class="text-white hover:text-blue-400 transition py-2 font-medium">Pendaftaran</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="particles"></div>
        <div class="hero-content">
            <img src="https://cdn.pixabay.com/photo/2018/10/05/21/29/bat-3726896_1280.png" alt="Logo STM Gotham City" class="w-32 h-32 mx-auto mb-8 animate-float">
            <h1 class="text-5xl md:text-7xl font-extrabold mb-4 animate-pulse-slow tracking-tight">Masuk Sekarang!</h1>
            <p class="text-xl md:text-2xl opacity-90 max-w-3xl mx-auto mb-10 font-light">Akses akun Anda untuk mengelola pendaftaran atau memulai perjalanan pendidikan Anda di STM Gotham City.</p>
            <button onclick="scrollToLogin()" class="submit-btn text-white">Login <i class="fas fa-sign-in-alt ml-2"></i></button>
        </div>
    </section>

    <!-- Login Section -->
    <section id="form" class="py-24">
        <div class="container mx-auto px-4">
            <div class="form-container max-w-lg mx-auto p-10">
                <h2 class="text-4xl font-bold text-white mb-8 text-center tracking-wide">Login</h2>
                <div class="flex justify-center mb-8">
                    <div class="tab active" data-tab="student">Login Siswa</div>
                    <div class="tab" data-tab="admin">Login Admin</div>
                </div>

                <!-- Student Login Form -->
                <div class="tab-content active" id="student-login">
                    <form id="studentLoginForm" method="POST">
                        <input type="hidden" name="login_type" value="student">
                        <div class="mb-6">
                            <label for="student_email" class="block text-sm font-medium text-gray-300 mb-3">Email</label>
                            <input type="email" id="student_email" name="student_email" class="form-input w-full" placeholder="Masukkan email" required>
                        </div>
                        <div class="mb-6">
                            <label for="student_password" class="block text-sm font-medium text-gray-300 mb-3">Kata Sandi</label>
                            <input type="password" id="student_password" name="student_password" class="form-input w-full" placeholder="Masukkan kata sandi" required>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="submit-btn text-white">Login Siswa <i class="fas fa-sign-in-alt ml-2"></i></button>
                        </div>
                    </form>
                </div>

                <!-- Admin Login Form -->
                <div class="tab-content" id="admin-login">
                    <form id="adminLoginForm" method="POST">
                        <input type="hidden" name="login_type" value="admin">
                        <div class="mb-6">
                            <label for="admin_id" class="block text-sm font-medium text-gray-300 mb-3">ID Admin</label>
                            <input type="text" id="admin_id" name="admin_id" class="form-input w-full" placeholder="Masukkan ID admin" required>
                        </div>
                        <div class="mb-6">
                            <label for="admin_password" class="block text-sm font-medium text-gray-300 mb-3">Kata Sandi</label>
                            <input type="password" id="admin_password" name="admin_password" class="form-input w-full" placeholder="Masukkan kata sandi" required>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="submit-btn text-white">Login Admin <i class="fas fa-sign-in-alt ml-2"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-16">
                <div>
                    <div class="flex items-center mb-8">
                        <img src="https://cdn.pixabay.com/photo/2018/10/05/21/29/bat-3726896_1280.png" alt="Logo STM Gotham City" class="mr-4 w-20 h-20">
                        <h3 class="text-2xl font-bold">STM Gotham City</h3>
                    </div>
                    <p class="text-gray-400">Sekolah vokasi teknologi terdepan yang mempersiapkan siswa untuk masa depan industri modern.</p>
                </div>
                <div>
                    <h4 class="text-xl font-semibold mb-6">Tautan Cepat</h4>
                    <ul class="space-y-4">
                        <li><a href="../index.html#beranda" class="text-gray-400 hover:text-blue-400 transition">Beranda</a></li>
                        <li><a href="../index.html#tentang" class="text-gray-400 hover:text-blue-400 transition">Tentang</a></li>
                        <li><a href="../index.html#keunggulan" class="text-gray-400 hover:text-blue-400 transition">Keunggulan</a></li>
                        <li><a href="../index.html#faq" class="text-gray-400 hover:text-blue-400 transition">FAQ</a></li>
                        <li><a href="../index.html#galeri" class="text-gray-400 hover:text-blue-400 transition">Galeri</a></li>
                        <li><a href="../index.html#lokasi" class="text-gray-400 hover:text-blue-400 transition">Lokasi</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xl font-semibold mb-6">Kontak Kami</h4>
                    <ul class="space-y-4">
                        <li class="flex items-center"><i class="fas fa-map-marker-alt mr-3 text-blue-400"></i>Jl. Wayne Tower, Gotham City, London</li>
                        <li class="flex items-center"><i class="fas fa-phone-alt mr-3 text-blue-400"></i>(021) 1234 5678</li>
                        <li class="flex items-center"><i class="fas fa-envelope mr-3 text-blue-400"></i><a href="mailto:info@stmgotham.ac.id">info@stmgotham.ac.id</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xl font-semibold mb-6">Ikuti Kami</h4>
                    <div class="flex space-x-5">
                        <a href="#" class="bg-blue-900 w-12 h-12 rounded-full flex items-center justify-center text-white hover:bg-blue-600 transition"><i class="fa-brands fa-facebook-f text-xl"></i></a>
                        <a href="#" class="bg-blue-900 w-12 h-12 rounded-full flex items-center justify-center text-white hover:bg-blue-600 transition"><i class="fa-brands fa-instagram text-xl"></i></a>
                        <a href="#" class="bg-blue-900 w-12 h-12 rounded-full flex items-center justify-center text-white hover:bg-blue-600 transition"><i class="fa-brands fa-twitter text-xl"></i></a>
                        <a href="#" class="bg-blue-900 w-12 h-12 rounded-full flex items-center justify-center text-white hover:bg-blue-600 transition"><i class="fa-brands fa-youtube text-xl"></i></a>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-16 pt-8 text-center text-gray-400">
                <p>© 2025 STM Gotham City. Hak Cipta Dilindungi.</p>
            </div>
        </div>
    </footer>

    <script>
        // Tambahkan SweetAlert
        const sweetAlertScript = document.createElement('script');
        sweetAlertScript.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
        document.head.appendChild(sweetAlertScript);

        // Tab switching
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(`${tab.getAttribute('data-tab')}-login`).classList.add('active');
            });
        });

        // Form submission
        const studentForm = document.getElementById('studentLoginForm');
        const adminForm = document.getElementById('adminLoginForm');

        studentForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(studentForm);
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`Network response was not ok: ${response.status} ${response.statusText}\nResponse: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                Swal.fire({
                    icon: data.status,
                    title: data.status === 'success' ? 'Selamat Datang!' : 'Gagal',
                    text: data.message,
                    confirmButtonColor: '#3b82f6'
                }).then(() => {
                    if (data.status === 'success') {
                        window.location.href = data.redirect;
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

        adminForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(adminForm);
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`Network response was not ok: ${response.status} ${response.statusText}\nResponse: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                Swal.fire({
                    icon: data.status,
                    title: data.status === 'success' ? 'Selamat Datang!' : 'Gagal',
                    text: data.message,
                    confirmButtonColor: '#3b82f6'
                }).then(() => {
                    if (data.status === 'success') {
                        window.location.href = data.redirect;
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
        document.getElementById('mobile-menu-button').addEventListener('click', () => {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });

        // Smooth scroll untuk anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = anchor.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 150,
                        behavior: 'smooth'
                    });
                    document.getElementById('mobile-menu').classList.add('hidden');
                }
            });
        });

        // Particles animation
        const particlesContainer = document.querySelector('.particles');
        for (let i = 0; i < 25; i++) {
            const particle = document.createElement('div');
            particle.classList.add('particle');
            particle.style.width = `${Math.random() * 6 + 3}px`;
            particle.style.height = particle.style.width;
            particle.style.left = `${Math.random() * 100}vw`;
            particle.style.top = `${Math.random() * 100}vh`;
            particle.style.animationDelay = `${Math.random() * 8}s`;
            particlesContainer.appendChild(particle);
        }

        // Scroll ke form login
        function scrollToLogin() {
            const loginForm = document.getElementById('form');
            loginForm.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>