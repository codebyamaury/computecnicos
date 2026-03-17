# Assets

Lugar recomendado para archivos estáticos del proyecto:

- `assets/css/` → hojas de estilos.
- `assets/js/` → scripts.
- `assets/img/` → imágenes estáticas.

Estado actual (incremental):
- Las carpetas `css/` y `js/` siguen en la raíz para no romper rutas.
- Cuando migremos, actualizaremos referencias con un helper `asset()`.