# 🚀 Guía de Deployment - Sistema de Presupuestos Online ###

## 📋 Antes de Empezar

Asegúrate de tener:
- ✅ Una cuenta de GitHub
- ✅ Git instalado en tu sistema
- ✅ Acceso a un servidor web o servicio de hosting
- ✅ Base de datos MySQL configurada

## 🔧 Configuración Inicial

### 1. Configurar Base de Datos
```sql
-- Crear la base de datos
CREATE DATABASE company_presupuestos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Configurar Credenciales
```bash
# Copia el archivo de configuración
cp config.php.example config.php

# Edita config.php con tus credenciales reales
```

### 3. Variables de Entorno para Producción
Para Railway, Heroku, o similar:
```bash
DB_HOST=tu_host_mysql
DB_USER=tu_usuario
DB_PASS=tu_password
DB_NAME=company_presupuestos
DB_PORT=3306
RAILWAY_ENVIRONMENT=production
```

## 🌐 Deployment en Different Plataformas

### 🚂 Railway
1. Conecta tu repositorio de GitHub a Railway
2. Configura las variables de entorno
3. Railway detectará automáticamente PHP y configurará todo

### 🟦 Heroku
```bash
# Instalar Heroku CLI
heroku create tu-app-name
heroku addons:create cleardb:ignite
heroku config:set DB_HOST=tu_cleardb_host
heroku config:set DB_USER=tu_cleardb_user
heroku config:set DB_PASS=tu_cleardb_pass
heroku config:set DB_NAME=tu_cleardb_name
git push heroku main
```

### 🔶 cPanel/Hosting Tradicional
1. Sube los archivos via FTP
2. Crea la base de datos desde el panel de control
3. Edita `config.php` con las credenciales correctas
4. Configura permisos de carpetas

## 📁 Estructura Requerida en Servidor

```
public_html/ (o raíz del servidor)
├── config.php           # ⚠️  NO subir a GitHub
├── cotizador.php        # Página principal
├── sistema/             # Sistema core
├── assets/              # CSS, JS, imágenes
├── presupuestos/        # PDFs generados
├── uploads/             # Archivos subidos
└── logs/                # Logs del sistema (crear con permisos 755)
```

## 🔐 Permisos de Carpetas
```bash
chmod 755 presupuestos/
chmod 755 uploads/
chmod 755 logs/
chmod 755 temp/
```

## 🔄 Proceso de Deploy Automático

### GitHub Actions (Opcional)
Crea `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Production
on:
  push:
    branches: [ main ]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v2
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.0'
    - name: Deploy to server
      # Aquí tu lógica de deploy específica
```

## 🗄️ Migración de Base de Datos

### Script SQL Inicial
```sql
-- Ejecutar en tu base de datos de producción
-- Los scripts están en la carpeta sistema/sql/
SOURCE sistema/sql/estructura_inicial.sql;
SOURCE sistema/sql/datos_ejemplo.sql;
```

### Backup y Restauración
```bash
# Backup
mysqldump -u usuario -p company_presupuestos > backup.sql

# Restaurar
mysql -u usuario -p company_presupuestos < backup.sql
```

## 🔍 Verificación Post-Deploy

### Checklist de Verificación
- [ ] La página principal carga correctamente
- [ ] El cotizador funciona y calcula precios
- [ ] Se pueden generar PDFs
- [ ] El panel de administración es accesible
- [ ] Los emails se envían correctamente (si configurado)
- [ ] Las imágenes y CSS cargan correctamente

### URLs de Prueba
```
https://tu-dominio.com/                    # Página principal
https://tu-dominio.com/cotizador.php       # Cotizador
https://tu-dominio.com/sistema/admin/      # Panel admin
https://tu-dominio.com/health.php          # Estado del sistema
```

## 🐛 Troubleshooting

### Errores Comunes

#### "Error de conexión a la base de datos"
- ✅ Verificar credenciales en `config.php`
- ✅ Confirmar que la base de datos existe
- ✅ Verificar que el servidor MySQL está funcionando

#### "Archivo no encontrado"
- ✅ Verificar que todos los archivos se subieron
- ✅ Comprobar permisos de carpetas
- ✅ Verificar rutas en la configuración

#### "PDFs no se generan"
- ✅ Verificar permisos de carpeta `presupuestos/`
- ✅ Comprobar que la librería PDF está disponible
- ✅ Verificar espacio en disco

### Logs de Debug
```php
// Activar logs de debug en config.php
define('DEBUG', true);

// Los logs se guardan en logs/error.log
tail -f logs/error.log
```

## 🔄 Actualizaciones Futuras

### Proceso de Actualización
1. Hacer backup de la base de datos
2. Backup de archivos personalizados
3. Descargar nueva versión del repositorio
4. Ejecutar scripts de migración si los hay
5. Verificar funcionamiento

### Mantener Sincronizado
```bash
# En tu servidor local
git pull origin main
git push production main
```

## 📞 Soporte

Si encuentras problemas durante el deployment:
1. Revisa los logs del servidor
2. Verifica las variables de entorno
3. Comprueba la documentación específica de tu hosting
4. Consulta la sección de troubleshooting

---

💡 **Tip**: Siempre prueba primero en un entorno de staging antes de hacer deploy a producción. 
