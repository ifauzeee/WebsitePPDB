<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log all POST data for debugging
error_log("Received POST data: " . print_r($_POST, true));

// Database connection configuration
$host = 'localhost';
$dbname = 'stm_gotham';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connection successful");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal: ' . $e->getMessage()]);
    exit;
}

// Function to sanitize input
function sanitize($data) {
    if (is_null($data) || $data === '') {
        return null;
    }
    return htmlspecialchars(strip_tags(trim($data)));
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Metode permintaan tidak valid']);
    exit;
}

// Collect and sanitize form data
$data = [
    'email' => sanitize($_POST['email'] ?? ''),
    'full_name' => sanitize($_POST['full_name'] ?? ''),
    'nisn' => sanitize($_POST['nisn'] ?? ''),
    'phone' => sanitize($_POST['phone'] ?? ''),
    'birth_date' => sanitize($_POST['birth_date'] ?? ''),
    'address' => sanitize($_POST['address'] ?? ''),
    'major_id' => sanitize($_POST['major_id'] ?? ''),
    'gender' => sanitize($_POST['gender'] ?? ''),
    'birth_place' => sanitize($_POST['birth_place'] ?? ''),
    'nik' => sanitize($_POST['nik'] ?? ''),
    'religion' => sanitize($_POST['religion'] ?? ''),
    'previous_school' => sanitize($_POST['previous_school'] ?? ''),
    'graduation_year' => sanitize($_POST['graduation_year'] ?? ''),
    'father_name' => sanitize($_POST['father_name'] ?? ''),
    'father_occupation' => sanitize($_POST['father_occupation'] ?? ''),
    'father_phone' => sanitize($_POST['father_phone'] ?? ''),
    'mother_name' => sanitize($_POST['mother_name'] ?? ''),
    'mother_occupation' => sanitize($_POST['mother_occupation'] ?? ''),
    'mother_phone' => sanitize($_POST['mother_phone'] ?? ''),
    'district' => sanitize($_POST['district'] ?? ''),
    'city' => sanitize($_POST['city'] ?? ''),
    'province' => sanitize($_POST['province'] ?? ''),
    'postal_code' => sanitize($_POST['postal_code'] ?? ''),
    'password' => $_POST['password'] ?? '',
    'confirm_password' => $_POST['confirm_password'] ?? ''
];

// Map major_id to program name
$program_map = [
    '1' => 'Teknik Komputer dan Jaringan',
    '2' => 'Teknik Otomotif',
    '3' => 'Multimedia'
];
$data['program'] = isset($program_map[$data['major_id']]) ? $program_map[$data['major_id']] : '';

// Server-side validation
$errors = [];

// Validate required fields
$required_fields = [
    'email', 'full_name', 'nisn', 'phone', 'birth_date', 'address', 'major_id', 'gender',
    'birth_place', 'nik', 'religion', 'previous_school', 'graduation_year', 'father_name',
    'father_occupation', 'mother_name', 'mother_occupation', 'district', 'city', 'province', 'postal_code'
];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        $errors[] = "Field $field wajib diisi";
    }
}

// Validate email format
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Format email tidak valid';
}

// Validate NISN (10 digits)
if (!preg_match('/^[0-9]{10}$/', $data['nisn'])) {
    $errors[] = 'NISN harus 10 digit';
}

// Validate phone number (10-15 digits)
if (!preg_match('/^[0-9]{10,15}$/', $data['phone'])) {
    $errors[] = 'Nomor telepon harus 10-15 digit';
}

// Validate NIK (16 digits)
if (!preg_match('/^[0-9]{16}$/', $data['nik'])) {
    $errors[] = 'NIK harus 16 digit';
}

// Validate graduation year (4 digits)
if (!preg_match('/^[0-9]{4}$/', $data['graduation_year'])) {
    $errors[] = 'Tahun lulus harus 4 digit';
}

// Validate postal code (5-10 digits)
if (!preg_match('/^[0-9]{5,10}$/', $data['postal_code'])) {
    $errors[] = 'Kode pos harus 5-10 digit';
}

// Validate gender
if (!in_array($data['gender'], ['Laki-laki', 'Perempuan'])) {
    $errors[] = 'Jenis kelamin tidak valid';
}

// Validate religion
$valid_religions = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];
if (!in_array($data['religion'], $valid_religions)) {
    $errors[] = 'Agama tidak valid';
}

// Validate major_id
if (!array_key_exists($data['major_id'], $program_map)) {
    $errors[] = 'Jurusan tidak valid';
}

// Validate password
if (strlen($data['password']) < 8) {
    $errors[] = 'Password harus minimal 8 karakter';
}
if ($data['password'] !== $data['confirm_password']) {
    $errors[] = 'Password dan konfirmasi password tidak cocok';
}

// Validate optional phone numbers
if (!empty($data['father_phone']) && !preg_match('/^[0-9]{10,15}$/', $data['father_phone'])) {
    $errors[] = 'Nomor HP ayah harus 10-15 digit';
}
if (!empty($data['mother_phone']) && !preg_match('/^[0-9]{10,15}$/', $data['mother_phone'])) {
    $errors[] = 'Nomor HP ibu harus 10-15 digit';
}

// Check for duplicate email, NISN, or NIK
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $stmt->execute([$data['email']]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Email sudah terdaftar';
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE nisn = ?');
    $stmt->execute([$data['nisn']]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'NISN sudah terdaftar';
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE nik = ?');
    $stmt->execute([$data['nik']]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'NIK sudah terdaftar';
    }
} catch (PDOException $e) {
    error_log("Database query error: " . $e->getMessage());
    $errors[] = 'Kesalahan kueri database: ' . $e->getMessage();
}

// If there are validation errors, return them
if (!empty($errors)) {
    error_log("Validation errors: " . implode(', ', $errors));
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Hash password
$hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

// Start transaction to ensure data integrity
try {
    $pdo->beginTransaction();

    // Insert into users table
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$data['email'], $hashed_password]);
    $user_id = $pdo->lastInsertId();
    error_log("Inserted into users table, user_id: $user_id");

    // Insert into registrations table
    $sql = 'INSERT INTO registrations (
        user_id, email, full_name, nisn, phone, birth_date, address, program, gender,
        birth_place, nik, religion, previous_school, graduation_year, father_name,
        father_occupation, father_phone, mother_name, mother_occupation, mother_phone,
        district, city, province, postal_code, major_id, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $user_id,
        $data['email'],
        $data['full_name'],
        $data['nisn'],
        $data['phone'],
        $data['birth_date'],
        $data['address'],
        $data['program'],
        $data['gender'],
        $data['birth_place'],
        $data['nik'],
        $data['religion'],
        $data['previous_school'],
        $data['graduation_year'],
        $data['father_name'],
        $data['father_occupation'],
        $data['father_phone'] ?: null,
        $data['mother_name'],
        $data['mother_occupation'],
        $data['mother_phone'] ?: null,
        $data['district'],
        $data['city'],
        $data['province'],
        $data['postal_code'],
        $data['major_id'],
        'Menunggu Verifikasi'
    ]);
    error_log("Inserted into registrations table");

    $pdo->commit();
    error_log("Database transaction committed");

    // Store session data
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_email'] = $data['email'];
    $_SESSION['user_name'] = $data['full_name'];
    error_log("Session data stored: " . print_r($_SESSION, true));

    echo json_encode(['success' => true, 'message' => 'Pendaftaran berhasil']);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Kesalahan database: ' . $e->getMessage()]);
}
?>