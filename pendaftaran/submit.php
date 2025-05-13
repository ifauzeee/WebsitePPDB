<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection configuration
$host = 'localhost';
$dbname = 'stm_gotham';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Function to sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $required_fields = ['email', 'full_name', 'nisn'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = "Field $field is required";
        }
    }

    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    // Validate NISN
    if (!preg_match('/^[0-9]{10}$/', $data['nisn'])) {
        $errors[] = 'NISN must be 10 digits';
    }

    // Validate phone number
    if (!empty($data['phone']) && !preg_match('/^[0-9]{10,15}$/', $data['phone'])) {
        $errors[] = 'Phone number must be 10-15 digits';
    }

    // Validate NIK
    if (!empty($data['nik']) && !preg_match('/^[0-9]{16}$/', $data['nik'])) {
        $errors[] = 'NIK must be 16 digits';
    }

    // Validate graduation year
    if (!empty($data['graduation_year']) && !preg_match('/^[0-9]{4}$/', $data['graduation_year'])) {
        $errors[] = 'Graduation year must be a 4-digit number';
    }

    // Validate postal code
    if (!empty($data['postal_code']) && !preg_match('/^[0-9]{5,10}$/', $data['postal_code'])) {
        $errors[] = 'Postal code must be 5-10 digits';
    }

    // Validate gender
    if (!empty($data['gender']) && !in_array($data['gender'], ['Laki-laki', 'Perempuan'])) {
        $errors[] = 'Invalid gender selected';
    }

    // Validate religion
    $valid_religions = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];
    if (!empty($data['religion']) && !in_array($data['religion'], $valid_religions)) {
        $errors[] = 'Invalid religion selected';
    }

    // Validate major_id
    if (!empty($data['major_id']) && !array_key_exists($data['major_id'], $program_map)) {
        $errors[] = 'Invalid major selected';
    }

    // Validate password
    if (strlen($data['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    if ($data['password'] !== $data['confirm_password']) {
        $errors[] = 'Passwords do not match';
    }

    // Check if email, NISN, or NIK already exists
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Email already registered';
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE nisn = ?');
        $stmt->execute([$data['nisn']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'NISN already registered';
        }

        if (!empty($data['nik'])) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE nik = ?');
            $stmt->execute([$data['nik']]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'NIK already registered';
            }
        }
    } catch (PDOException $e) {
        $errors[] = 'Database query error: ' . $e->getMessage();
    }

    // If there are validation errors, return them
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }

    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

    // Start transaction
    try {
        $pdo->beginTransaction();

        // Insert into users table
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$data['email'], $hashed_password]);
        $user_id = $pdo->lastInsertId();

        // Prepare SQL for registrations table
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
            $data['phone'] ?: null,
            $data['birth_date'] ?: null,
            $data['address'] ?: null,
            $data['program'] ?: null,
            $data['gender'] ?: null,
            $data['birth_place'] ?: null,
            $data['nik'] ?: null,
            $data['religion'] ?: null,
            $data['previous_school'] ?: null,
            $data['graduation_year'] ?: null,
            $data['father_name'] ?: null,
            $data['father_occupation'] ?: null,
            $data['father_phone'] ?: null,
            $data['mother_name'] ?: null,
            $data['mother_occupation'] ?: null,
            $data['mother_phone'] ?: null,
            $data['district'] ?: null,
            $data['city'] ?: null,
            $data['province'] ?: null,
            $data['postal_code'] ?: null,
            $data['major_id'] ?: null,
            'Menunggu Verifikasi'
        ]);

        $pdo->commit();

        // Store session data
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_email'] = $data['email'];
        $_SESSION['user_name'] = $data['full_name'];

        echo json_encode(['success' => true, 'message' => 'Registration successful']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>