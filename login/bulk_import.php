<?php
session_start();

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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
    $_SESSION['error'] = "Koneksi database gagal.";
    header("Location: exam_management.php");
    exit;
}

// Handle CSV upload
$import_errors = [];
$import_success = 0;
$import_total = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Gagal mengunggah file. Error: " . $file['error'];
    } elseif ($file['type'] !== 'text/csv' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        $_SESSION['error'] = "File harus berformat CSV.";
    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        $_SESSION['error'] = "Ukuran file tidak boleh melebihi 5MB.";
    } else {
        $file_path = $file['tmp_name'];
        
        // Read CSV
        if (($handle = fopen($file_path, 'r')) !== false) {
            // Expected headers
            $expected_headers = ['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'category', 'difficulty'];
            $headers = fgetcsv($handle); // Read header row
            
            // Validate headers
            if (!$headers || array_intersect($expected_headers, array_map('trim', $headers)) !== $expected_headers) {
                $_SESSION['error'] = "Format CSV tidak valid. Gunakan template yang disediakan.";
                fclose($handle);
                header("Location: bulk_import.php");
                exit;
            }
            
            // Prepare SQL statement
            $sql = "INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, correct_answer, exam_category, difficulty, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            
            // Process each row
            $row_number = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $row_number++;
                $import_total++;
                
                // Validate row
                if (count($row) < 6) {
                    $import_errors[] = "Baris $row_number: Data tidak lengkap.";
                    continue;
                }
                
                $question_text = trim($row[0]);
                $option_a = trim($row[1]);
                $option_b = trim($row[2]);
                $option_c = trim($row[3]);
                $option_d = trim($row[4]);
                $correct_answer = strtoupper(trim($row[5]));
                $category = isset($row[6]) ? trim($row[6]) : '';
                $difficulty = isset($row[7]) ? trim($row[7]) : 'Medium';
                
                // Validate data
                if (empty($question_text) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d)) {
                    $import_errors[] = "Baris $row_number: Teks soal atau opsi tidak boleh kosong.";
                    continue;
                }
                
                if (!in_array($correct_answer, ['A', 'B', 'C', 'D'])) {
                    $import_errors[] = "Baris $row_number: Jawaban benar harus A, B, C, atau D.";
                    continue;
                }
                
                if ($difficulty && !in_array($difficulty, ['Easy', 'Medium', 'Hard'])) {
                    $import_errors[] = "Baris $row_number: Tingkat kesulitan harus Easy, Medium, atau Hard.";
                    continue;
                }
                
                // Bind and execute
                $stmt->bind_param("ssssssss", $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $category, $difficulty);
                
                if ($stmt->execute()) {
                    $import_success++;
                } else {
                    $import_errors[] = "Baris $row_number: Gagal menyimpan soal ke database.";
                }
            }
            
            fclose($handle);
            $stmt->close();
            
            // Set session messages
            if ($import_success > 0) {
                $_SESSION['success'] = "Berhasil mengimpor $import_success dari $import_total soal.";
            }
            if (!empty($import_errors)) {
                $_SESSION['error'] = "Terdapat kesalahan saat mengimpor beberapa soal.";
                $_SESSION['import_errors'] = array_slice($import_errors, 0, 5); // Limit to 5 errors for display
            }
        } else {
            $_SESSION['error'] = "Gagal membaca file CSV.";
        }
    }
    
    header("Location: bulk_import.php");
    exit;
}

// Handle sample CSV download
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sample_questions.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'category', 'difficulty']);
    fputcsv($output, [
        'Apa ibu kota Indonesia?',
        'Jakarta',
        'Surabaya',
        'Bandung',
        'Yogyakarta',
        'A',
        'Geografi',
        'Easy'
    ]);
    fputcsv($output, [
        '2 + 2 = ?',
        '3',
        '4',
        '5',
        '6',
        'B',
        'Matematika',
        'Medium'
    ]);
    
    fclose($output);
    exit;
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
    <title>Import Soal Massal - STM Gotham City</title>
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
        
        .card {
            background: rgba(30, 41, 59, 0.8);
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        
        .slide-in {
            animation: slideIn 0.5s forwards;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .gradient-button {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            transition: all 0.3s ease;
        }
        
        .gradient-button:hover {
            background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
            transform: translateY(-2px);
        }
        
        .glow {
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .glow::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(
                circle at center,
                rgba(59, 130, 246, 0.1) 0%,
                transparent 70%
            );
            opacity: 0;
            z-index: -1;
            animation: glowPulse 3s infinite;
        }
        
        @keyframes glowPulse {
            0% { opacity: 0; }
            50% { opacity: 1; }
            100% { opacity: 0; }
        }
        
        .shimmer {
            position: relative;
            overflow: hidden;
        }
        
        .shimmer::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(
                to right,
                transparent 0%,
                rgba(255, 255, 255, 0.05) 50%,
                transparent 100%
            );
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 150%; }
        }
        
        .file-input {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
        }
        
        .file-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>

<body>
    <div class="container mx-auto px-4 py-8">
        <!-- Header with breadcrumbs -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
            <div>
                <nav class="flex mb-4" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="admin_dashboard.php" class="inline-flex items-center text-sm font-medium text-blue-400 hover:text-blue-300 transition">
                                <i class="fas fa-home mr-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-500 mx-2 text-sm"></i>
                                <a href="exam_management.php" class="text-sm font-medium text-blue-400 hover:text-blue-300 transition">Manajemen Ujian</a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-500 mx-2 text-sm"></i>
                                <span class="text-sm font-medium text-gray-400">Import Soal Massal</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <h1 class="text-3xl md:text-4xl font-bold slide-in flex items-center">
                    <i class="fas fa-file-import text-blue-400 mr-3"></i>
                    Import Soal Massal
                </h1>
                <p class="text-gray-400 mt-2 slide-in">Unggah file CSV untuk menambahkan soal ujian secara massal</p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <a href="exam_management.php" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition flex items-center text-sm">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali
                </a>
                <button id="refreshBtn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition flex items-center text-sm">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh
                </button>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <div id="notificationArea" class="mb-6">
            <?php if (isset($_SESSION['success'])): ?>
                <div id="successMsg" class="bg-green-900/60 text-green-300 p-4 rounded-lg mb-4 flex items-center justify-between slide-in backdrop-blur-sm border border-green-600/30">
                    <div class="flex items-center">
                        <div class="bg-green-600/30 p-2 rounded-full mr-3">
                            <i class="fas fa-check text-green-300"></i>
                        </div>
                        <div>
                            <h4 class="font-medium">Berhasil!</h4>
                            <p id="successText" class="text-sm opacity-90"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
                        </div>
                    </div>
                    <button onclick="document.getElementById('successMsg').style.display='none'" class="text-green-300 hover:text-white p-1">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div id="errorMsg" class="bg-red-900/60 text-red-300 p-4 rounded-lg mb-4 flex items-center justify-between slide-in backdrop-blur-sm border border-red-600/30">
                    <div class="flex items-center">
                        <div class="bg-red-600/30 p-2 rounded-full mr-3">
                            <i class="fas fa-exclamation-triangle text-red-300"></i>
                        </div>
                        <div>
                            <h4 class="font-medium">Error!</h4>
                            <p id="errorText" class="text-sm opacity-90"><?php echo htmlspecialchars($_SESSION['error']); ?></p>
                            <?php if (isset($_SESSION['import_errors'])): ?>
                                <ul class="list-disc ml-4 mt-2 text-sm">
                                    <?php foreach ($_SESSION['import_errors'] as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button onclick="document.getElementById('errorMsg').style.display='none'" class="text-red-300 hover:text-white p-1">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['error']); unset($_SESSION['import_errors']); ?>
            <?php endif; ?>
        </div>
        
        <!-- Import Form -->
        <div class="card p-6 glow slide-in">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <i class="fas fa-upload text-blue-400 mr-2"></i>
                Unggah File CSV
            </h2>
            <form action="" method="post" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label for="csv_file" class="block text-sm font-medium text-gray-300 mb-2">Pilih File CSV</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" class="file-input w-full px-4 py-2 rounded-lg text-white focus:outline-none transition" required>
                    <p class="text-xs text-gray-400 mt-2">File harus berformat CSV, maksimal 5MB. Unduh <a href="?download_sample" class="text-blue-400 hover:text-blue-300 underline">template CSV</a> untuk format yang benar.</p>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="gradient-button px-6 py-2 text-white rounded-lg transition flex items-center">
                        <i class="fas fa-upload mr-2"></i> Impor Soal
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Instructions -->
        <div class="card p-6 mt-6 slide-in">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                Petunjuk Import Soal
            </h2>
            <ul class="list-disc ml-6 text-sm text-gray-300 space-y-2">
                <li>File CSV harus memiliki kolom: <code>question_text</code>, <code>option_a</code>, <code>option_b</code>, <code>option_c</code>, <code>option_d</code>, <code>correct_answer</code>, <code>category</code>, <code>difficulty</code>.</li>
                <li><code>correct_answer</code> harus berupa 'A', 'B', 'C', atau 'D'.</li>
                <li><code>category</code> bersifat opsional, kosongkan jika tidak diperlukan.</li>
                <li><code>difficulty</code> harus 'Easy', 'Medium', atau 'Hard', default 'Medium' jika kosong.</li>
                <li>Pastikan tidak ada kolom kosong untuk <code>question_text</code> dan opsi jawaban.</li>
                <li>Gunakan <a href="?download_sample" class="text-blue-400 hover:text-blue-300">template CSV</a> untuk memastikan format yang benar.</li>
            </ul>
        </div>
    </div>

    <script>
        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', () => {
            window.location.href = 'bulk_import.php';
        });
    </script>
</body>
</html>