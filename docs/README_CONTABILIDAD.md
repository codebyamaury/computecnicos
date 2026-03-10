# Sistema Contable Profesional - Computécnicos

## 🎯 Características Implementadas

### ✅ Backend Completado
- **Formulario de movimientos de inventario mejorado** con campos contables
- **Subida de archivos** (PDF/JPG) para soportes documentales
- **Validaciones robustas** para datos contables
- **Transacciones de base de datos** para integridad de datos
- **Reportes contables profesionales** con exportación a Excel

### 📊 Campos Contables Agregados
- **Proveedor**: Referencia al proveedor de la compra
- **Número de factura**: Soporte legal de la compra
- **Precio unitario**: Costo por unidad de compra
- **IVA**: Valor del IVA pagado
- **Retención**: Valor de retención aplicada
- **Soporte documental**: Archivo PDF/JPG de la factura (una sola imagen por producto/compra)
- **Fecha de factura**: Fecha del documento legal

### 🔧 Gestión de Soportes Documentales
- **Un solo soporte por producto/compra**: Sistema similar a las fotos de perfil
- **Actualización automática**: Si se sube un nuevo soporte, se elimina el anterior
- **Eliminación segura**: Al eliminar movimientos, se borran los archivos asociados
- **Limpieza de archivos huérfanos**: Herramienta para eliminar soportes sin referencias
- **Nomenclatura inteligente**: Archivos nombrados como `soporte_[id_producto]_[timestamp].[ext]`

### 📈 Reportes Disponibles
1. **Reporte General**: Todos los movimientos con información contable
2. **Reporte de Compras**: Solo entradas con datos de proveedores e impuestos
3. **Inventario Valorizado**: Stock actual valorizado al precio promedio
4. **Exportación Excel**: Reportes profesionales para auditoría

## 🚀 Instalación y Configuración

### 1. Actualizar Base de Datos
Instala todo en una sola importación ejecutando el SQL consolidado:

```sql
-- En MySQL (CLI) o phpMyAdmin importa:
database/computecnicos_full.sql
```

Esto crea las tablas base y activa funcionalidades: inventario, facturación electrónica, comentarios, impuestos por producto y notas crédito. Si solo necesitas la actualización mínima de inventario, puedes seguir usando `sql/sql_actualizacion_inventario.sql`.

### 2. Instalar PhpSpreadsheet (para exportación Excel)

#### Opción A: Composer (Recomendado)
```bash
# En la raíz del proyecto
composer require phpoffice/phpspreadsheet
```

#### Opción B: Descarga Manual
1. Descarga PhpSpreadsheet desde: https://github.com/PHPOffice/PhpSpreadsheet
2. Extrae en la carpeta `vendor/` del proyecto
3. Asegúrate de que existe `vendor/autoload.php`

### 3. Configurar Permisos
```bash
# Crear directorio para soportes documentales
mkdir uploads/soportes
chmod 755 uploads/soportes
```

### 4. Verificar Extensiones PHP
Asegúrate de que estas extensiones estén habilitadas en tu `php.ini`:
```ini
extension=gd
extension=zip
extension=xml
extension=mbstring
```

## 📋 Uso del Sistema

### Registrar Movimiento de Inventario
1. Ve a **Admin → Inventario**
2. Haz clic en **"+ Nuevo movimiento"**
3. Selecciona **"Entrada (compra)"** para activar campos contables
4. Completa todos los campos requeridos:
   - Producto
   - Proveedor
   - Número de factura
   - Precio unitario
   - IVA (opcional)
   - Retención (opcional)
   - Soporte documental (PDF/JPG)

### Generar Reportes Contables
1. Ve a **Admin → Reportes**
2. En la sección **"Reporte Contable Profesional"**
3. Haz clic en **"Ver Reporte Completo"**
4. Filtra por fechas y tipo de reporte
5. Exporta a Excel si es necesario

### Gestionar Soportes Documentales
1. **Ver soportes**: En la tabla de inventario, haz clic en "Ver soporte"
2. **Eliminar movimientos**: Usa el botón "Eliminar" en la tabla (borra automáticamente el soporte)
3. **Limpiar archivos huérfanos**: Ve a **Admin → Inventario → Limpiar Soportes**
4. **Actualizar soportes**: Al subir un nuevo archivo, se reemplaza automáticamente el anterior

## 🔧 Estructura de Archivos

```
admin/
├── inventario.php              # Vista principal de inventario
├── inventario_nuevo.php        # Backend para nuevos movimientos
├── inventario_eliminar.php     # Eliminación de movimientos
├── eliminar_movimiento.php     # API para eliminar movimientos
├── limpiar_soportes.php        # Herramienta de limpieza de archivos
├── reporte_contable.php        # Sistema de reportes contables
└── reportes.php               # Página principal de reportes

uploads/
└── soportes/                  # Archivos de soporte documental

sql_actualizacion_inventario.sql  # Script de actualización BD
```

## 📊 Cumplimiento Legal Colombiano

### ✅ Requisitos Cumplidos
- **Soporte documental**: Facturas y comprobantes de pago
- **Información de proveedores**: NIT, nombre, dirección
- **Control de IVA**: Registro de IVA pagado y cobrado
- **Retenciones**: Control de retenciones aplicadas
- **Inventario valorizado**: Valoración al costo promedio
- **Reportes para auditoría**: Exportación a Excel
- **Gestión de archivos**: Sistema robusto de soportes documentales

### 📋 Próximos Pasos Sugeridos
1. **Integrar con ventas**: Conectar movimientos de salida con pedidos
2. **Reporte de utilidad**: Calcular utilidad bruta y neta
3. **Conciliación bancaria**: Integrar con extractos bancarios
4. **Reportes fiscales**: Generar reportes para DIAN
5. **Backup automático**: Respaldo de soportes documentales

## 🛠️ Solución de Problemas

### Error: "Class 'PhpOffice\PhpSpreadsheet\Spreadsheet' not found"
```bash
# Instalar Composer si no está instalado
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Instalar dependencias
composer install
```

### Error: "Permission denied" al subir archivos
```bash
# Dar permisos al directorio de uploads
chmod -R 755 uploads/
chown -R www-data:www-data uploads/
```

### Error: "Extension GD not found"
```bash
# En Ubuntu/Debian
sudo apt-get install php-gd

# En CentOS/RHEL
sudo yum install php-gd

# Reiniciar Apache
sudo systemctl restart apache2
```

### Error: "Archivos huérfanos detectados"
1. Ve a **Admin → Inventario → Limpiar Soportes**
2. Revisa las estadísticas de archivos
3. Ejecuta la limpieza automática
4. Verifica que los soportes importantes estén respaldados

## 📞 Soporte

Para dudas o problemas con el sistema contable:
1. Verifica que todas las extensiones PHP estén habilitadas
2. Confirma que PhpSpreadsheet esté instalado correctamente
3. Revisa los logs de error de PHP/Apache
4. Asegúrate de que la base de datos esté actualizada
5. Usa la herramienta de limpieza de soportes si hay archivos huérfanos

---

**¡Sistema contable profesional con gestión robusta de soportes documentales listo para uso en producción!** 🎉
