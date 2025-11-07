<?php 
// views/includes/header.php
$pageTitle = $pageTitle ?? (defined('APP_NAME') ? APP_NAME : 'Meu Projeto');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Tailwind CSS CDN com fallback -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Verifica se Tailwind carregou -->
    <script>
        // Fallback se Tailwind não carregar
        setTimeout(() => {
            if (!window.tailwind) {
                console.error('Tailwind não carregou, aplicando fallback CSS');
                // Poderia carregar CSS alternativo aqui
            }
        }, 1000);
    </script>
    
    <!-- CSS de Fallback -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        
        /* Reset básico */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7fdf7;
            min-height: 100vh;
        }
        
        /* Classes utilitárias como fallback */
        .flex { display: flex; }
        .flex-col { flex-direction: column; }
        .flex-grow { flex-grow: 1; }
        .hidden { display: none; }
        .min-h-screen { min-height: 100vh; }
        
        /* Cores do tema */
        .bg-white { background-color: white; }
        .bg-green-600 { background-color: #38a169; }
        .bg-red-500 { background-color: #ef4444; }
        .text-white { color: white; }
        .text-gray-600 { color: #4b5563; }
        
        /* Espaçamentos */
        .p-4 { padding: 1rem; }
        .p-8 { padding: 2rem; }
        .px-4 { padding-left: 1rem; padding-right: 1rem; }
        .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        
        /* Efeitos */
        .shadow-md { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .rounded-lg { border-radius: 0.5rem; }
        
        /* Hover effects */
        .hover\:bg-green-700:hover { background-color: #2f855a; }
        .hover\:bg-red-600:hover { background-color: #dc2626; }
        .hover\:text-green-700:hover { color: #2f855a; }
        
        /* Responsividade */
        @media (min-width: 768px) {
            .md\:flex { display: flex; }
            .md\:p-8 { padding: 2rem; }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Header para usuários logados -->
        <header class="bg-white shadow-md border-b border-green-100 sticky top-0 z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex justify-between items-center">
                <div class="flex items-center space-x-6">
                    <a href="<?= BASE_URL ?>?route=dashboard" class="text-xl font-bold text-green-700 hover:text-green-900 transition duration-150">
                        <?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Dashboard') ?> 🌿
                    </a>
                    
                    <nav class="hidden md:flex space-x-4">
                        <a href="<?= BASE_URL ?>?route=home" class="nav-link <?= ($route ?? '') === 'home' ? 'active' : '' ?>">
                            <i class="fas fa-home"></i> Home
                        </a>
                        <a href="<?= BASE_URL ?>?route=dashboard" class="text-gray-600 hover:text-green-700 font-medium">
                            Minhas Plantas
                        </a>
                        <a href="<?= BASE_URL ?>?route=plant_register" class="text-gray-600 hover:text-green-700 font-medium">
                            Cadastrar Nova
                        </a>
                        <a href="<?= BASE_URL ?>?route=care_register" class="text-gray-600 hover:text-green-700 font-medium">
                            Registrar Cuidado
                        </a>

                        <a href="<?= BASE_URL ?>?route=calendar" class="text-gray-600 hover:text-green-700 font-medium">
                             Calendário
                        </a>

                    </nav>
                </div>
                
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500 hidden sm:inline">
                        Bem-vindo(a), <?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuário') ?>!
                    </span>
                    <a href="<?= BASE_URL ?>?route=logout" class="bg-red-500 hover:bg-red-600 text-white text-sm font-semibold py-1 px-3 rounded-lg transition duration-150">
                        Sair
                    </a>
                </div>
            </div>
        </header>
        
        <main class="flex-grow p-4 md:p-8">
    <?php else: ?>
        <!-- Para páginas públicas -->
        <main class="flex-grow">
    <?php endif; ?>