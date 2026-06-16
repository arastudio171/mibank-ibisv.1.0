<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IBIS v1.0 - Smart School Finance Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
        }

        #sidebar {
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-gradient {
            background: linear-gradient(180deg, #1257aa 0%, #1566c7 100%);
        }

        .active-nav {
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid #60a5fa;
            color: white !important;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        /* Logic for Collapsed State */
        @media (min-width: 1024px) {
            .sidebar-collapsed {
                width: 88px !important;
            }

            .sidebar-collapsed .nav-text,
            .sidebar-collapsed .sidebar-header-text,
            .sidebar-collapsed .user-info-text,
            .sidebar-collapsed .menu-category-text,
            .sidebar-collapsed .logout-text,
            .sidebar-collapsed .chevron-icon {
                display: none !important;
            }

            .sidebar-collapsed .nav-item {
                justify-content: center;
                padding-left: 0;
                padding-right: 0;
            }

            .sidebar-collapsed .active-nav {
                border-left-width: 0;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 12px;
                margin: 0 12px;
            }

            .sidebar-collapsed .header-container,
            .sidebar-collapsed .user-card-container {
                justify-content: center;
                padding-left: 0;
                padding-right: 0;
            }
        }

        /* Mobile specific */
        @media (max-width: 1023px) {
            #sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                bottom: 0;
                z-index: 100;
                width: 280px;
                transition: left 0.3s ease;
            }

            #sidebar.mobile-open {
                left: 0;
            }
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
    </style>
</head>

<body class="overflow-hidden">