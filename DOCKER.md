# 🐳 Docker - CompuTécnicos Project

## Requisitos Previos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado
- [Docker Compose](https://docs.docker.com/compose/) (incluido en Docker Desktop)

---

## 🚀 Inicio Rápido

### 1. Configurar variables de entorno

```bash
# Copiar el archivo de ejemplo (ya viene uno preconfigurado)
cp .env.example .env

# Editar con tus credenciales reales si es necesario
```

### 2. Levantar los servicios

```bash
# Construir y levantar todos los servicios
docker-compose up -d --build

# Ver logs en tiempo real
docker-compose logs -f app
```

### 3. Acceder a la aplicación

| Servicio    | URL                          | Notas                     |
|-------------|------------------------------|---------------------------|
| **App**     | http://localhost:8080        | Aplicación principal      |
| **phpMyAdmin** | http://localhost:8081     | Solo con perfil `dev`     |

---

## 📋 Comandos Útiles

### Gestión de servicios

```bash
# Levantar servicios (en segundo plano)
docker-compose up -d

# Levantar con phpMyAdmin (modo desarrollo)
docker-compose --profile dev up -d

# Detener servicios
docker-compose down

# Detener y eliminar volúmenes (⚠️ borra datos de BD)
docker-compose down -v

# Reconstruir después de cambios en código
docker-compose up -d --build

# Ver estado de los servicios
docker-compose ps

# Ver logs de la app
docker-compose logs -f app

# Ver logs de MySQL
docker-compose logs -f db
```

### Acceso a contenedores

```bash
# Entrar al contenedor de la app
docker-compose exec app bash

# Acceder a MySQL desde línea de comandos
docker-compose exec db mysql -u computecnicos_user -pcomputecnicos_secret computecnicos

# Ejecutar un script PHP
docker-compose exec app php scripts/seed_random_products.php
```

### Gestión de base de datos

```bash
# Importar SQL manualmente
docker-compose exec -T db mysql -u root -proot_secret computecnicos < database/computecnicos_full.sql

# Exportar backup de la BD
docker-compose exec db mysqldump -u root -proot_secret computecnicos > backup_$(date +%Y%m%d).sql
```

---

## 🏗️ Estructura Docker

```
computecnicosproject/
├── Dockerfile              # Imagen de la aplicación
├── docker-compose.yml      # Orquestación de servicios
├── .dockerignore           # Archivos excluidos del build
├── .env                    # Variables de entorno (no subir a git)
├── .env.example            # Plantilla de variables
├── .htaccess               # Configuración Apache + seguridad
└── docker/
    ├── entrypoint.sh       # Script de inicialización
    └── php/
        └── custom.ini      # Configuración PHP optimizada
```

---

## ⚙️ Variables de Entorno

| Variable              | Default               | Descripción                        |
|-----------------------|-----------------------|------------------------------------|
| `APP_PORT`            | `8080`                | Puerto de la aplicación            |
| `DB_PORT`             | `3306`                | Puerto externo de MySQL            |
| `PMA_PORT`            | `8081`                | Puerto de phpMyAdmin               |
| `DB_NAME`             | `computecnicos`       | Nombre de la base de datos         |
| `DB_USER`             | `computecnicos_user`  | Usuario de la BD                   |
| `DB_PASS`             | `computecnicos_secret`| Contraseña de la BD                |
| `MYSQL_ROOT_PASSWORD` | `root_secret`         | Contraseña root de MySQL           |
| `PAYPAL_CLIENT_ID`    | -                     | Client ID de PayPal                |
| `PAYPAL_CLIENT_SECRET`| -                     | Client Secret de PayPal            |
| `PAYPAL_ENVIRONMENT`  | `sandbox`             | Entorno PayPal (`sandbox`/`live`)  |
| `FE_PROVIDER`         | `alegra`              | Proveedor de facturación           |
| `FE_SIMULATE`         | `true`                | Modo simulado de facturación       |

---

## 🚢 Despliegue en Producción

### Pasos recomendados

1. **Cambiar todas las contraseñas** en `.env`
2. **Deshabilitar phpMyAdmin** (no usar `--profile dev`)
3. **Configurar HTTPS** con un proxy reverso (Nginx/Traefik)
4. **Descomentar la redirección HTTPS** en `.htaccess`
5. **Cambiar PayPal** de `sandbox` a `live`
6. **Configurar facturación real** (`FE_SIMULATE=false`)

### Despliegue con Docker en un servidor

```bash
# En el servidor, clonar el repositorio
git clone <tu-repo> computecnicos
cd computecnicos

# Configurar variables de producción
cp .env.example .env
nano .env  # Editar con valores de producción

# Construir y levantar
docker-compose up -d --build

# Verificar que todo funcione
docker-compose ps
docker-compose logs -f app
```

### Ejemplo con proxy reverso Nginx

```nginx
server {
    listen 80;
    server_name tudominio.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name tudominio.com;

    ssl_certificate /etc/letsencrypt/live/tudominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tudominio.com/privkey.pem;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 🔧 Troubleshooting

### La app no conecta a la BD
```bash
# Verificar que el contenedor de MySQL esté healthy
docker-compose ps

# Ver logs de MySQL
docker-compose logs db

# Reiniciar MySQL
docker-compose restart db
```

### Error de permisos en uploads/
```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/uploads
docker-compose exec app chmod -R 775 /var/www/html/uploads
```

### Reconstruir desde cero
```bash
docker-compose down -v
docker-compose up -d --build
```
