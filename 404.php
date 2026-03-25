<?php
http_response_code(404);
require_once __DIR__ . '/app/Core/bootstrap.php';
$page_title = 'Página no encontrada';

$extra_css = '<link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />' . "\n" .
    '<link rel="stylesheet" href="' . asset('css/index.css') . '">';

include 'includes/header.php';
?>

<main class="flex-1 bg-[#050505] text-white relative z-10 flex flex-col items-center justify-center min-h-[70vh]">
    <div class="container mx-auto px-4 py-20 text-center relative z-20">
        
        <!-- Elemento decorativo tipo Error -->
        <div class="inline-flex items-center justify-center p-4 bg-red-900/20 rounded-full mb-8 border border-red-500/30 animate-pulse">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <h1 class="text-8xl md:text-9xl font-black text-transparent bg-clip-text bg-gradient-to-b from-red-500 to-red-900 mb-4 drop-shadow-xl" style="font-family:'Orbitron', sans-serif; letter-spacing: 2px;">
            404
        </h1>
        
        <h2 class="text-3xl md:text-4xl font-bold mb-6 text-gray-100">
            Página no encontrada
        </h2>
        
        <p class="text-gray-400 mb-10 max-w-lg mx-auto text-lg">
            Parece que te has perdido en el ciberespacio. La página que buscas no existe, ha sido movida o la dirección es incorrecta.
        </p>
        
        <div class="flex flex-col sm:flex-row justify-center gap-4">
            <a href="<?php echo base_url(); ?>/" class="px-8 py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg transition-transform hover:-translate-y-1 hover:shadow-[0_0_15px_rgba(220,38,38,0.5)] flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Volver al Inicio
            </a>
            <a href="<?php echo base_url(); ?>/productos.php" class="px-8 py-3 bg-gray-800 hover:bg-gray-700 text-white font-bold rounded-lg transition-transform hover:-translate-y-1 border border-gray-700 hover:border-gray-500 flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2"/><path d="M3 11h18"/><path d="M9 11v10"/><path d="M15 11v10"/></svg>
                Ver Tienda
            </a>
        </div>
    </div>
    
    <!-- Elementos de fondo decorativos -->
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none opacity-20">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-red-600 rounded-full mix-blend-screen filter blur-[100px] opacity-20 animate-pulse"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-600 rounded-full mix-blend-screen filter blur-[100px] opacity-10 animate-pulse" style="animation-delay: 2s;"></div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
