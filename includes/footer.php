<footer class="mt-8 md:mt-16 pt-8 md:pt-12 pb-6 text-gray-200 w-full">
    <div class="container mx-auto px-2 sm:px-4 grid grid-cols-1 md:grid-cols-4 gap-8 md:gap-10 mb-8">
        <!-- Computécnicos -->
        <div>
            <h3 class="text-base sm:text-lg font-bold mb-2 border-b-2 border-red-600 inline-block pb-1">Computécnicos
            </h3>
            <p class="mb-4 text-xs sm:text-sm">Encuentra una gran variedad de productos tecnológicos, computadoras y
                accesorios a los mejores precios.</p>
            <div class="flex gap-3 mt-4">
                <!-- Facebook -->
                <a href="https://www.facebook.com/profile.php?id=100054648720157" target="_blank"
                    rel="noopener noreferrer" class="bg-red-700 hover:bg-red-600 rounded-full p-2">
                    <i data-lucide="facebook" class="w-5 h-5"></i>
                </a>
                <!-- Instagram -->
                <a href="https://www.instagram.com/compu_tecnicos/" target="_blank" rel="noopener noreferrer"
                    class="bg-red-700 hover:bg-red-600 rounded-full p-2">
                    <i data-lucide="instagram" class="w-5 h-5"></i>
                </a>
            </div>
        </div>
        <!-- Enlaces Rápidos -->
        <div>
            <h3 class="text-base sm:text-lg font-bold mb-2 border-b-2 border-red-600 inline-block pb-1">Enlaces Rápidos
            </h3>
            <ul class="space-y-1 mt-2">
                <li><a href="index.php" class="hover:text-red-500 transition text-sm sm:text-base">Inicio</a></li>
                <li><a href="productos.php" class="hover:text-red-500 transition text-sm sm:text-base">Productos</a>
                </li>
                <li><a href="servicios.php" class="hover:text-red-500 transition text-sm sm:text-base">Servicios</a>
                </li>
                <li><a href="contacto.php" class="hover:text-red-500 transition text-sm sm:text-base">Contacto</a></li>
            </ul>
        </div>
        <!-- Categorías -->
        <div>
            <h3 class="text-base sm:text-lg font-bold mb-2 border-b-2 border-red-600 inline-block pb-1">Categorías</h3>
            <ul class="space-y-1 mt-2">
                <li><a href="#" class="hover:text-red-500 transition text-sm sm:text-base">Laptops</a></li>
                <li><a href="#" class="hover:text-red-500 transition text-sm sm:text-base">Computadoras</a></li>
                <li><a href="#" class="hover:text-red-500 transition text-sm sm:text-base">Componentes</a></li>
                <li><a href="#" class="hover:text-red-500 transition text-sm sm:text-base">Accesorios</a></li>
            </ul>
        </div>
        <!-- Contacto -->
        <div>
            <h3 class="text-base sm:text-lg font-bold mb-2 border-b-2 border-red-600 inline-block pb-1">Contacto</h3>
            <ul class="space-y-1 mt-2 text-xs sm:text-sm">
                <li>Email: info@computecnicos.com</li>
                <li>Tel: +57 316 850 0131</li>
                <li>Dirección: Calle 123 #45-67, Ciudad</li>
            </ul>
        </div>
    </div>
    <div class="text-center text-gray-500 text-xs">&copy; 2024 Computécnicos. Todos los derechos reservados.</div>
</footer>

<?php
// Incluir modal de login global
include __DIR__ . '/login-modal.php';
?>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>