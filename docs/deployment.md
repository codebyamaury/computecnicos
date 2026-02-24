# Proceso de Implementación y Verificación

Este documento describe cómo desplegar cambios y verificar que se reflejen correctamente en Computécnicos.

## Entornos

- `dev`: detectado automáticamente en `localhost`/`127.0.0.1` (override con `APP_ENV=dev`).
- `prod`: cualquier otro host (override con `APP_ENV=prod`).

## Cache Busting

- El helper `asset(path)` añade `?v=<mtime>` a CSS/JS en `assets/` para forzar actualización de navegador/CDN.
- En `dev` se envían cabeceras `Cache-Control: no-cache, no-store, must-revalidate`, `Pragma: no-cache`, `Expires: 0`.

## Flujo de despliegue

1. Actualiza archivos en `assets/` o `php` según cambios.
2. Verifica en `status.php`:
   - Entorno detectado
   - Tabla de assets con `mtime`, `md5` y URLs con versión
3. Ejecuta smoke tests: `scripts/smoke_check.php` y revisa salida `OK`.
4. Valida visualmente `pedidos.php`, `index.php`, `productos.php`, `checkout.php`.
5. Revisa logs en `logs/app.log` (si se habilita logging adicional).

## Pruebas de regresión

- Los smoke tests comprueban presencia de selectors críticos.
- Para estilos: confirma que `.order-state-badge` y barra de estado estén presentes en `pedidos.css`.
- Para funcionalidad: verifica vaciado de carrito posterior a pago en `paypal_capture_order.php` y `paypal_response.php`.

## Permisos y accesos

- Asegúrate de que `assets/` y `logs/` sean legibles por el servidor web.
- La escritura en `logs/` es opcional; si falla, no bloquea la app.

## Rollback

- Si hay problemas de cache, limpiar caché del navegador y CDN.
- Puedes revertir el helper `asset()` para eliminar el parámetro `?v=` si se requiere.

## Referencias

- `app/Core/bootstrap.php` – entorno, helpers y cabeceras.
- `status.php` – página de diagnóstico.
- `scripts/smoke_check.php` – pruebas rápidas de regresión.