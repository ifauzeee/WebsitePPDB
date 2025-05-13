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
$sql = "SELECT id, full_name, status, exam_permission FROM registrations WHERE email = ?";
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

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/Uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle file upload (new document)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document']) && isset($_POST['document_type']) && !isset($_POST['document_id'])) {
    $response = ['status' => 'error', 'message' => 'Gagal mengunggah dokumen'];

    $file = $_FILES['document'];
    $documentType = trim($_POST['document_type']);
    $allowedTypes = ['application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    $validDocumentTypes = ['Ijazah', 'Rapor', 'Pas Foto', 'Kartu Keluarga'];

    // Validate inputs
    if (!in_array($documentType, $validDocumentTypes)) {
        $response['message'] = 'Jenis dokumen tidak valid';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'Error saat mengunggah: ' . $file['error'];
    } elseif (!in_array($file['type'], $allowedTypes) || $file['size'] > $maxSize) {
        $response['message'] = 'File harus PDF dan maksimal 5MB';
    } else {
        $fileName = 'doc_' . $student['id'] . '_' . strtolower(str_replace(' ', '_', $documentType)) . '_' . time() . '.pdf';
        $filePath = $uploadDir . $fileName;
        $relativePath = 'Uploads/' . $fileName;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Insert into documents table
            $sql = "INSERT INTO documents (registration_id, document_type, document_name, file_path, uploaded_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Prepare failed for INSERT documents: " . $conn->error);
                $response['message'] = 'Gagal menyiapkan query.';
                unlink($filePath);
            } else {
                $stmt->bind_param("isss", $student['id'], $documentType, $documentType, $relativePath);
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'Dokumen berhasil diunggah'];
                } else {
                    $response['message'] = 'Gagal menyimpan data ke database';
                    unlink($filePath);
                }
                $stmt->close();
            }
        } else {
            $response['message'] = 'Gagal memindahkan file';
        }
    }

    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

// Handle document replacement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document']) && isset($_POST['document_id']) && isset($_POST['document_type'])) {
    $response = ['status' => 'error', 'message' => 'Gagal mengganti dokumen'];

    $file = $_FILES['document'];
    $document_id = (int)$_POST['document_id'];
    $documentType = trim($_POST['document_type']);
    $allowedTypes = ['application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    $validDocumentTypes = ['Ijazah', 'Rapor', 'Pas Foto', 'Kartu Keluarga'];

    // Validate inputs
    if (!in_array($documentType, $validDocumentTypes)) {
        $response['message'] = 'Jenis dokumen tidak valid';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'Error saat mengunggah: ' . $file['error'];
    } elseif (!in_array($file['type'], $allowedTypes) || $file['size'] > $maxSize) {
        $response['message'] = 'File harus PDF dan maksimal 5MB';
    } else {
        // Fetch existing document to get old file path
        $sql = "SELECT file_path FROM documents WHERE id = ? AND registration_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for SELECT document: " . $conn->error);
            $response['message'] = 'Gagal menyiapkan query.';
        } else {
            $stmt->bind_param("ii", $document_id, $student['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing_doc = $result->fetch_assoc();
            $stmt->close();

            if (!$existing_doc) {
                $response['message'] = 'Dokumen tidak ditemukan atau Anda tidak memiliki akses.';
            } else {
                $fileName = 'doc_' . $student['id'] . '_' . strtolower(str_replace(' ', '_', $documentType)) . '_' . time() . '.pdf';
                $filePath = $uploadDir . $fileName;
                $relativePath = 'Uploads/' . $fileName;

                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    // Update documents table
                    $sql = "UPDATE documents SET document_type = ?, document_name = ?, file_path = ?, uploaded_at = NOW() WHERE id = ? AND registration_id = ?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        error_log("Prepare failed for UPDATE documents: " . $conn->error);
                        $response['message'] = 'Gagal menyiapkan query.';
                        unlink($filePath);
                    } else {
                        $stmt->bind_param("sssii", $documentType, $documentType, $relativePath, $document_id, $student['id']);
                        if ($stmt->execute()) {
                            // Delete old file
                            $oldFilePath = __DIR__ . '/' . $existing_doc['file_path'];
                            if (file_exists($oldFilePath)) {
                                unlink($oldFilePath);
                            }
                            $response = ['status' => 'success', 'message' => 'Dokumen berhasil diganti'];
                        } else {
                            $response['message'] = 'Gagal memperbarui database';
                            unlink($filePath);
                        }
                        $stmt->close();
                    }
                } else {
                    $response['message'] = 'Gagal memindahkan file';
                }
            }
        }
    }

    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

// Handle document deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document_id'])) {
    $response = ['status' => 'error', 'message' => 'Gagal menghapus dokumen'];
    $document_id = (int)$_POST['delete_document_id'];

    // Fetch document to get file path
    $sql = "SELECT file_path FROM documents WHERE id = ? AND registration_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $document_id, $student['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $doc = $result->fetch_assoc();
        $stmt->close();

        if ($doc) {
            // Delete from database
            $sql = "DELETE FROM documents WHERE id = ? AND registration_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ii", $document_id, $student['id']);
                if ($stmt->execute()) {
                    // Delete file
                    $filePath = __DIR__ . '/' . $doc['file_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    $response = ['status' => 'success', 'message' => 'Dokumen berhasil dihapus'];
                }
                $stmt->close();
            }
        } else {
            $response['message'] = 'Dokumen tidak ditemukan atau Anda tidak memiliki akses.';
        }
    }
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

// Fetch document history from documents table
$sql = "SELECT id, document_type, file_path, uploaded_at FROM documents WHERE registration_id = ? ORDER BY uploaded_at DESC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed for SELECT documents: " . $conn->error);
    $_SESSION['error'] = "Gagal mengambil riwayat dokumen.";
    $conn->close();
    header("Location: login.php");
    exit;
}
$stmt->bind_param("i", $student['id']);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// Format date to Indonesian
function formatTanggalIndonesia($datetime) {
    if (!$datetime) return '-';
    return date('d/m/Y H:i', strtotime($datetime));
}

// Tambahkan sebelum tabel dokumen saat ini
$requiredTypes = ['Ijazah', 'Rapor', 'Pas Foto', 'Kartu Keluarga'];
$uploadedDocs = [];
foreach ($documents as $doc) {
    $uploadedDocs[$doc['document_type']] = $doc;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Student Documents - STM Gotham City</title>
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
        
        input[type="file"]::-webkit-file-upload-button {
            visibility: hidden;
        }
        input[type="file"]::before {
            content: '';
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
        
        .replace-btn {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }
        
        .replace-btn:hover {
            background: linear-gradient(90deg, #d97706, #f59e0b);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.5);
        }
        
        .delete-btn {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }
        
        .delete-btn:hover {
            background: linear-gradient(90deg, #dc2626, #ef4444);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.5);
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
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid rgba(71, 85, 105, 0.3);
        }
        
        th {
            background: rgba(15, 23, 42, 0.8);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            color: #94a3b8;
        }
        
        td {
            background: rgba(30, 41, 59, 0.8);
        }
        
        tr:hover td {
            background: rgba(59, 130, 246, 0.1);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 50;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: rgba(30, 41, 59, 0.95);
            border-radius: 1rem;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            color: #e2e8f0;
            font-size: 1.5rem;
            cursor: pointer;
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

        .custom-file-label {
            background: rgba(15, 23, 42, 0.8);
            border: 2px solid rgba(71, 85, 105, 0.8);
            border-radius: 0.5rem;
            padding: 0.75rem 1.25rem;
            color: #e2e8f0;
            transition: all 0.3s ease;
        }
        .custom-file-label:hover {
            border-color: #3b82f6;
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
                        <a href="students_documents.php" class="flex items-center space-x-3 text-blue-400 bg-blue-900 bg-opacity-30 rounded-lg px-4 py-3 transition-all duration-300">
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
                        <h1 class="text-3xl font-bold mb-2 slide-in">Dokumen Anda</h1>
                        <p class="text-gray-400 slide-in">Kelola dokumen pendaftaran Anda</p>
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

                <!-- Document Upload Section -->
                <div class="card p-6 mb-6 slide-in">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-10 h-10 rounded-full bg-blue-500 bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-file-upload text-blue-500"></i>
                        </div>
                        <h2 class="text-xl font-semibold">Unggah Dokumen Baru</h2>
                    </div>
                    <form id="documentForm" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label for="document_type" class="block text-sm font-medium text-gray-300 mb-2">Jenis Dokumen</label>
                            <select id="document_type" name="document_type" class="input-field w-full" required>
                                <option value="" disabled selected>Pilih Jenis Dokumen</option>
                                <option value="Ijazah">Ijazah</option>
                                <option value="Rapor">Rapor</option>
                                <option value="Pas Foto">Pas Foto</option>
                                <option value="Kartu Keluarga">Kartu Keluarga</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Pilih Dokumen (PDF, maks 5MB)</label>
                            <label for="document" class="custom-file-label input-field w-full flex items-center cursor-pointer">
                                <i class="fas fa-paperclip mr-2"></i>
                                <span id="file-chosen" class="truncate text-gray-400">Belum ada file dipilih</span>
                                <input type="file" id="document" name="document" accept=".pdf" class="hidden" required>
                            </label>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="submit-btn btn text-white">
                                <i class="fas fa-upload mr-2"></i> Unggah
                            </button>
                        </div>
                    </form>
                </div>

                <script>
                // Custom tampilkan nama file yang dipilih
                const documentInput = document.getElementById('document');
                const fileChosen = document.getElementById('file-chosen');
                if (documentInput && fileChosen) {
                    documentInput.addEventListener('change', function(){
                        fileChosen.textContent = this.files.length ? this.files[0].name : 'Belum ada file dipilih';
                    });
                }
                </script>

                <!-- Current Document Section -->
                <div class="card p-6 mb-6 slide-in">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-10 h-10 rounded-full bg-green-500 bg-opacity-20 flex items-center justify-center">
                            <i class="fas fa-file-pdf text-green-500"></i>
                        </div>
                        <h2 class="text-xl font-semibold">Dokumen Saat Ini</h2>
                    </div>
                    <div class="table-container">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="w-1/4">Jenis Dokumen</th>
                                    <th class="w-1/4">Tanggal Unggah</th>
                                    <th class="w-1/2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requiredTypes as $type):
                                    $doc = $uploadedDocs[$type] ?? null;
                                ?>
                                <tr>
                                    <td class="py-4 font-semibold"><?php echo htmlspecialchars($type); ?></td>
                                    <td class="py-4">
                                        <?php if ($doc): ?>
                                            <?php echo formatTanggalIndonesia($doc['uploaded_at']); ?>
                                        <?php else: ?>
                                            <span class="text-yellow-400">Belum diupload</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4">
                                        <?php if ($doc): ?>
                                            <div class="flex items-center space-x-6">
                                                <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                                   class="inline-flex items-center text-blue-400 hover:underline px-3 py-2" 
                                                   target="_blank">
                                                    <i class="fas fa-eye mr-2"></i> Lihat
                                                </a>
                                                <button class="replace-btn btn text-white px-4"
                                                        onclick="openReplaceModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['document_type']); ?>')">
                                                    <i class="fas fa-sync-alt mr-2"></i> Ganti
                                                </button>
                                                <button class="logout-btn btn text-white px-4"
                                                        onclick="deleteDocument(<?php echo $doc['id']; ?>)">
                                                    <i class="fas fa-trash-alt mr-2"></i> Hapus
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-yellow-400">Belum diupload</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Replace Document Modal -->
    <div id="replaceModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeReplaceModal()">×</span>
            <h2 class="text-xl font-semibold mb-4">Ganti Dokumen</h2>
            <form id="replaceForm" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" id="replace_document_id" name="document_id">
                <div>
                    <label for="replace_document_type" class="block text-sm font-medium text-gray-300 mb-2">Jenis Dokumen</label>
                    <input type="text" id="replace_document_type" name="document_type" class="input-field w-full" readonly>
                </div>
                <div>
                    <label for="replace_document" class="block text-sm font-medium text-gray-300 mb-2">Pilih Dokumen Baru (PDF, maks 5MB)</label>
                    <input type="file" id="replace_document" name="document" accept=".pdf" class="input-field w-full" required>
                </div>
                <div class="text-right">
                    <button type="submit" class="submit-btn btn text-white">
                        <i class="fas fa-upload mr-2"></i> Ganti
                    </button>
                </div>
            </form>
        </div>
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

        // Document upload form submission with AJAX
        const documentForm = document.getElementById('documentForm');
        if (documentForm) {
            documentForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(documentForm);
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

        // Replace document form submission with AJAX
        const replaceForm = document.getElementById('replaceForm');
        if (replaceForm) {
            replaceForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(replaceForm);
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
                            window.location.reload();
                        }
                    });
                    closeReplaceModal();
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

        // Delete document function
        function deleteDocument(documentId) {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: 'Apakah Anda yakin ingin menghapus dokumen ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('delete_document_id', documentId);
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
                }
            });
        }

        // Modal functions
        function openReplaceModal(documentId, documentType) {
            const modal = document.getElementById('replaceModal');
            document.getElementById('replace_document_id').value = documentId;
            document.getElementById('replace_document_type').value = documentType;
            modal.style.display = 'flex';
            
            // Event listener untuk klik di luar modal
            modal.addEventListener('click', handleModalClick);
            
            // Event listener untuk tombol Escape
            document.addEventListener('keydown', handleEscapeKey);
        }

        function closeReplaceModal() {
            const modal = document.getElementById('replaceModal');
            modal.style.display = 'none';
            document.getElementById('replaceForm').reset();
            document.getElementById('replace_document_id').value = '';
            document.getElementById('replace_document_type').value = '';
            
            // Hapus event listeners
            modal.removeEventListener('click', handleModalClick);
            document.removeEventListener('keydown', handleEscapeKey);
        }

        // Handler untuk klik di luar modal
        function handleModalClick(e) {
            if (e.target === document.getElementById('replaceModal')) {
                closeReplaceModal();
            }
        }

        // Handler untuk tombol Escape
        function handleEscapeKey(e) {
            if (e.key === 'Escape') {
                closeReplaceModal();
            }
        }
    </script>
</body>
</html>