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
$sql = "SELECT id, full_name, email, phone, birth_date, address, program, status, admin_notes, gender, birth_place, nik, religion, previous_school, nisn, graduation_year, father_name, father_occupation, father_phone, mother_name, mother_occupation, mother_phone, district, city, province, postal_code, exam_permission, major_id FROM registrations WHERE email = ?";
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

// Fetch student documents
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

// Fetch majors data
$sql_majors = "SELECT id, name FROM majors ORDER BY name";
$result_majors = $conn->query($sql_majors);
if (!$result_majors) {
    error_log("Query failed for SELECT majors: " . $conn->error);
    $_SESSION['error'] = "Gagal mengambil data jurusan.";
    $conn->close();
    header("Location: login.php");
    exit;
}
$majors = $result_majors->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $birth_place = trim($_POST['birth_place'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $religion = trim($_POST['religion'] ?? '');
    $previous_school = trim($_POST['previous_school'] ?? '');
    $graduation_year = trim($_POST['graduation_year'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $father_occupation = trim($_POST['father_occupation'] ?? '');
    $father_phone = trim($_POST['father_phone'] ?? '');
    $mother_name = trim($_POST['mother_name'] ?? '');
    $mother_occupation = trim($_POST['mother_occupation'] ?? '');
    $mother_phone = trim($_POST['mother_phone'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $major_id = trim($_POST['major_id'] ?? '');

    $response = ['status' => 'error', 'message' => 'Gagal memperbarui data'];

    // Validate inputs
    if (empty($full_name)) {
        $response['message'] = 'Nama Lengkap tidak boleh kosong';
    } elseif (empty($nisn)) {
        $response['message'] = 'NISN tidak boleh kosong';
    } elseif (!preg_match("/^\d{10}$/", $nisn)) {
        $response['message'] = 'NISN harus berupa 10 digit angka';
    } elseif ($nik && !preg_match("/^\d{16}$/", $nik)) {
        $response['message'] = 'NIK harus berupa 16 digit angka';
    } elseif ($phone && !preg_match("/^\d{0,15}$/", $phone)) {
        $response['message'] = 'Nomor Telepon harus berupa angka (maksimum 15 digit)';
    } elseif ($father_phone && !preg_match("/^\d{0,15}$/", $father_phone)) {
        $response['message'] = 'Nomor Telepon Ayah harus berupa angka (maksimum 15 digit)';
    } elseif ($mother_phone && !preg_match("/^\d{0,15}$/", $mother_phone)) {
        $response['message'] = 'Nomor Telepon Ibu harus berupa angka (maksimum 15 digit)';
    } elseif ($graduation_year && !preg_match("/^(19[0-9]{2}|20[0-9]{2}|21[0-5][0-5])$/", $graduation_year)) {
        $response['message'] = 'Tahun Lulus harus antara 1901 dan 2155';
    } elseif ($postal_code && !preg_match("/^\d{0,10}$/", $postal_code)) {
        $response['message'] = 'Kode Pos harus berupa angka (maksimum 10 digit)';
    } elseif ($gender && !in_array($gender, ['Laki-laki', 'Perempuan'])) {
        $response['message'] = 'Jenis Kelamin tidak valid';
    } elseif ($major_id && !in_array($major_id, array_column($majors, 'id'))) {
        $response['message'] = 'Jurusan tidak valid';
    } else {
        // Update database
        $sql = "UPDATE registrations SET full_name = ?, nisn = ?, phone = ?, address = ?, gender = ?, birth_place = ?, birth_date = ?, religion = ?, previous_school = ?, graduation_year = ?, father_name = ?, father_occupation = ?, father_phone = ?, mother_name = ?, mother_occupation = ?, mother_phone = ?, district = ?, city = ?, province = ?, postal_code = ?, nik = ?, major_id = ? WHERE email = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for UPDATE: " . $conn->error);
            $response['message'] = "Gagal menyiapkan query: " . $conn->error;
        } else {
            // Bind parameters, using NULL for empty nullable fields
            $phone = $phone ?: null;
            $address = $address ?: null;
            $gender = $gender ?: null;
            $birth_place = $birth_place ?: null;
            $birth_date = $birth_date ?: null;
            $religion = $religion ?: null;
            $previous_school = $previous_school ?: null;
            $graduation_year = $graduation_year ?: null;
            $father_name = $father_name ?: null;
            $father_occupation = $father_occupation ?: null;
            $father_phone = $father_phone ?: null;
            $mother_name = $mother_name ?: null;
            $mother_occupation = $mother_occupation ?: null;
            $mother_phone = $mother_phone ?: null;
            $district = $district ?: null;
            $city = $city ?: null;
            $province = $province ?: null;
            $postal_code = $postal_code ?: null;
            $nik = $nik ?: null;
            $major_id = $major_id ?: null;

            $stmt->bind_param("sssssssssssssssssssssis", $full_name, $nisn, $phone, $address, $gender, $birth_place, $birth_date, $religion, $previous_school, $graduation_year, $father_name, $father_occupation, $father_phone, $mother_name, $mother_occupation, $mother_phone, $district, $city, $province, $postal_code, $nik, $major_id, $email);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Data berhasil diperbarui'];
            } else {
                error_log("Execute failed for UPDATE: " . $stmt->error);
                $response['message'] = "Gagal memperbarui data: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    ob_end_clean(); // Clear buffer before JSON output
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit; // Ensure no further output
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
    <title>Student Profile - STM Gotham City</title>
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
                        <a href="#" class="flex items-center space-x-3 text-blue-400 bg-blue-900 bg-opacity-30 rounded-lg px-4 py-3 transition-all duration-300">
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
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                    <div>
                        <h1 class="text-3xl font-bold mb-2 slide-in">Profile Anda</h1>
                        <p class="text-gray-400 slide-in">Kelola informasi pribadi dan pendaftaran Anda</p>
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
                            case 'Ditolak':
                                $statusClass = 'status-rejected';
                                $statusIcon = 'fa-times';
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

                <!-- Profile Form -->
                <div class="card p-6 slide-in">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-10 h-10 rounded-full bg-blue-500 bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-user text-blue-500"></i>
                        </div>
                        <h2 class="text-xl font-semibold">Informasi Profil</h2>
                    </div>
                    <form id="profileForm" method="POST" class="space-y-8">
                        <!-- Informasi Pribadi -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Informasi Pribadi</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="full_name" class="block text-sm font-medium text-gray-300 mb-2">Nama Lengkap</label>
                                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($student['full_name'] ?? ''); ?>" class="input-field w-full" required>
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                                    <input type="email" id="email" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" class="input-field w-full bg-gray-700" readonly>
                                </div>
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-300 mb-2">Nomor Telepon</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" class="input-field w-full" pattern="[0-9]{0,15}">
                                </div>
                                <div>
                                    <label for="nik" class="block text-sm font-medium text-gray-300 mb-2">NIK</label>
                                    <input type="text" id="nik" name="nik" value="<?php echo htmlspecialchars($student['nik'] ?? ''); ?>" class="input-field w-full" pattern="\d{16}">
                                </div>
                                <div>
                                    <label for="gender" class="block text-sm font-medium text-gray-300 mb-2">Jenis Kelamin</label>
                                    <select id="gender" name="gender" class="input-field w-full">   
                                        <option value="" <?php echo empty($student['gender']) ? 'selected' : ''; ?>>Pilih</option>
                                        <option value="Laki-laki" <?php echo $student['gender'] === 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                                        <option value="Perempuan" <?php echo $student['gender'] === 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="religion" class="block text-sm font-medium text-gray-300 mb-2">Agama</label>
                                    <select id="religion" name="religion" class="input-field w-full">
                                        <option value="" <?php echo empty($student['religion']) ? 'selected' : ''; ?>>Pilih Agama</option>
                                        <option value="Islam" <?php echo $student['religion'] === 'Islam' ? 'selected' : ''; ?>>Islam</option>
                                        <option value="Kristen" <?php echo $student['religion'] === 'Kristen' ? 'selected' : ''; ?>>Kristen</option>
                                        <option value="Katolik" <?php echo $student['religion'] === 'Katolik' ? 'selected' : ''; ?>>Katolik</option>
                                        <option value="Hindu" <?php echo $student['religion'] === 'Hindu' ? 'selected' : ''; ?>>Hindu</option>
                                        <option value="Buddha" <?php echo $student['religion'] === 'Buddha' ? 'selected' : ''; ?>>Buddha</option>
                                        <option value="Konghucu" <?php echo $student['religion'] === 'Konghucu' ? 'selected' : ''; ?>>Konghucu</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="birth_place" class="block text-sm font-medium text-gray-300 mb-2">Tempat Lahir</label>
                                    <input type="text" id="birth_place" name="birth_place" value="<?php echo htmlspecialchars($student['birth_place'] ?? ''); ?>" class="input-field w-full">
                                </div>
                                <div>
                                    <label for="birth_date" class="block text-sm font-medium text-gray-300 mb-2">Tanggal Lahir</label>
                                    <input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($student['birth_date'] ?? ''); ?>" class="input-field w-full">
                                </div>
                            </div>
                        </div>

                        <!-- Academic Information -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Informasi Akademik</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="nisn" class="block text-sm font-medium text-gray-300 mb-2">NISN</label>
                                    <input type="text" id="nisn" name="nisn" value="<?php echo htmlspecialchars($student['nisn'] ?? ''); ?>" class="input-field w-full" pattern="\d{10}" required>
                                </div>
                                <div>
                                    <label for="major_id" class="block text-sm font-medium text-gray-300 mb-2">Program Studi</label>
                                    <select id="major_id" name="major_id" class="input-field w-full">
                                        <option value="" <?php echo empty($student['major_id']) ? 'selected' : ''; ?>>Pilih Jurusan</option>
                                        <?php foreach ($majors as $major): ?>
                                            <option value="<?php echo htmlspecialchars($major['id']); ?>" <?php echo $student['major_id'] == $major['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($major['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="previous_school" class="block text-sm font-medium text-gray-300 mb-2">Sekolah Asal</label>
                                    <input type="text" id="previous_school" name="previous_school" value="<?php echo htmlspecialchars($student['previous_school'] ?? ''); ?>" class="input-field w-full">
                                </div>
                                <div>
                                    <label for="graduation_year" class="block text-sm font-medium text-gray-300 mb-2">Tahun Lulus</label>
                                    <input type="text" id="graduation_year" name="graduation_year" value="<?php echo htmlspecialchars($student['graduation_year'] ?? ''); ?>" class="input-field w-full" pattern="(19[0-9]{2}|20[0-9]{2}|21[0-5][0-5])">
                                </div>
                            </div>
                        </div>

                        <!-- Parent Information -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Informasi Orang Tua</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="father_name" class="block text-sm font-medium text-gray-300 mb-2">Nama Ayah</label>
                                    <input type="text" id="father_name" name="father_name" value="<?php echo htmlspecialchars($student['father_name'] ?? ''); ?>" class="input-field w-full">
                                </div>
                                <div>
                                    <label for="father_occupation" class="block text-sm font-medium text-gray-300 mb-2">Pekerjaan Ayah</label>
                                    <input type="text" id="father_occupation" name="father_occupation" value="<?php echo htmlspecialchars($student['father_occupation'] ?? ''); ?>" class="input-field w-full">
                                </div>
                                <div>
                                    <label for="father_phone" class="block text-sm font-medium text-gray-300 mb-2">No. Telepon Ayah</label>
                                    <input type="tel" id="father_phone" name="father_phone" value="<?php echo htmlspecialchars($student['father_phone'] ?? ''); ?>" class="input-field w-full" pattern="[0-9]{0,15}">
                                </div>
                                <div>
                                    <label for="mother_name" class="block text-sm font-medium text-gray-300 mb-2">Nama Ibu</label>
                                    <input type="text" id="mother_name" name="mother_name" value="<?php echo htmlspecialchars($student['mother_name'] ?? ''); ?>" class="input-field w-full">
                                </div>
                                <div>
                                    <label for="mother_occupation" class="block text-sm font-medium text-gray-300 mb-2">Pekerjaan Ibu</label>
                                    <input type="text" id="mother_occupation" name="mother_occupation" value="<?php echo htmlspecialchars($student['mother_occupation'] ?? ''); ?>" class="input-field w-full">
                                </div>
                                <div>
                                    <label for="mother_phone" class="block text-sm font-medium text-gray-300 mb-2">No. Telepon Ibu</label>
                                    <input type="tel" id="mother_phone" name="mother_phone" value="<?php echo htmlspecialchars($student['mother_phone'] ?? ''); ?>" class="input-field w-full" pattern="[0-9]{0,15}">
                                </div>
                            </div>
                        </div>

                        <!-- Address Information -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Informasi Alamat</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label for="address" class="block text-sm font-medium text-gray-300 mb-2">Alamat Lengkap</label>
                                    <textarea id="address" name="address" class="input-field w-full" rows="4"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                                </div>
                                <div>
                                    <label for="district" class="block text-sm font-medium text-gray-300 mb-2">Kecamatan</label>
                                    <input type="text" id="district" name="district" value="<?php echo htmlspecialchars($student['district'] ?? ''); ?>" class="input-field w-full">
                                </div>
                                <div>
                                    <label for="city" class="block text-sm font-medium text-gray-300 mb-2">Kota/Kabupaten</label>
                                    <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($student['city'] ?? ''); ?>" class="input-field w-full">
                                </div>
                                <div>
                                    <label for="province" class="block text-sm font-medium text-gray-300 mb-2">Provinsi</label>
                                    <input type="text" id="province" name="province" value="<?php echo htmlspecialchars($student['province'] ?? ''); ?>" class="input-field w-full">
                                </div>
                                <div>
                                    <label for="postal_code" class="block text-sm font-medium text-gray-300 mb-2">Kode Pos</label>
                                    <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($student['postal_code'] ?? ''); ?>" class="input-field w-full" pattern="\d{0,10}">
                                </div>
                            </div>
                        </div>

                        <!-- Documents and Admin Notes -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Dokumen dan Catatan Admin</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Dokumen Terkirim</label>
                                    <?php if (!empty($student_documents)): ?>
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
                                    <?php else: ?>
                                        <p class="text-gray-400">Belum ada dokumen diunggah</p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Catatan Admin</label>
                                    <p class="text-gray-400"><?php echo htmlspecialchars($student['admin_notes'] ?? 'Tidak ada catatan'); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="text-right">
                            <button type="submit" class="submit-btn btn text-white">
                                <i class="fas fa-save mr-2"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
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
        const profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(profileForm);
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            throw new Error(`Response is not JSON: ${text}`);
                        });
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
                            window.location.reload();
                        }
                    });
                })
                .catch(error => {
                    console.error('Fetch error:', error);
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