<?php
session_start();

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    $_SESSION['error'] = "Koneksi database gagal: " . $conn->connect_error;
    header("Location: admin_dashboard.php");
    exit;
}

// Validate ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID tidak valid.";
    header("Location: admin_dashboard.php");
    exit;
}

$id = (int)$_GET['id'];

// Handle status history deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_status_history'])) {
    $history_id = (int)($_POST['history_id'] ?? 0);
    $response = ['status' => 'error', 'message' => 'Gagal menghapus riwayat status'];

    // Verify the history record exists and belongs to the student
    $sql = "SELECT id FROM status_history WHERE id = ? AND registration_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $history_id, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $response['message'] = 'Riwayat status tidak ditemukan';
        $stmt->close();
        header('Content-Type: application/json');
        echo json_encode($response);
        $conn->close();
        exit;
    }
    $stmt->close();

    // Delete the status history record
    $sql = "DELETE FROM status_history WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $history_id);
    if ($stmt->execute()) {
        $response = ['status' => 'success', 'message' => 'Riwayat status berhasil dihapus'];
    } else {
        $response['message'] = 'Gagal menghapus riwayat status: ' . $stmt->error;
    }
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode($response);
    $conn->close();
    exit;
}

// Fetch student data
$sql = "SELECT * FROM registrations WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Pendaftar tidak ditemukan.";
    $stmt->close();
    $conn->close();
    header("Location: admin_dashboard.php");
    exit;
}

$student = $result->fetch_assoc();
$stmt->close();

// Fetch documents from documents table
$sql_documents = "SELECT document_type, document_name, file_path, uploaded_at 
                 FROM documents 
                 WHERE registration_id = ? 
                 ORDER BY uploaded_at ASC";
$stmt_documents = $conn->prepare($sql_documents);
$stmt_documents->bind_param("i", $id);
$stmt_documents->execute();
$result_documents = $stmt_documents->get_result();
if ($result_documents === false) {
    error_log("Gagal mengambil dokumen untuk ID $id: " . $conn->error, 3, __DIR__ . '/error.log');
    $documents = [];
} else {
    $documents = $result_documents->fetch_all(MYSQLI_ASSOC);
}
$stmt_documents->close();

// Fetch status history
$sql_history = "SELECT id, status, notes, changed_at, changed_by 
                FROM status_history 
                WHERE registration_id = ? 
                ORDER BY changed_at ASC";
$stmt_history = $conn->prepare($sql_history);
$stmt_history->bind_param("i", $id);
$stmt_history->execute();
$result_history = $stmt_history->get_result();
$status_history = $result_history->fetch_all(MYSQLI_ASSOC);
$stmt_history->close();

// Proses update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = trim($_POST['status'] ?? '');
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    // Log POST data for debugging
    error_log("POST Data - ID: $id, Status: '$new_status', Admin Notes: '$admin_notes'", 3, __DIR__ . '/debug.log');
    
    // Validate status
    $valid_statuses = ['Menunggu Verifikasi', 'Terverifikasi', 'Diterima', 'Ditolak'];
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['error'] = "Status tidak valid.";
        error_log("Invalid status: '$new_status'", 3, __DIR__ . '/error.log');
    } else {
        // Start transaction
        $conn->begin_transaction();
        try {
            // Update registrations
            $update_sql = "UPDATE registrations SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssi", $new_status, $admin_notes, $id);
            if (!$update_stmt->execute()) {
                throw new Exception("Gagal memperbarui registrations: " . $update_stmt->error);
            }
            $update_stmt->close();

            // Insert into status_history
            $history_sql = "INSERT INTO status_history (registration_id, status, notes, changed_at, changed_by) VALUES (?, ?, ?, NOW(), ?)";
            $history_stmt = $conn->prepare($history_sql);
            $changed_by = $_SESSION['user_name'] ?? 'Admin';
            $history_stmt->bind_param("isss", $id, $new_status, $admin_notes, $changed_by);
            if (!$history_stmt->execute()) {
                throw new Exception("Gagal menyimpan riwayat status: " . $history_stmt->error);
            }
            $history_stmt->close();

            // Commit transaction
            $conn->commit();
            $_SESSION['success'] = "Status dan catatan admin berhasil diperbarui.";
            error_log("Update successful for ID $id - Status: '$new_status', Admin Notes: '$admin_notes'", 3, __DIR__ . '/debug.log');

            // Refresh student data
            $sql = "SELECT * FROM registrations WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $student = $result->fetch_assoc();
            $stmt->close();

            // Refresh documents
            $sql_documents = "SELECT document_type, document_name, file_path, uploaded_at 
                             FROM documents 
                             WHERE registration_id = ? 
                             ORDER BY uploaded_at ASC";
            $stmt_documents = $conn->prepare($sql_documents);
            $stmt_documents->bind_param("i", $id);
            $stmt_documents->execute();
            $result_documents = $stmt_documents->get_result();
            if ($result_documents === false) {
                error_log("Gagal mengambil dokumen untuk ID $id: " . $conn->error, 3, __DIR__ . '/error.log');
                $documents = [];
            } else {
                $documents = $result_documents->fetch_all(MYSQLI_ASSOC);
            }
            $stmt_documents->close();

            // Refresh status history
            $sql_history = "SELECT id, status, notes, changed_at, changed_by 
                            FROM status_history 
                            WHERE registration_id = ? 
                            ORDER BY changed_at ASC";
            $stmt_history = $conn->prepare($sql_history);
            $stmt_history->bind_param("i", $id);
            $stmt_history->execute();
            $result_history = $stmt_history->get_result();
            $status_history = $result_history->fetch_all(MYSQLI_ASSOC);
            $stmt_history->close();

            // Redirect to prevent form resubmission
            header("Location: student_detail.php?id=$id");
            $conn->close();
            exit;
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $_SESSION['error'] = "Gagal memperbarui data.";
            error_log("Update failed for ID $id: " . $e->getMessage(), 3, __DIR__ . '/error.log');
        }
    }
}

$conn->close();

// Format date to Indonesian
function formatTanggalIndonesia($tanggal) {
    if (!$tanggal) return '-';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $split = explode('-', $tanggal);
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

// Status badge class
function getStatusBadgeClass($status) {
    return match($status) {
        'Terverifikasi' => 'bg-blue-900 text-blue-300',
        'Menunggu Verifikasi' => 'bg-yellow-900 text-yellow-300',
        'Ditolak' => 'bg-red-900 text-red-300',
        'Diterima' => 'bg-green-900 text-green-300',
        default => 'bg-gray-700 text-gray-300'
    };
}

// Timeline dot class
function getTimelineDotClass($status) {
    return match($status) {
        'Terverifikasi' => 'bg-blue-900',
        'Menunggu Verifikasi' => 'bg-yellow-900',
        'Ditolak' => 'bg-red-900',
        'Diterima' => 'bg-green-900',
        default => 'bg-gray-700'
    };
}

// Status icon
function getStatusIcon($status) {
    return match($status) {
        'Terverifikasi' => 'fa-check-circle',
        'Menunggu Verifikasi' => 'fa-clock',
        'Ditolak' => 'fa-times-circle',
        'Diterima' => 'fa-user-check',
        default => 'fa-question-circle'
    };
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
    <title>Detail Pendaftar - STM Gotham City</title>
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
        }
        .dashboard-card {
            background: #1e293b;
            border-radius: 1rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        .status-timeline .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .status-timeline .line {
            height: 2px;
        }
        .photo-placeholder {
            background-color: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            width: 150px;
            height: 150px;
            margin: 0 auto;
        }
        .info-item {
            border-bottom: 1px solid #334155;
            padding: 0.75rem 0;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .document-card {
            transition: all 0.3s ease;
        }
        .document-card:hover {
            transform: translateY(-5px);
        }
        .delete-btn {
            transition: all 0.3s ease;
        }
        .delete-btn:hover {
            background-color: #dc2626;
        }
        .print-only {
            display: none;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block;
            }
            .dashboard-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            body {
                background-color: white;
                color: black;
            }
        }
    </style>
</head>
<body>
    <div class="container mx-auto p-4 md:p-6">
        <!-- Header dengan breadcrumbs -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <div>
                <nav class="flex mb-4" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="admin_dashboard.php" class="inline-flex items-center text-sm font-medium text-blue-400 hover:text-blue-300">
                                <i class="fas fa-home mr-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-500 mx-2 text-sm"></i>
                                <span class="text-sm font-medium text-gray-400">Detail Pendaftar</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <h1 class="text-2xl md:text-3xl font-bold">Detail Pendaftar</h1>
            </div>
            
            <div class="flex space-x-2 mt-4 md:mt-0 no-print">
                <a href="admin_dashboard.php" class="px-3 py-2 bg-gray-600 hover:bg-gray-700 rounded-lg transition flex items-center text-sm">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali
                </a>
                <button onclick="window.print()" class="px-3 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg transition flex items-center text-sm">
                    <i class="fas fa-print mr-2"></i> Cetak
                </button>
                <a href="edit_student.php?id=<?php echo $id; ?>" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition flex items-center text-sm">
                    <i class="fas fa-edit mr-2"></i> Edit
                </a>
            </div>
        </div>
        
        <!-- Pesan sukses/error -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-900 text-green-300 p-4 rounded-lg mb-6 flex items-center justify-between">
                <div><i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['success']); ?></div>
                <button onclick="this.parentElement.style.display='none'" class="text-green-300 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-900 text-red-300 p-4 rounded-lg mb-6 flex items-center justify-between">
                <div><i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['error']); ?></div>
                <button onclick="this.parentElement.style.display='none'" class="text-red-300 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Header info pendaftar -->
        <div class="dashboard-card p-6 mb-6 print-friendly">
            <div class="flex flex-col md:flex-row md:space-x-6">
                <!-- Foto profil dan status -->
                <div class="md:w-1/4 flex flex-col items-center mb-6 md:mb-0">
                    <div class="photo-placeholder mb-4">
                        <i class="fas fa-user text-5xl text-gray-400"></i>
                    </div>
                    <div class="text-center">
                        <h2 class="text-xl font-bold"><?php echo htmlspecialchars($student['full_name']); ?></h2>
                        <p class="text-gray-400 text-sm mt-1 mb-3">ID Pendaftar: #<?php echo str_pad($student['id'], 5, '0', STR_PAD_LEFT); ?></p>
                        <div class="inline-flex items-center px-3 py-1 rounded-full <?php echo getStatusBadgeClass($student['status']); ?>">
                            <i class="fas <?php echo getStatusIcon($student['status']); ?> mr-2"></i>
                            <?php echo htmlspecialchars($student['status']); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Info dasar -->
                <div class="md:w-3/4">
                    <h3 class="text-lg font-semibold mb-4 border-b border-gray-700 pb-2">Informasi Dasar</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <div class="info-item">
                            <p class="text-gray-400 text-sm">Email</p>
                            <p class="font-medium"><?php echo htmlspecialchars($student['email']); ?></p>
                        </div>
                        <div class="info-item">
                            <p class="text-gray-400 text-sm">No. Telepon</p>
                            <p class="font-medium"><?php echo htmlspecialchars($student['phone']); ?></p>
                        </div>
                        <div class="info-item">
                            <p class="text-gray-400 text-sm">Tanggal Lahir</p>
                            <p class="font-medium"><?php echo formatTanggalIndonesia($student['birth_date']); ?></p>
                        </div>
                        <div class="info-item">
                            <p class="text-gray-400 text-sm">Program Studi</p>
                            <p class="font-medium"><?php echo htmlspecialchars($student['major_id'] == 1 ? 'Teknik Komputer dan Jaringan' : ($student['major_id'] == 2 ? 'Teknik Otomotif' : 'Multimedia')); ?></p>
                        </div>
                        <div class="info-item md:col-span-2">
                            <p class="text-gray-400 text-sm">Alamat</p>
                            <p class="font-medium"><?php echo htmlspecialchars($student['address']); ?></p>
                        </div>
                        <div class="info-item">
                            <p class="text-gray-400 text-sm">Tanggal Pendaftaran</p>
                            <p class="font-medium"><?php echo date('d/m/Y H:i', strtotime($student['created_at'])); ?></p>
                        </div>
                        <div class="info-item">
                            <p class="text-gray-400 text-sm">Terakhir Diperbarui</p>
                            <p class="font-medium"><?php echo isset($student['updated_at']) && $student['updated_at'] ? date('d/m/Y H:i', strtotime($student['updated_at'])) : '-'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabs untuk navigasi informasi detail -->
        <div class="mb-6 no-print">
            <div class="flex flex-wrap border-b border-gray-700">
                <button class="tab-button px-4 py-2 text-blue-400 border-b-2 border-blue-500" data-tab="detail">
                    <i class="fas fa-info-circle mr-2"></i> Detail Lengkap
                </button>
                <button class="tab-button px-4 py-2 text-gray-400 hover:text-blue-400" data-tab="documents">
                    <i class="fas fa-file-alt mr-2"></i> Dokumen
                </button>
                <button class="tab-button px-4 py-2 text-gray-400 hover:text-blue-400" data-tab="status">
                    <i class="fas fa-tasks mr-2"></i> Status & Catatan
                </button>
            </div>
        </div>
        
        <!-- Tab Content: Detail Lengkap -->
        <div id="detail-tab" class="tab-content active">
            <div class="dashboard-card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Data Lengkap Pendaftar</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Informasi Pribadi -->
                    <div>
                        <h4 class="font-semibold mb-3 border-b border-gray-700 pb-2">Informasi Pribadi</h4>
                        <div class="space-y-3">
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">Nama Lengkap</p>
                                <p class="font-medium"><?php echo htmlspecialchars($student['full_name']); ?></p>
                            </div>
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">Jenis Kelamin</p>
                                <p class="font-medium"><?php echo isset($student['gender']) ? htmlspecialchars($student['gender']) : '-'; ?></p>
                            </div>
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">Tempat Lahir</p>
                                <p class="font-medium"><?php echo isset($student['birth_place']) ? htmlspecialchars($student['birth_place']) : '-'; ?></p>
                            </div>
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">NIK</p>
                                <p class="font-medium"><?php echo isset($student['nik']) ? htmlspecialchars($student['nik']) : '-'; ?></p>
                            </div>
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">Agama</p>
                                <p class="font-medium"><?php echo isset($student['religion']) ? htmlspecialchars($student['religion']) : '-'; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informasi Kontak & Pendidikan -->
                    <div>
                        <h4 class="font-semibold mb-3 border-b border-gray-700 pb-2">Kontak & Pendidikan</h4>
                        <div class="space-y-3">
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">Email</p>
                                <p class="font-medium"><?php echo htmlspecialchars($student['email']); ?></p>
                            </div>
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">No. Telepon</p>
                                <p class="font-medium"><?php echo htmlspecialchars($student['phone']); ?></p>
                            </div>
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">Asal Sekolah</p>
                                <p class="font-medium"><?php echo isset($student['previous_school']) ? htmlspecialchars($student['previous_school']) : '-'; ?></p>
                            </div>
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">NISN</p>
                                <p class="font-medium"><?php echo isset($student['nisn']) ? htmlspecialchars($student['nisn']) : '-'; ?></p>
                            </div>
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">Tahun Lulus</p>
                                <p class="font-medium"><?php echo isset($student['graduation_year']) ? htmlspecialchars($student['graduation_year']) : '-'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Informasi Orang Tua -->
                <div class="mt-6">
                    <h4 class="font-semibold mb-3 border-b border-gray-700 pb-2">Informasi Orang Tua</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h5 class="font-medium mb-2">Data Ayah</h5>
                            <div class="space-y-3">
                                <div class="info-item">
                                    <p class="text-gray-400 text-sm">Nama Ayah</p>
                                    <p class="font-medium"><?php echo isset($student['father_name']) ? htmlspecialchars($student['father_name']) : '-'; ?></p>
                                </div>
                                <div class="info-item">
                                    <p class="text-gray-400 text-sm">Pekerjaan Ayah</p>
                                    <p class="font-medium"><?php echo isset($student['father_occupation']) ? htmlspecialchars($student['father_occupation']) : '-'; ?></p>
                                </div>
                                <div class="info-item">
                                    <p class="text-gray-400 text-sm">No. HP Ayah</p>
                                    <p class="font-medium"><?php echo isset($student['father_phone']) ? htmlspecialchars($student['father_phone']) : '-'; ?></p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h5 class="font-medium mb-2">Data Ibu</h5>
                            <div class="space-y-3">
                                <div class="info-item">
                                    <p class="text-gray-400 text-sm">Nama Ibu</p>
                                    <p class="font-medium"><?php echo isset($student['mother_name']) ? htmlspecialchars($student['mother_name']) : '-'; ?></p>
                                </div>
                                <div class="info-item">
                                    <p class="text-gray-400 text-sm">Pekerjaan Ibu</p>
                                    <p class="font-medium"><?php echo isset($student['mother_occupation']) ? htmlspecialchars($student['mother_occupation']) : '-'; ?></p>
                                </div>
                                <div class="info-item">
                                    <p class="text-gray-400 text-sm">No. HP Ibu</p>
                                    <p class="font-medium"><?php echo isset($student['mother_phone']) ? htmlspecialchars($student['mother_phone']) : '-'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Alamat Lengkap -->
                <div class="mt-6">
                    <h4 class="font-semibold mb-3 border-b border-gray-700 pb-2">Alamat Lengkap</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">Alamat</p>
                                <p class="font-medium"><?php echo htmlspecialchars($student['address']); ?></p>
                            </div>
                        </div>
                        <div>
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">Kecamatan</p>
                                <p class="font-medium"><?php echo isset($student['district']) ? htmlspecialchars($student['district']) : '-'; ?></p>
                            </div>
                        </div>
                        <div>
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">Kota/Kabupaten</p>
                                <p class="font-medium"><?php echo isset($student['city']) ? htmlspecialchars($student['city']) : '-'; ?></p>
                            </div>
                        </div>
                        <div>
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">Provinsi</p>
                                <p class="font-medium"><?php echo isset($student['province']) ? htmlspecialchars($student['province']) : '-'; ?></p>
                            </div>
                        </div>
                        <div>
                            <div class="info-item">
                                <p class="text-gray-400 text-sm">Kode Pos</p>
                                <p class="font-medium"><?php echo isset($student['postal_code']) ? htmlspecialchars($student['postal_code']) : '-'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab Content: Dokumen -->
        <div id="documents-tab" class="tab-content">
            <div class="dashboard-card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Dokumen Pendaftaran</h3>
                
                <?php if (empty($documents)): ?>
                    <div class="bg-gray-800 p-8 rounded-lg text-center">
                        <i class="fas fa-file-alt text-5xl text-gray-500 mb-4"></i>
                        <p class="text-gray-400">Belum ada dokumen yang diunggah oleh pendaftar.</p>
                    </div>
                <?php else: ?>
                    <div id Deletar="documents-list" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php foreach ($documents as $doc): ?>
                            <div class="document-card bg-gray-800 p-4 rounded-lg flex flex-col items-center">
                                <?php
                                $extension = pathinfo($doc['file_path'], PATHINFO_EXTENSION);
                                $icon = 'fa-file';
                                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                                    $icon = 'fa-file-image';
                                } elseif ($extension === 'pdf') {
                                    $icon = 'fa-file-pdf';
                                } elseif (in_array($extension, ['doc', 'docx'])) {
                                    $icon = 'fa-file-word';
                                } elseif (in_array($extension, ['xls', 'xlsx'])) {
                                    $icon = 'fa-file-excel';
                                }
                                ?>
                                <i class="fas <?php echo $icon; ?> text-4xl text-blue-400 mb-3"></i>
                                <h4 class="font-medium mb-1"><?php echo htmlspecialchars($doc['document_type']); ?></h4>
                                <p class="text-sm text-gray-400 mb-3">Diunggah: <?php echo date('d/m/Y', strtotime($doc['uploaded_at'])); ?></p>
                                <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 rounded text-sm transition flex items-center">
                                    <i class="fas fa-eye mr-2"></i> Lihat Dokumen
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tab Content: Status & Catatan -->
        <div id="status-tab" class="tab-content">
            <div class="dashboard-card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Status & Catatan Admin</h3>
                
                <form action="" method="post" class="space-y-4">
                    <input type="hidden" name="update_status" value="1">
                    
                    <div>
                        <label for="status" class="block text-sm font-medium mb-1">Status Pendaftar</label>
                        <select name="status" id="status" class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2 text-white">
                            <option value="Menunggu Verifikasi" <?php echo $student['status'] === 'Menunggu Verifikasi' ? 'selected' : ''; ?>>Menunggu Verifikasi</option>
                            <option value="Terverifikasi" <?php echo $student['status'] === 'Terverifikasi' ? 'selected' : ''; ?>>Terverifikasi</option>
                            <option value="Diterima" <?php echo $student['status'] === 'Diterima' ? 'selected' : ''; ?>>Diterima</option>
                            <option value="Ditolak" <?php echo $student['status'] === 'Ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="admin_notes" class="block text-sm font-medium mb-1">Catatan Admin</label>
                        <textarea name="admin_notes" id="admin_notes" rows="4" class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2 text-white"><?php echo htmlspecialchars($student['admin_notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm flex items-center">
                            <i class="fas fa-save mr-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
                
                <!-- Timeline Status -->
                <div class="mt-6">
                    <h4 class="font-semibold mb-3 border-b border-gray-700 pb-2">Riwayat Status</h4>
                    <div class="status-timeline space-y-4">
                        <!-- Pendaftaran Dibuat -->
                        <div class="flex items-center">
                            <div class="dot bg-blue-500 mr-3"></div>
                            <div class="line bg-gray-600 flex-1"></div>
                            <div class="ml-4">
                                <p class="font-medium">Pendaftaran Dibuat</p>
                                <p class="text-sm text-gray-400"><?php echo date('d/m/Y H:i', strtotime($student['created_at'])); ?></p>
                            </div>
                        </div>
                        <!-- Status History -->
                        <?php if (!empty($status_history)): ?>
                            <?php foreach ($status_history as $history): ?>
                                <div class="flex items-center">
                                    <div class="dot <?php echo getTimelineDotClass($history['status']); ?> mr-3"></div>
                                    <div class="line bg-gray-600 flex-1"></div>
                                    <div class="ml-4 flex-1">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <p class="font-medium">Status: <?php echo htmlspecialchars($history['status']); ?></p>
                                                <?php if (!empty($history['notes'])): ?>
                                                    <p class="text-sm text-gray-300">Catatan: <?php echo htmlspecialchars($history['notes']); ?></p>
                                                <?php endif; ?>
                                                <p class="text-sm text-gray-400"><?php echo date('d/m/Y H:i', strtotime($history['changed_at'])); ?></p>
                                                <?php if (!empty($history['changed_by'])): ?>
                                                    <p class="text-sm text-gray-400">Diubah oleh: <?php echo htmlspecialchars($history['changed_by']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <button class="delete-btn px-3 py-1 bg-red-600 hover:bg-red-700 rounded text-sm transition flex items-center no-print" onclick="confirmDelete(<?php echo $history['id']; ?>)">
                                                <i class="fas fa-trash mr-2"></i> Hapus
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif (isset($student['updated_at']) && $student['updated_at']): ?>
                            <!-- Fallback for existing data -->
                            <div class="flex items-center">
                                <div class="dot <?php echo getTimelineDotClass($student['status']); ?> mr-3"></div>
                                <div class="line bg-gray-600 flex-1"></div>
                                <div class="ml-4">
                                    <p class="font-medium">Status: <?php echo htmlspecialchars($student['status']); ?></p>
                                    <?php if (!empty($student['admin_notes'])): ?>
                                        <p class="text-sm text-gray-300">Catatan: <?php echo htmlspecialchars($student['admin_notes']); ?></p>
                                    <?php endif; ?>
                                    <p class="text-sm text-gray-400"><?php echo date('d/m/Y H:i', strtotime($student['updated_at'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Print Header -->
        <div class="print-only mb-6">
            <h1 class="text-2xl font-bold mb-2">Laporan Detail Pendaftar</h1>
            <p class="text-sm">STM Gotham City - Dicetak pada: <?php echo date('d/m/Y H:i'); ?></p>
        </div>
    </div>

    <script>
        // Tab Switching
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => {
                const tab = button.getAttribute('data-tab');
                document.querySelectorAll('.tab-button').forEach(btn => {
                    btn.classList.remove('text-blue-400', 'border-b-2', 'border-blue-500');
                    btn.classList.add('text-gray-400');
                });
                button.classList.add('text-blue-400', 'border-b-2', 'border-blue-500');
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(`${tab}-tab`).classList.add('active');
            });
        });

        // Delete Confirmation
        function confirmDelete(historyId) {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: 'Apakah Anda yakin ingin menghapus riwayat status ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('delete_status_history', '1');
                    formData.append('history_id', historyId);

                    fetch('', {
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
    </script>
</body>
</html>