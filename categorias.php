<?php
require_once __DIR__ . '/app/Core/bootstrap.php';
// Configuración de título y estilos adicionales
$page_title = 'Categorías - Computécnicos';
$extra_css = '<link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />';
include 'includes/header.php';
?>
<body class="bg-[#181818] min-h-screen text-white">
    <main class="container mx-auto px-4 py-12">
        <h1 class="text-4xl font-bold text-center mb-2">Categorías</h1>
        <div class="flex justify-center mb-10">
            <span class="block w-24 h-1 bg-red-600 rounded"></span>
        </div>
        <!-- Tarjetas de categorías -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="bg-[#222] rounded-xl shadow-lg p-8 flex flex-col items-center border border-[#333] hover:border-red-600 transition">
                <svg class="w-16 h-16 mb-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7h18M3 12h18M3 17h18"/></svg>
                <span class="text-2xl font-bold mb-2">Laptops</span>
                <span class="text-gray-300 text-center">Portátiles para trabajo y gaming</span>
            </div>
            <div class="bg-[#222] rounded-xl shadow-lg p-8 flex flex-col items-center border border-[#333] hover:border-red-600 transition">
                <svg class="w-16 h-16 mb-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M8 21h8"/></svg>
                <span class="text-2xl font-bold mb-2">Computadoras</span>
                <span class="text-gray-300 text-center">Equipos de escritorio y all-in-one</span>
            </div>
            <div class="bg-[#222] rounded-xl shadow-lg p-8 flex flex-col items-center border border-[#333] hover:border-red-600 transition">
                <svg class="w-16 h-16 mb-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M12 4h9"/><rect width="18" height="12" x="3" y="8" rx="2"/></svg>
                <span class="text-2xl font-bold mb-2">Componentes</span>
                <span class="text-gray-300 text-center">Procesadores, tarjetas gráficas y más</span>
            </div>
            <div class="bg-[#222] rounded-xl shadow-lg p-8 flex flex-col items-center border border-[#333] hover:border-red-600 transition">
                <svg class="w-16 h-16 mb-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10V7a5 5 0 0110 0v3"/></svg>
                <span class="text-2xl font-bold mb-2">Accesorios</span>
                <span class="text-gray-300 text-center">Periféricos y accesorios para tu equipo</span>
            </div>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
</body>
</html>