<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran - STM Gotham City</title>
    <link rel="icon" href="https://cdn.pixabay.com/photo/2018/10/05/21/29/bat-3726896_1280.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            clip-path: none;
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
        .delete-btn {
            background: #ef4444;
            transition: all 0.4s ease;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .delete-btn:hover {
            background: #dc2626;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.5);
        }
        .cta-btn {
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
            color: white;
            transition: all 0.4s ease;
            padding: 0.75rem 2.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .cta-btn:hover {
            background: linear-gradient(90deg, #1e40af, #3b82f6);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.5);
        }
        .progress-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin-bottom: 2.5rem;
        }
        .progress-item {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .progress-icon {
            width: 3rem;
            height: 3rem;
            background: #475569;
            color: #94a3b8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-size: 1.5rem;
            transition: all 0.4s ease;
        }
        .progress-item.active .progress-icon {
            background: #3b82f6;
            color: white;
            transform: scale(1.2);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
        }
        .progress-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: #94a3b8;
            transition: color 0.4s ease;
        }
        .progress-item.active .progress-label {
            color: #e2e8f0;
            font-weight: 600;
        }
        .btn-back {
            background: #475569;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            background: #64748b;
            transform: translateY(-2px);
        }
        .btn-edit {
            background: #6b7280;
            padding: 0.5rem 1.5rem;
            font-size: 0.9rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }
        .btn-edit:hover {
            background: #9ca3af;
            transform: translateY(-2px);
        }
        .summary-section {
            background: #0f172a;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }
        .summary-section h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .summary-item {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .summary-item strong {
            color: #3b82f6;
            min-width: 120px;
        }
        .cta-banner {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            border-radius: 1.5rem;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
            transition: transform 0.4s ease;
        }
        .cta-banner:hover {
            transform: translateY(-8px);
        }
        .fab {
            position: fixed;
            bottom: 2.5rem;
            right: 2.5rem;
            background: #3b82f6;
            color: white;
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            transition: all 0.4s ease;
            z-index: 1000;
        }
        .fab:hover {
            background: #1e40af;
            transform: scale(1.15);
        }
        .footer {
            background: linear-gradient(135deg, #0f172a, #1e3a8a);
            color: #e2e8f0;
            padding: 5rem 0;
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
        .why-choose-card {
            transition: transform 0.4s ease, background 0.4s ease;
        }
        .why-choose-card:hover {
            transform: translateY(-10px);
            background: rgba(255, 255, 255, 0.15) !important;
        }
        .program-option {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border: 2px solid #475569;
            border-radius: 0.5rem;
            background: #0f172a;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .program-option:hover {
            border-color: #3b82f6;
            background: #1e293b;
        }
        .program-option.selected {
            border-color: #3b82f6;
            background: #1e293b;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
        }
        .program-option i {
            margin-right: 1rem;
            font-size: 1.5rem;
            color: #94a3b8;
        }
        .program-option.selected i {
            color: #3b82f6;
        }
        .program-option span {
            color: #e2e8f0;
            font-size: 1rem;
            font-weight: 500;
        }
        .error-message {
            color: #ef4444;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            display: none;
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
        .border-red-500 {
            border-color: #ef4444 !important;
        }
        @media (max-width: 768px) {
            .navbar-fixed {
                background: rgba(15, 23, 42, 1);
            }
            .hero-section {
                padding: 8rem 0;
                clip-path: none;
            }
            .hero-section h1 {
                font-size: 3rem;
            }
            .form-container {
                padding: 2.5rem;
            }
            .progress-bar {
                flex-direction: column;
                gap: 2rem;
            }
            .progress-icon {
                width: 2.5rem;
                height: 2.5rem;
                font-size: 1.25rem;
            }
            .progress-label {
                font-size: 0.8rem;
            }
        }
        @media (max-width: 640px) {
            .hero-section h1 {
                font-size: 2.5rem;
            }
            .fab {
                width: 3.5rem;
                height: 3.5rem;
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
                <a href="../login/login.php" class="text-white navbar-link font-medium">Login</a>
                <a href="pendaftaran.php" class="text-white navbar-link font-medium">Pendaftaran</a>
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
                <a href="../login/login.php" class="text-white hover:text-blue-400 transition py-2 font-medium">Login</a>
                <a href="pendaftaran.php" class="text-white hover:text-blue-400 transition py-2 font-medium">Pendaftaran</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section min-h-screen flex items-center justify-center" style="position:relative; overflow:hidden; padding:0;">
        <div id="bubble-bg" style="position:absolute; inset:0; z-index:0; pointer-events:none;"></div>
        <div class="container mx-auto px-4 hero-content text-center flex flex-col items-center justify-center" style="position:relative; z-index:2; min-height:100vh;">
            <img src="https://cdn.pixabay.com/photo/2018/10/05/21/29/bat-3726896_1280.png" alt="Logo STM Gotham City" class="w-32 h-32 mx-auto mb-8 animate-float">
            <h1 class="text-5xl md:text-7xl font-extrabold mb-4 animate-pulse-slow tracking-tight">Daftar Sekarang!</h1>
            <p class="text-xl md:text-2xl opacity-90 max-w-3xl mx-auto mb-10 font-light">Bergabunglah dengan STM Gotham City dan wujudkan karir teknologi masa depan Anda!</p>
            <a href="#form" class="inline-block cta-btn">Mulai Pendaftaran</a>
        </div>
    </section>

    <section id="form" class="py-24">
        <div class="container mx-auto px-4">
            <div class="form-container max-w-4xl mx-auto p-12">
                <h2 class="text-4xl font-bold text-white mb-10 text-center tracking-wide">Formulir Pendaftaran</h2>
                <div class="progress-bar">
                    <div class="progress-item active">
                        <div class="progress-icon"><i class="fas fa-user"></i></div>
                        <span class="progress-label">Data Pribadi</span>
                    </div>
                    <div class="progress-item">
                        <div class="progress-icon"><i class="fas fa-graduation-cap"></i></div>
                        <span class="progress-label">Pendidikan & Orang Tua</span>
                    </div>
                    <div class="progress-item">
                        <div class="progress-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <span class="progress-label">Alamat</span>
                    </div>
                    <div class="progress-item">
                        <div class="progress-icon"><i class="fas fa-laptop-code"></i></div>
                        <span class="progress-label">Pilihan Jurusan</span>
                    </div>
                    <div class="progress-item">
                        <div class="progress-icon"><i class="fas fa-check-circle"></i></div>
                        <span class="progress-label">Konfirmasi</span>
                    </div>
                </div>
                <form id="registrationForm" enctype="multipart/form-data">
                    <!-- Step 1: Data Pribadi -->
                    <div class="step active" data-step="1">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <div>
                                <label for="fullName" class="block text-sm font-medium text-gray-300 mb-3">Nama Lengkap</label>
                                <input type="text" id="fullName" name="full_name" class="form-input w-full" placeholder="Masukkan nama lengkap" required>
                                <p class="error-message" id="fullName-error">Nama lengkap wajib diisi.</p>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-300 mb-3">Email</label>
                                <input type="email" id="email" name="email" class="form-input w-full" placeholder="Masukkan email" required>
                                <p class="error-message" id="email-error">Email wajib diisi dengan format yang valid.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-300 mb-3">Nomor Telepon</label>
                                <input type="tel" id="phone" name="phone" class="form-input w-full" placeholder="Masukkan nomor telepon" pattern="[0-9]{10,15}" required>
                                <p class="text-xs text-gray-400 mt-1">Contoh: 081234567890</p>
                                <p class="error-message" id="phone-error">Nomor telepon harus 10-15 digit.</p>
                            </div>
                            <div>
                                <label for="birthDate" class="block text-sm font-medium text-gray-300 mb-3">Tanggal Lahir</label>
                                <input type="date" id="birthDate" name="birth_date" class="form-input w-full" required max="<?php echo date('Y-m-d', strtotime('-14 years')); ?>">
                                <p class="error-message" id="birthDate-error">Tanggal lahir wajib diisi.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-300 mb-3">Password</label>
                                <input type="password" id="password" name="password" class="form-input w-full" placeholder="Masukkan password" minlength="8" required>
                                <p class="error-message" id="password-error">Password minimal 8 karakter.</p>
                            </div>
                            <div>
                                <label for="confirmPassword" class="block text-sm font-medium text-gray-300 mb-3">Konfirmasi Password</label>
                                <input type="password" id="confirmPassword" name="confirm_password" class="form-input w-full" placeholder="Konfirmasi password" minlength="8" required>
                                <p class="error-message" id="confirmPassword-error">Konfirmasi password wajib diisi.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <div>
                                <label for="gender" class="block text-sm font-medium text-gray-300 mb-3">Jenis Kelamin</label>
                                <select id="gender" name="gender" class="form-input w-full" required>
                                    <option value="">Pilih jenis kelamin</option>
                                    <option value="Laki-laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                                <p class="error-message" id="gender-error">Jenis kelamin wajib dipilih.</p>
                            </div>
                            <div>
                                <label for="birthPlace" class="block text-sm font-medium text-gray-300 mb-3">Tempat Lahir</label>
                                <input type="text" id="birthPlace" name="birth_place" class="form-input w-full" placeholder="Masukkan tempat lahir" required>
                                <p class="error-message" id="birthPlace-error">Tempat lahir wajib diisi.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <div>
                                <label for="nik" class="block text-sm font-medium text-gray-300 mb-3">NIK</label>
                                <input type="text" id="nik" name="nik" class="form-input w-full" placeholder="Masukkan NIK (16 digit)" pattern="[0-9]{16}" required>
                                <p class="error-message" id="nik-error">NIK harus 16 digit.</p>
                            </div>
                            <div>
                                <label for="religion" class="block text-sm font-medium text-gray-300 mb-3">Agama</label>
                                <select id="religion" name="religion" class="form-input w-full" required>
                                    <option value="">Pilih agama</option>
                                    <option value="Islam">Islam</option>
                                    <option value="Kristen">Kristen</option>
                                    <option value="Katolik">Katolik</option>
                                    <option value="Hindu">Hindu</option>
                                    <option value="Buddha">Buddha</option>
                                    <option value="Konghucu">Konghucu</option>
                                </select>
                                <p class="error-message" id="religion-error">Agama wajib dipilih.</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="button" class="submit-btn text-white next-step">Lanjut <i class="fas fa-arrow-right ml-2"></i></button>
                        </div>
                    </div>

                    <!-- Step 2: Pendidikan & Orang Tua -->
                    <div class="step hidden" data-step="2">
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-300 mb-4">Informasi Pendidikan</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <label for="previousSchool" class="block text-sm font-medium text-gray-300 mb-3">Asal Sekolah</label>
                                    <input type="text" id="previousSchool" name="previous_school" class="form-input w-full" placeholder="Masukkan nama sekolah" required>
                                    <p class="error-message" id="previousSchool-error">Asal sekolah wajib diisi.</p>
                                </div>
                                <div>
                                    <label for="nisn" class="block text-sm font-medium text-gray-300 mb-3">NISN</label>
                                    <input type="text" id="nisn" name="nisn" class="form-input w-full" placeholder="Masukkan NISN (10 digit)" pattern="[0-9]{10}" required>
                                    <p class="error-message" id="nisn-error">NISN harus 10 digit.</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4">
                                <div>
                                    <label for="graduationYear" class="block text-sm font-medium text-gray-300 mb-3">Tahun Lulus</label>
                                    <input type="text" id="graduationYear" name="graduation_year" class="form-input w-full" placeholder="Masukkan tahun lulus" pattern="[0-9]{4}" required>
                                    <p class="error-message" id="graduationYear-error">Tahun lulus harus 4 digit.</p>
                                </div>
                            </div>
                        </div>
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-300 mb-4">Informasi Orang Tua</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <label for="fatherName" class="block text-sm font-medium text-gray-300 mb-3">Nama Ayah</label>
                                    <input type="text" id="fatherName" name="father_name" class="form-input w-full" placeholder="Masukkan nama ayah" required>
                                    <p class="error-message" id="fatherName-error">Nama ayah wajib diisi.</p>
                                </div>
                                <div>
                                    <label for="fatherOccupation" class="block text-sm font-medium text-gray-300 mb-3">Pekerjaan Ayah</label>
                                    <input type="text" id="fatherOccupation" name="father_occupation" class="form-input w-full" placeholder="Masukkan pekerjaan ayah" required>
                                    <p class="error-message" id="fatherOccupation-error">Pekerjaan ayah wajib diisi.</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4">
                                <div>
                                    <label for="fatherPhone" class="block text-sm font-medium text-gray-300 mb-3">No. HP Ayah</label>
                                    <input type="tel" id="fatherPhone" name="father_phone" class="form-input w-full" placeholder="Masukkan nomor HP ayah" pattern="[0-9]{10,15}">
                                    <p class="error-message" id="fatherPhone-error">Nomor HP ayah harus 10-15 digit jika diisi.</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4">
                                <div>
                                    <label for="motherName" class="block text-sm font-medium text-gray-300 mb-3">Nama Ibu</label>
                                    <input type="text" id="motherName" name="mother_name" class="form-input w-full" placeholder="Masukkan nama ibu" required>
                                    <p class="error-message" id="motherName-error">Nama ibu wajib diisi.</p>
                                </div>
                                <div>
                                    <label for="motherOccupation" class="block text-sm font-medium text-gray-300 mb-3">Pekerjaan Ibu</label>
                                    <input type="text" id="motherOccupation" name="mother_occupation" class="form-input w-full" placeholder="Masukkan pekerjaan ibu" required>
                                    <p class="error-message" id="motherOccupation-error">Pekerjaan ibu wajib diisi.</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4">
                                <div>
                                    <label for="motherPhone" class="block text-sm font-medium text-gray-300 mb-3">No. HP Ibu</label>
                                    <input type="tel" id="motherPhone" name="mother_phone" class="form-input w-full" placeholder="Masukkan nomor HP ibu" pattern="[0-9]{10,15}">
                                    <p class="error-message" id="motherPhone-error">Nomor HP ibu harus 10-15 digit jika diisi.</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <button type="button" class="btn-back text-white prev-step"><i class="fas fa-arrow-left mr-2"></i> Kembali</button>
                            <button type="button" class="submit-btn text-white next-step">Lanjut <i class="fas fa-arrow-right ml-2"></i></button>
                        </div>
                    </div>

                    <!-- Step 3: Alamat -->
                    <div class="step hidden" data-step="3">
                        <div class="mb-8">
                            <label for="address" class="block text-sm font-medium text-gray-300 mb-3">Alamat Lengkap</label>
                            <textarea id="address" name="address" class="form-input w-full" rows="4" placeholder="Masukkan alamat lengkap" required></textarea>
                            <p class="error-message" id="address-error">Alamat lengkap wajib diisi.</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <div>
                                <label for="district" class="block text-sm font-medium text-gray-300 mb-3">Kecamatan</label>
                                <input type="text" id="district" name="district" class="form-input w-full" placeholder="Masukkan kecamatan" required>
                                <p class="error-message" id="district-error">Kecamatan wajib diisi.</p>
                            </div>
                            <div>
                                <label for="city" class="block text-sm font-medium text-gray-300 mb-3">Kota/Kabupaten</label>
                                <input type="text" id="city" name="city" class="form-input w-full" placeholder="Masukkan kota/kabupaten" required>
                                <p class="error-message" id="city-error">Kota/kabupaten wajib diisi.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <div>
                                <label for="province" class="block text-sm font-medium text-gray-300 mb-3">Provinsi</label>
                                <input type="text" id="province" name="province" class="form-input w-full" placeholder="Masukkan provinsi" required>
                                <p class="error-message" id="province-error">Provinsi wajib diisi.</p>
                            </div>
                            <div>
                                <label for="postalCode" class="block text-sm font-medium text-gray-300 mb-3">Kode Pos</label>
                                <input type="text" id="postalCode" name="postal_code" class="form-input w-full" placeholder="Masukkan kode pos" pattern="[0-9]{5,10}" required>
                                <p class="error-message" id="postalCode-error">Kode pos harus 5-10 digit.</p>
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <button type="button" class="btn-back text-white prev-step"><i class="fas fa-arrow-left mr-2"></i> Kembali</button>
                            <button type="button" class="submit-btn text-white next-step">Lanjut <i class="fas fa-arrow-right ml-2"></i></button>
                        </div>
                    </div>

                    <!-- Step 4: Pilihan Jurusan -->
                    <div class="step hidden" data-step="4">
                        <div class="mb-8">
                            <label class="block text-sm font-medium text-gray-300 mb-3">Pilih Jurusan</label>
                            <input type="hidden" id="major_id" name="major_id" required>
                            <div class="program-options">
                                <div class="program-option" data-value="1">
                                    <i class="fas fa-laptop-code"></i>
                                    <span>Teknik Komputer dan Jaringan</span>
                                </div>
                                <div class="program-option" data-value="2">
                                    <i class="fas fa-car"></i>
                                    <span>Teknik Otomotif</span>
                                </div>
                                <div class="program-option" data-value="3">
                                    <i class="fas fa-camera"></i>
                                    <span>Multimedia</span>
                                </div>
                            </div>
                            <p class="error-message" id="major-error">Harap pilih jurusan.</p>
                        </div>
                        <div class="flex justify-between">
                            <button type="button" class="btn-back text-white prev-step"><i class="fas fa-arrow-left mr-2"></i> Kembali</button>
                            <button type="button" class="submit-btn text-white next-step">Lanjut <i class="fas fa-arrow-right ml-2"></i></button>
                        </div>
                    </div>

                    <!-- Step 5: Konfirmasi -->
                    <div class="step hidden" data-step="5">
                        <div class="summary-section">
                            <h3 class="text-2xl font-semibold mb-6">Ringkasan Pendaftaran</h3>
                            <div class="summary-item">
                                <strong>Nama Lengkap:</strong> <span id="summary-fullName">-</span>
                                <button class="btn-edit text-white" data-step="1">Edit</button>
                            </div>
                            <div class="summary-item">
                                <strong>Email:</strong> <span id="summary-email">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Nomor Telepon:</strong> <span id="summary-phone">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Tanggal Lahir:</strong> <span id="summary-birthDate">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Jenis Kelamin:</strong> <span id="summary-gender">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Tempat Lahir:</strong> <span id="summary-birthPlace">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>NIK:</strong> <span id="summary-nik">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Agama:</strong> <span id="summary-religion">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Asal Sekolah:</strong> <span id="summary-previousSchool">-</span>
                                <button class="btn-edit text-white" data-step="2">Edit</button>
                            </div>
                            <div class="summary-item">
                                <strong>NISN:</strong> <span id="summary-nisn">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Tahun Lulus:</strong> <span id="summary-graduationYear">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Nama Ayah:</strong> <span id="summary-fatherName">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Pekerjaan Ayah:</strong> <span id="summary-fatherOccupation">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>No. HP Ayah:</strong> <span id="summary-fatherPhone">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Nama Ibu:</strong> <span id="summary-motherName">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Pekerjaan Ibu:</strong> <span id="summary-motherOccupation">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>No. HP Ibu:</strong> <span id="summary-motherPhone">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Alamat:</strong> <span id="summary-address">-</span>
                                <button class="btn-edit text-white" data-step="3">Edit</button>
                            </div>
                            <div class="summary-item">
                                <strong>Kecamatan:</strong> <span id="summary-district">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Kota/Kabupaten:</strong> <span id="summary-city">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Provinsi:</strong> <span id="summary-province">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Kode Pos:</strong> <span id="summary-postalCode">-</span>
                            </div>
                            <div class="summary-item">
                                <strong>Jurusan:</strong> <span id="summary-program">-</span>
                                <button class="btn-edit text-white" data-step="4">Edit</button>
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <button type="button" class="btn-back text-white prev-step"><i class="fas fa-arrow-left mr-2"></i> Kembali</button>
                            <button type="button" class="delete-btn text-white" onclick="confirmDelete()"><i class="fas fa-trash mr-2"></i> Hapus</button>
                            <button type="submit" class="submit-btn text-white">Kirim Pendaftaran <i class="fas fa-check ml-2"></i></button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- CTA Banner -->
            <div class="cta-banner max-w-4xl mx-auto mt-16 p-10 text-center text-white">
                <h3 class="text-3xl font-bold mb-4 tracking-tight">Jadilah Bagian dari Inovasi!</h3>
                <p class="text-lg mb-6 font-light">Daftar sekarang dan dapatkan kesempatan magang di perusahaan teknologi terkemuka.</p>
                <a href="#form" class="inline-block bg-white text-blue-900 px-8 py-3 rounded-lg font-semibold hover:bg-blue-100 text-sm uppercase tracking-wide">Daftar Sekarang</a>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="py-24 bg-gradient-to-r from-gray-900 to-blue-900 text-white">
        <div class="container mx-auto px-4">
            <h2 class="text-5xl font-bold text-center mb-16 tracking-tight">Mengapa Memilih STM Gotham City?</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                <div class="why-choose-card bg-white bg-opacity-10 p-8 rounded-xl text-center">
                    <i class="fas fa-laptop-code text-5xl mb-4 text-blue-400"></i>
                    <h3 class="text-2xl font-semibold mb-3">Kurikulum Modern</h3>
                    <p class="text-gray-300">Dirancang bersama industri untuk keterampilan yang relevan.</p>
                </div>
                <div class="why-choose-card bg-white bg-opacity-10 p-8 rounded-xl text-center">
                    <i class="fas fa-users text-5xl mb-4 text-blue-400"></i>
                    <h3 class="text-2xl font-semibold mb-3">Pengajar Profesional</h3>
                    <p class="text-gray-300">Tenaga pengajar berpengalaman dari dunia industri.</p>
                </div>
                <div class="why-choose-card bg-white bg-opacity-10 p-8 rounded-xl text-center">
                    <i class="fas fa-trophy text-5xl mb-4 text-blue-400"></i>
                    <h3 class="text-2xl font-semibold mb-3">Prestasi Nasional</h3>
                    <p class="text-gray-300">Juara kompetisi robotik dan pemrograman.</p>
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

    <!-- Floating Action Button -->
    <a href="#form" class="fab">
        <i class="fas fa-pen fa-lg"></i>
    </a>

    <script>
        // Mobile Menu Toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });

        // Navbar Scroll Effect
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('navbar-fixed');
            } else {
                navbar.classList.remove('navbar-fixed');
            }
        });

        // Form Step Navigation
        const steps = document.querySelectorAll('.step');
        const progressItems = document.querySelectorAll('.progress-item');
        const nextButtons = document.querySelectorAll('.next-step');
        const prevButtons = document.querySelectorAll('.prev-step');
        const editButtons = document.querySelectorAll('.btn-edit');
        let currentStep = 1;

        // Smooth scroll to form
        document.querySelectorAll('a[href^="#form"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Scroll to form top on step change
        function scrollToFormTop() {
            const formSection = document.getElementById('form');
            if (formSection) {
                formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function updateStep(step) {
            steps.forEach(s => s.classList.add('hidden'));
            progressItems.forEach(p => p.classList.remove('active'));
            document.querySelector(`.step[data-step="${step}"]`).classList.remove('hidden');
            document.querySelector(`.progress-item:nth-child(${step})`).classList.add('active');
            currentStep = step;
            updateSummary();
            scrollToFormTop();
        }

        nextButtons.forEach(button => {
            button.addEventListener('click', () => {
                if (validateStep(currentStep)) {
                    updateStep(currentStep + 1);
                }
            });
        });

        prevButtons.forEach(button => {
            button.addEventListener('click', () => {
                updateStep(currentStep - 1);
            });
        });

        editButtons.forEach(button => {
            button.addEventListener('click', () => {
                const step = parseInt(button.getAttribute('data-step'));
                updateStep(step);
            });
        });

        // Program Selection
        const programOptions = document.querySelectorAll('.program-option');
        const majorInput = document.getElementById('major_id');
        programOptions.forEach(option => {
            option.addEventListener('click', () => {
                programOptions.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                majorInput.value = option.getAttribute('data-value');
                document.getElementById('major-error').style.display = 'none';
            });
        });

        // Form Validation
        function validateStep(step) {
            console.log(`Validating step ${step}`);
            let isValid = true;
            const inputs = document.querySelectorAll(`.step[data-step="${step}"] [required]`);
            inputs.forEach(input => {
                const errorMessage = document.getElementById(`${input.id}-error`);
                if (!input.value) {
                    isValid = false;
                    input.classList.add('border-red-500');
                    if (errorMessage) errorMessage.style.display = 'block';
                    console.log(`Invalid input: ${input.id} is empty`);
                } else if (input.pattern && !new RegExp(input.pattern).test(input.value)) {
                    isValid = false;
                    input.classList.add('border-red-500');
                    if (errorMessage) errorMessage.style.display = 'block';
                    console.log(`Pattern mismatch for ${input.id}`);
                } else {
                    input.classList.remove('border-red-500');
                    if (errorMessage) errorMessage.style.display = 'none';
                }
            });

            if (step === 1) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirmPassword').value;
                if (password !== confirmPassword) {
                    isValid = false;
                    document.getElementById('confirmPassword').classList.add('border-red-500');
                    document.getElementById('confirmPassword-error').style.display = 'block';
                    Swal.fire({
                        icon: 'error',
                        title: 'Password Tidak Cocok',
                        text: 'Password dan konfirmasi password harus sama.',
                    });
                    console.log('Password mismatch');
                }
            }

            if (step === 4 && !majorInput.value) {
                isValid = false;
                document.getElementById('major-error').style.display = 'block';
                console.log('No major selected');
            }

            console.log(`Step ${step} validation result: ${isValid}`);
            return isValid;
        }

        // Update Summary
        function updateSummary() {
            const fields = [
                'fullName', 'email', 'phone', 'birthDate', 'gender', 'birthPlace', 'nik', 'religion',
                'previousSchool', 'nisn', 'graduationYear', 'fatherName', 'fatherOccupation', 'fatherPhone',
                'motherName', 'motherOccupation', 'motherPhone', 'address', 'district', 'city', 'province',
                'postalCode', 'major_id'
            ];
            fields.forEach(field => {
                const input = document.getElementById(field);
                const summary = document.getElementById(`summary-${field}`);
                if (input && summary) {
                    summary.textContent = input.value || '-';
                }
            });

            const majorId = majorInput.value;
            const programNames = {
                '1': 'Teknik Komputer dan Jaringan',
                '2': 'Teknik Otomotif',
                '3': 'Multimedia'
            };
            document.getElementById('summary-program').textContent = programNames[majorId] || '-';
        }

        // Confirm Delete
        function confirmDelete() {
            Swal.fire({
                title: 'Hapus Pendaftaran?',
                text: 'Semua data yang telah diisi akan dihapus.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#475569',
                confirmButtonText: 'Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('registrationForm').reset();
                    programOptions.forEach(opt => opt.classList.remove('selected'));
                    majorInput.value = '';
                    updateStep(1);
                    updateSummary();
                    Swal.fire('Dihapus!', 'Data pendaftaran telah dihapus.', 'success');
                }
            });
        }

        // Form Submission
        document.getElementById('registrationForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            console.log('Form submission triggered');

            if (!validateStep(currentStep)) {
                console.log(`Validation failed for step ${currentStep}`);
                Swal.fire({
                    icon: 'error',
                    title: 'Validasi Gagal',
                    text: 'Harap lengkapi semua kolom dengan benar sebelum mengirim.',
                });
                return;
            }

            const formData = new FormData(document.getElementById('registrationForm'));
            const submitButton = document.querySelector('.submit-btn[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="loading-spinner"></span> Mengirim...';

            try {
                console.log('Sending fetch request to ./submit.php');
                const response = await fetch('./submit.php', {
                    method: 'POST',
                    body: formData
                });
                console.log(`Response status: ${response.status}`);

                const result = await response.json();
                console.log('Parsed JSON:', result);

                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Pendaftaran Berhasil!',
                        text: 'Data Anda telah berhasil dikirim. Silakan login dan tunggu verifikasi.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = '../index.html';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Pendaftaran Gagal',
                        text: result.message || 'Terjadi kesalahan saat mengirim data.',
                    });
                }
            } catch (error) {
                console.error('Fetch error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Jaringan',
                    text: `Gagal terhubung ke server: ${error.message}. Silakan coba lagi.`,
                });
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = 'Kirim Pendaftaran <i class="fas fa-check ml-2"></i>';
            }
        });

        // Animasi bubble
        (function() {
            const bubbleBg = document.getElementById('bubble-bg');
            const colors = ['rgba(59,130,246,0.15)', 'rgba(16,185,129,0.12)', 'rgba(236,72,153,0.10)', 'rgba(253,224,71,0.10)'];
            for (let i = 0; i < 18; i++) {
                const bubble = document.createElement('div');
                const size = Math.random() * 80 + 40;
                bubble.style.position = 'absolute';
                bubble.style.borderRadius = '50%';
                bubble.style.opacity = Math.random() * 0.5 + 0.2;
                bubble.style.width = `${size}px`;
                bubble.style.height = `${size}px`;
                bubble.style.left = `${Math.random() * 100}%`;
                bubble.style.top = `${Math.random() * 100}%`;
                bubble.style.background = colors[Math.floor(Math.random() * colors.length)];
                bubble.style.filter = 'blur(2px)';
                bubble.style.animation = `bubbleFloat ${Math.random() * 10 + 12}s ease-in-out infinite`;
                bubbleBg.appendChild(bubble);
            }
        })();

        // Keyframes untuk bubble
        const style = document.createElement('style');
        style.innerHTML = `
        @keyframes bubbleFloat {
            0% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-60px) scale(1.08); }
            100% { transform: translateY(0) scale(1); }
        }
        `;
        document.head.appendChild(style);

        // Initialize summary
        updateSummary();
    </script>
</body>
</html>