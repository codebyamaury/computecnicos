<?php
require_once __DIR__ . '/app/Core/bootstrap.php';
// Sesión manejada por bootstrap (DB handler)
// Configuración del header compartido
$page_title = 'Servicios';
// Usamos index.css para mantener el mismo estilo futurista
$extra_css = '<link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />' . "\n" .
    '<link rel="stylesheet" href="' . asset('css/index.css') . '">' . "\n" .
    '<link rel="stylesheet" href="' . asset('css/servicios.css') . '">'; // Mantenemos servicios.css para ajustes específicos

include __DIR__ . '/includes/header.php';
?>

<main class="flex-1 bg-[#050505] text-white relative z-10">

    <!-- Servicios Grid -->
    <section id="servicios" class="container mx-auto px-4 py-16">
        <h2 class="section-title animate-slide-up">Nuestros Servicios</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">
            <!-- Servicio Técnico -->
            <div class="tech-card p-8 flex flex-col items-center text-center group animate-slide-up">
                <div class="service-icon mb-6">
                    <i data-lucide="wrench" class="w-10 h-10"></i>
                </div>
                <h3 class="card-title text-2xl mb-4">Servicio Técnico</h3>
                <p class="text-gray-400 mb-6">Diagnóstico y reparación de computadoras, laptops y dispositivos móviles.
                    Soluciones rápidas y garantizadas.</p>
                <ul class="text-left text-sm text-gray-500 space-y-2 mb-8 w-full px-4">
                    <li class="flex items-center"><span class="text-red-500 mr-2">✓</span> Reparación de Hardware</li>
                    <li class="flex items-center"><span class="text-red-500 mr-2">✓</span> Instalación de Software</li>
                    <li class="flex items-center"><span class="text-red-500 mr-2">✓</span> Limpieza Interna</li>
                </ul>
                <div class="mt-auto w-full">
                    <span class="tech-badge block w-full text-center mb-4">DIAGNÓSTICO GRATIS</span>
                    <a href="contacto.php?servicio=tecnico"
                        class="hero-btn secondary w-full block text-center relative z-20">Solicitar</a>
                </div>
            </div>

            <!-- Compra de Equipos -->
            <div class="tech-card p-8 flex flex-col items-center text-center group animate-slide-up delay-100">
                <div class="service-icon mb-6">
                    <!-- Icono: Computadora / Monitor -->
                    <i data-lucide="monitor" class="w-10 h-10"></i>
                </div>
                <h3 class="card-title text-2xl mb-4">Compra de Equipos</h3>
                <p class="text-gray-400 mb-6">Compramos tus equipos usados al mejor precio del mercado. Evaluación
                    inmediata y pago en efectivo.</p>
                <ul class="text-left text-sm text-gray-500 space-y-2 mb-8 w-full px-4">
                    <li class="flex items-center"><span class="text-red-500 mr-2">✓</span> Valoración Justa</li>
                    <li class="flex items-center"><span class="text-red-500 mr-2">✓</span> Pago Inmediato</li>
                    <li class="flex items-center"><span class="text-red-500 mr-2">✓</span> Recogida a Domicilio</li>
                </ul>
                <div class="mt-auto w-full">
                    <span class="tech-badge block w-full text-center mb-4">PAGO AL INSTANTE</span>
                    <a href="contacto.php?servicio=venta"
                        class="hero-btn secondary w-full block text-center relative z-20">Vender Equipo</a>
                </div>
            </div>

            <!-- Asesoría Técnica -->
            <div class="tech-card p-8 flex flex-col items-center text-center group animate-slide-up delay-200">
                <div class="service-icon mb-6">
                    <!-- Icono: Chat / Asesoría -->
                    <i data-lucide="message-circle" class="w-10 h-10"></i>
                </div>
                <h3 class="card-title text-2xl mb-4">Asesoría IT</h3>

                <p class="text-gray-400 mb-6">Consultoría especializada para empresas y particulares. Optimiza tu
                    infraestructura tecnológica.</p>
                <ul class="text-left text-sm text-gray-500 space-y-2 mb-8 w-full px-4">
                    <li class="flex items-center"><span class="text-red-500 mr-2">✓</span> Armado de PC Gamer</li>
                    <li class="flex items-center"><span class="text-red-500 mr-2">✓</span> Redes y Servidores</li>
                    <li class="flex items-center"><span class="text-red-500 mr-2">✓</span> Seguridad Informática</li>
                </ul>
                <div class="mt-auto w-full">
                    <span class="tech-badge block w-full text-center mb-4">PERSONALIZADO</span>
                    <a href="contacto.php?servicio=asesoria"
                        class="hero-btn secondary w-full block text-center relative z-20">Consultar</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Proceso Section -->
    <section class="py-16 bg-[#0a0a0a] border-y border-[#333]">
        <div class="container mx-auto px-4">
            <h2 class="section-title animate-slide-up">Nuestro Proceso</h2>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mt-12 relative">
                <!-- Línea conectora (Desktop) -->
                <div class="hidden md:block absolute top-12 left-0 w-full h-1 bg-[#333] -z-0"></div>

                <!-- Paso 1 -->
                <div class="relative z-10 flex flex-col items-center text-center animate-slide-up">
                    <div
                        class="w-24 h-24 bg-[#050505] border-2 border-red-600 rounded-full flex items-center justify-center text-3xl font-bold text-red-500 mb-6 shadow-[0_0_20px_rgba(255,0,0,0.3)]">
                        1</div>
                    <h3 class="text-xl font-bold text-white mb-2">Contacto</h3>
                    <p class="text-gray-400 text-sm">Cuéntanos tu problema o necesidad.</p>
                </div>

                <!-- Paso 2 -->
                <div class="relative z-10 flex flex-col items-center text-center animate-slide-up delay-100">
                    <div
                        class="w-24 h-24 bg-[#050505] border-2 border-red-600 rounded-full flex items-center justify-center text-3xl font-bold text-red-500 mb-6 shadow-[0_0_20px_rgba(255,0,0,0.3)]">
                        2</div>
                    <h3 class="text-xl font-bold text-white mb-2">Diagnóstico</h3>
                    <p class="text-gray-400 text-sm">Evaluamos el equipo sin costo.</p>
                </div>

                <!-- Paso 3 -->
                <div class="relative z-10 flex flex-col items-center text-center animate-slide-up delay-200">
                    <div
                        class="w-24 h-24 bg-[#050505] border-2 border-red-600 rounded-full flex items-center justify-center text-3xl font-bold text-red-500 mb-6 shadow-[0_0_20px_rgba(255,0,0,0.3)]">
                        3</div>
                    <h3 class="text-xl font-bold text-white mb-2">Solución</h3>
                    <p class="text-gray-400 text-sm">Reparamos con repuestos de calidad.</p>
                </div>

                <!-- Paso 4 -->
                <div class="relative z-10 flex flex-col items-center text-center animate-slide-up delay-300">
                    <div
                        class="w-24 h-24 bg-[#050505] border-2 border-red-600 rounded-full flex items-center justify-center text-3xl font-bold text-red-500 mb-6 shadow-[0_0_20px_rgba(255,0,0,0.3)]">
                        4</div>
                    <h3 class="text-xl font-bold text-white mb-2">Entrega</h3>
                    <p class="text-gray-400 text-sm">Tu equipo listo y garantizado.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="container mx-auto px-4 py-16">
        <h2 class="section-title animate-slide-up">Preguntas Frecuentes</h2>

        <div class="max-w-3xl mx-auto mt-12 space-y-4">
            <!-- FAQ Item 1 -->
            <div class="tech-card !p-0 animate-slide-up !overflow-visible">
                <button type="button"
                    class="flex items-center justify-between w-full p-6 text-left text-white font-bold hover:bg-[#1a1a1a] transition-colors relative z-50 cursor-pointer"
                    onclick="toggleServiceFaq('faq-1', this)">
                    <span>¿Cuánto tiempo demora el diagnóstico?</span>
                    <i data-lucide="chevron-down" class="w-6 h-6 transform transition-transform duration-300"></i>
                </button>
                <div id="faq-1"
                    class="hidden px-6 pb-6 text-gray-400 border-t border-[#333] pt-4 relative z-40">
                    El diagnóstico inicial suele tomar entre 24 a 48 horas hábiles, dependiendo de la complejidad del
                    problema. Te contactaremos apenas tengamos el resultado.
                </div>
            </div>

            <!-- FAQ Item 2 -->
            <div class="tech-card !p-0 animate-slide-up delay-100 !overflow-visible">
                <button type="button"
                    class="flex items-center justify-between w-full p-6 text-left text-white font-bold hover:bg-[#1a1a1a] transition-colors relative z-50 cursor-pointer"
                    onclick="toggleServiceFaq('faq-2', this)">
                    <span>¿Tienen garantía las reparaciones?</span>
                    <i data-lucide="chevron-down" class="w-6 h-6 transform transition-transform duration-300"></i>
                </button>
                <div id="faq-2"
                    class="hidden px-6 pb-6 text-gray-400 border-t border-[#333] pt-4 relative z-40">
                    Sí, todas nuestras reparaciones cuentan con una garantía de 30 a 90 días, dependiendo del tipo de
                    servicio y los repuestos utilizados.
                </div>
            </div>

            <!-- FAQ Item 3 -->
            <div class="tech-card !p-0 animate-slide-up delay-200 !overflow-visible">
                <button type="button"
                    class="flex items-center justify-between w-full p-6 text-left text-white font-bold hover:bg-[#1a1a1a] transition-colors relative z-50 cursor-pointer"
                    onclick="toggleServiceFaq('faq-3', this)">
                    <span>¿Compran equipos dañados?</span>
                    <i data-lucide="chevron-down" class="w-6 h-6 transform transition-transform duration-300"></i>
                </button>
                <div id="faq-3"
                    class="hidden px-6 pb-6 text-gray-400 border-t border-[#333] pt-4 relative z-40">
                    Sí, compramos equipos dañados o para repuestos. Tráelo a nuestra tienda para una valoración
                    gratuita.
                </div>
            </div>
        </div>
    </section>

</main>

<script>
    function toggleServiceFaq(id, btn) {
        console.log('Clicked FAQ:', id);
        var content = document.getElementById(id);
        var icon = btn.querySelector('svg');

        if (!content) {
            console.error('Content not found for id:', id);
            return;
        }

        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.classList.add('rotate-180');
        } else {
            content.classList.add('hidden');
            icon.classList.remove('rotate-180');
        }
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>

</html>