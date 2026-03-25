<?php
require_once __DIR__ . '/app/Core/bootstrap.php';

try {
    $pdo->beginTransaction();

    // Actualizar imágenes y videos en tabla productos
    $updatesProductos = $pdo->exec("UPDATE productos 
        SET imagen = REPLACE(imagen, 'computecnicos.duckdns.org', 'computecnicos.store'),
            video_url = REPLACE(video_url, 'computecnicos.duckdns.org', 'computecnicos.store')
        WHERE imagen LIKE '%computecnicos.duckdns.org%' 
           OR video_url LIKE '%computecnicos.duckdns.org%'");

    // Actualizar galería de imágenes secundarias
    $updatesGaleria = $pdo->exec("UPDATE imagenes_producto 
        SET url_imagen = REPLACE(url_imagen, 'computecnicos.duckdns.org', 'computecnicos.store')
        WHERE url_imagen LIKE '%computecnicos.duckdns.org%'");

    $pdo->commit();
    echo "<h1>Exito!</h1>";
    echo "<p>Productos actualizados: " . (int)$updatesProductos . "</p>";
    echo "<p>Imagenes de galeria actualizadas: " . (int)$updatesGaleria . "</p>";
    echo "<p>Ya puedes borrar este archivo (fix_urls.php).</p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h1>Error</h1><p>" . $e->getMessage() . "</p>";
}
