<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IBIS v1.0 - Smart School Finance Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            background-image: radial-gradient(#cbd5e1 0.5px, transparent 0.5px);
            background-size: 24px 24px;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        .gradient-brand {
            background: linear-gradient(180deg, #1257aa 0%, #1566c7 100%);
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .card-shadow {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
        }

        .input-focus-effect:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        @keyframes marquee {
            0% {
                transform: translateX(100%);
            }

            100% {
                transform: translateX(-100%);
            }
        }

        .animate-marquee {
            display: inline-block;
            animation: marquee 30s linear infinite;
        }

        /* Custom Scrollbar for the container if needed */
        .main-container::-webkit-scrollbar {
            width: 5px;
        }

        .main-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        /* Page Preloader */
        .preloader-overlay {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 60;
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }

        .preloader-hidden {
            opacity: 0;
            visibility: hidden;
        }

        .preloader-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.16);
            padding: 2rem 2.5rem;
            border-radius: 1.5rem;
            text-align: center;
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.25);
        }

        .preloader-dot {
            width: 0.85rem;
            height: 0.85rem;
            margin: 0 0.4rem;
            background: #ffffff;
            border-radius: 9999px;
            animation: preloaderPulse 0.9s infinite ease-in-out;
        }

        .preloader-dot:nth-child(2) {
            animation-delay: 0.15s;
        }

        .preloader-dot:nth-child(3) {
            animation-delay: 0.3s;
        }

        @keyframes preloaderPulse {

            0%,
            100% {
                transform: translateY(0);
                opacity: 0.45;
            }

            50% {
                transform: translateY(-10px);
                opacity: 1;
            }
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4 md:p-8">

    <div id="pagePreloader" class="preloader-overlay">
        <div class="preloader-card">
            <div class="flex items-center justify-center gap-3 mb-4">
                <i class="fas fa-shield-alt text-white text-2xl"></i>
                <h1 class="text-white text-xl font-bold">MiniBank sedang menyiapkan halaman...</h1>
            </div>
            <div class="flex items-center justify-center mt-4">
                <span class="preloader-dot"></span>
                <span class="preloader-dot"></span>
                <span class="preloader-dot"></span>
            </div>
            <p class="text-slate-100 text-xs mt-4 opacity-90">Tunggu sebentar, pengalaman akses cepat segera dimulai.
            </p>
        </div>
    </div>