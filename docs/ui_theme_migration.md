# Guía de migración al nuevo tema (Design System)

Objetivo: aplicar un diseño moderno y profesional manteniendo compatibilidad con los estilos existentes.

## Cambios clave
- Se agregó `assets/css/theme.css` con paleta, tipografía Inter, espaciado, radios y componentes (botones, tarjetas, tablas, badges, inputs, alerts).
- Se añadió un "puente de compatibilidad" que sobrescribe los tokens globales ya usados (`--primary-color`, `--bg-dark`, `--text-white`, etc.).
- Se reordenó la carga de CSS para que `theme.css` se aplique después de los CSS específicos de cada página.
- Se retiraron utilidades Tailwind que forzaban colores en `<body>` y `<header>` para permitir que el tema controle el color y fondo.
- Se incorporaron íconos SVG (Heroicons style) en la navegación principal.

## Uso de variables
Los CSS existentes utilizan tokens como `--primary-color`, `--bg-card`, `--border-color`. El tema redefine estos valores en `:root`. No es necesario cambiar clases en los HTML.

### Tokens sobrescritos
```
--primary-color, --primary-hover
--bg-dark, --bg-darker, --bg-card, --bg-card-hover
--border-color, --text-white, --text-gray
--shadow-dark, --shadow-red, --shadow-red-hover
```

## Buenas prácticas
- Preferir variables CSS sobre colores fijos.
- Evitar utilidades Tailwind para color en elementos raíz; usar variables y clases del tema.
- Para botones y tarjetas nuevas, usar `.btn`, `.btn-primary`, `.card`.

## Procedimiento de despliegue
1. Confirmar que `includes/header.php` carga `main.css`, luego `extra_css` de cada página y finalmente `theme.css`.
2. Limpiar caché del navegador y CDN si aplica.
3. Verificar páginas clave: `index.php`, `productos.php`, `pedidos.php`, `checkout.php`.
4. Comunicar a interesados el cambio de tema y puntos de contacto.

## Rollback
- Restaurar el orden original en `header.php` y deshabilitar la redefinición de tokens en `theme.css` (comentar el bloque de compatibilidad).

## Contacto
Para nuevas pantallas, usar los componentes definidos en `theme.css` y mantener consistencia con la tipografía Inter.