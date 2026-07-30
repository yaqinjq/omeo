<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Sedang Dalam Perbaikan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Animasi Kustom */
        .gear-spin {
            animation: spin 6s linear infinite;
            transform-origin: center;
        }
        .gear-spin-reverse {
            animation: spin-reverse 5s linear infinite;
            transform-origin: center;
        }
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
        @keyframes spin-reverse {
            100% { transform: rotate(-360deg); }
        }
        
        .fade-in-up {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s ease-out forwards;
        }
        .delay-100 { animation-delay: 0.2s; }
        .delay-200 { animation-delay: 0.4s; }
        .delay-300 { animation-delay: 0.6s; }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Latar belakang dengan pola dot (titik-titik) yang samar */
        .bg-pattern {
            background-color: #f8fafc;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>
</head>
<body class="bg-pattern min-h-screen flex items-center justify-center p-4 font-sans text-slate-800">

    <div class="max-w-2xl w-full bg-white rounded-3xl shadow-xl overflow-hidden fade-in-up">
        <!-- Bagian Banner Animasi -->
        <div class="bg-indigo-600 p-12 flex justify-center items-center relative overflow-hidden">
            <!-- Dekorasi Lingkaran Latar Belakang -->
            <div class="absolute w-64 h-64 bg-indigo-500 rounded-full mix-blend-multiply filter blur-2xl opacity-70 animate-pulse" style="top: -50px; left: -50px;"></div>
            <div class="absolute w-64 h-64 bg-purple-500 rounded-full mix-blend-multiply filter blur-2xl opacity-70 animate-pulse delay-100" style="bottom: -50px; right: -50px;"></div>

            <!-- Ilustrasi Roda Gigi (Gears) SVG -->
            <div class="relative z-10 flex items-center justify-center h-32 w-32">
                <!-- Roda Gigi Utama -->
                <svg class="w-24 h-24 text-white gear-spin absolute left-0 top-0" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.06-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.73,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.06,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.43-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.49-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                </svg>
                <!-- Roda Gigi Kecil -->
                <svg class="w-16 h-16 text-indigo-300 gear-spin-reverse absolute right-0 bottom-0 translate-x-1/4 translate-y-1/4" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.06-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.73,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.06,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.43-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.49-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                </svg>
            </div>
        </div>

        <!-- Bagian Konten Teks -->
        <div class="p-8 md:p-12 text-center">
            <span class="inline-block py-1 px-3 rounded-full bg-indigo-100 text-indigo-700 text-sm font-semibold tracking-wider mb-4 fade-in-up delay-100">MAINTENANCE MODE</span>
            
            <h1 class="text-3xl md:text-4xl font-bold text-slate-800 mb-4 fade-in-up delay-100">
                Website Sedang Dalam Perbaikan
            </h1>
            
            <p class="text-slate-600 mb-8 leading-relaxed fade-in-up delay-200 text-lg">
                Mohon maaf atas ketidaknyamanan ini. Kami sedang melakukan pembaruan sistem dan perawatan rutin untuk memberikan pengalaman yang lebih baik untuk Anda. Kami akan segera kembali dalam waktu singkat!
            </p>
            
            <div class="w-full bg-slate-100 rounded-full h-2.5 mb-8 overflow-hidden fade-in-up delay-300">
                <div class="bg-indigo-600 h-2.5 rounded-full animate-pulse" style="width: 75%"></div>
            </div>

            <div class="border-t border-slate-200 pt-6 mt-6 fade-in-up delay-300">
                <p class="text-sm text-slate-500">
                    Jika ada keperluan mendesak, silakan hubungi kami di <br>
                    <a href="mailto:support@domainanda.com" class="text-indigo-600 hover:text-indigo-800 font-medium transition-colors">support@omeo.tokio.com</a>
                </p>
            </div>
        </div>
    </div>

</body>
</html>