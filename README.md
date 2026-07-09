# AulaVirtual — Plataforma LMS

Sistema de gestión de aprendizaje (tipo Moodle) desarrollado en **PHP + MySQL + Bootstrap 5**, listo para XAMPP.

## Requisitos

- XAMPP (Apache + MySQL/MariaDB + PHP 8+)
- Extensiones PHP: PDO, pdo_mysql, mbstring

## Instalación

1. Asegúrate de que el proyecto esté en:
   ```
   /Applications/XAMPP/xamppfiles/htdocs/AulaVirtual
   ```

2. Inicia **Apache** y **MySQL** desde el panel de XAMPP.

3. Importa la base de datos. Opciones:

   **Opción A — Página de instalación (recomendado en producción):**
   1. Configura `config/config.php` con tus credenciales MySQL.
   2. Abre `http://tudominio.com/install.php` (no requiere login).
   3. Verifica el estado de conexión y usa **Instalación rápida** o **Actualizar tablas**.

   **Opción B — Desde terminal:**
   ```bash
   /Applications/XAMPP/xamppfiles/bin/mysql -u root < sql/schema.sql
   ```

4. Si tu MySQL tiene contraseña, edita `config/config.php`:
   ```php
   define('DB_PASS', 'tu_password');
   ```

5. **Producción:** `APP_URL` se detecta sola según tu dominio. Si usas proxy/CDN, define la variable de entorno:
   ```bash
   APP_URL=https://tudominio.com
   ```
   O descomenta y fija la URL manualmente en `config/config.php`.

6. Abre en el navegador:
   ```
   http://localhost/AulaVirtual
   ```

## Cuentas demo

| Rol           | Correo                      | Contraseña    |
|---------------|-----------------------------|---------------|
| Administrador | admin@aulavirtual.com       | password123   |
| Docente       | docente@aulavirtual.com     | password123   |
| Estudiante    | estudiante@aulavirtual.com  | password123   |

## Funcionalidades

### Administrador
- Panel con estadísticas globales
- Gestión de usuarios (crear, roles, activar/desactivar)
- Categorías de cursos
- Cursos, anuncios globales

### Docente
- Crear y editar cursos
- Lecciones con contenido HTML y video
- Tareas con fecha límite y puntaje
- Calificar entregas con feedback
- Foro del curso y anuncios
- Ver / retirar estudiantes

### Estudiante
- Catálogo e inscripción a cursos
- Ver lecciones y navegar el contenido
- Entregar tareas (texto + archivo)
- Participar en foros
- Ver calificaciones y anuncios
- Perfil y cambio de contraseña

## Actualizar estructura

Para agregar cambios a la BD en futuras versiones:

1. Crea un archivo en `sql/migrations/` con numeración secuencial, por ejemplo:
   ```
   sql/migrations/003_nueva_funcionalidad.sql
   ```
2. Ve a **install.php → Actualizar tablas**.

Las migraciones se registran en la tabla `schema_migrations` y no se ejecutan dos veces.

## Estructura

```
AulaVirtual/
├── admin/           # Panel de administración
├── assets/          # CSS y JS
├── config/          # Configuración y conexión DB
├── includes/        # Layout, helpers y DbManager
├── install.php      # Instalación y estado de BD (sin login)
├── sql/
│   ├── migrations/  # Migraciones versionadas
│   └── schema.sql   # Script completo (CLI)
├── uploads/         # Archivos subidos
├── login.php
├── register.php
├── dashboard.php
├── courses.php
├── course.php       # Vista principal del curso (pestañas)
├── lesson.php
├── catalog.php
├── announcements.php
└── profile.php
```

## Tecnologías

- PHP 8 (sesiones, PDO, password_hash)
- MySQL / MariaDB
- Bootstrap 5.3 + Bootstrap Icons
- CSRF tokens en formularios
