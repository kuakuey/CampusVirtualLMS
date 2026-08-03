# AulaVirtual — Plataforma LMS

Sistema de gestión de aprendizaje (tipo Moodle) desarrollado en **PHP + MySQL + Bootstrap 5**, listo para XAMPP.

## Requisitos

- XAMPP (Apache + MySQL/MariaDB + PHP 8+)
- Extensiones PHP: PDO, pdo_mysql, mbstring

## Instalación

1. Asegúrate de que el proyecto esté en tu servidor web.

2. Inicia **Apache** y **MySQL** desde el panel de XAMPP.

3. Importa la base de datos. Opciones:

   **Opción A — Página de instalación (recomendado en producción):**
   1. Configura `config/config.php` con tus credenciales MySQL.
   2. Abre `http://tudominio.com/instalacion.php` (no requiere login).
   3. Verifica el estado de conexión y usa **Instalación rápida** o **Actualizar tablas**.

   **Opción B — Desde terminal:**
   ```bash
   mysql -u root < sql/schema.sql
   ```

4. Si tu MySQL tiene contraseña, edita `config/config.php`:
   ```php
   define('BD_CLAVE', 'tu_password');
   ```

5. **Producción:** `URL_APP` se detecta sola según tu dominio. También puedes definir:
   ```bash
   URL_APP=https://tudominio.com
   ```

6. Abre en el navegador:
   ```
   http://localhost/AulaVirtual
   ```

## Cuenta inicial

Tras la instalación, entra con:

| Campo | Valor |
|-------|-------|
| Correo | `admin@aulavirtual.com` |
| Contraseña | `password123` |

Desde el panel de administración puedes crear docentes, estudiantes, categorías y cursos.

## URLs principales

| Página | URL |
|--------|-----|
| Instalación | `/instalacion.php` |
| Iniciar sesión | `/iniciar-sesion.php` |
| Registro | `/registrarse.php` |
| Panel | `/panel.php` |
| Cursos | `/cursos.php` |
| Curso | `/curso.php?id=1&pestaña=lecciones` |
| Catálogo | `/catalogo.php` |
| Perfil | `/perfil.php` |
| Usuarios (admin) | `/admin/usuarios.php` |
| Categorías (admin) | `/admin/categorias.php` |

## Actualizar estructura

1. Crea un archivo en `sql/migrations/` con numeración secuencial.
2. Ve a **instalacion.php → Actualizar tablas**.

## Estructura

```
AulaVirtual/
├── admin/
│   ├── usuarios.php
│   └── categorias.php
├── assets/
├── config/
│   ├── config.php
│   └── base_datos.php
├── includes/
│   ├── funciones.php
│   ├── gestor_bd.php
│   ├── encabezado.php
│   └── pie.php
├── subidas/
├── sql/migrations/
├── instalacion.php
├── iniciar-sesion.php
├── registrarse.php
├── panel.php
├── cursos.php
├── curso.php
├── leccion.php
├── catalogo.php
└── perfil.php
```

## Tecnologías

- PHP 8 (sesiones, PDO, password_hash)
- MySQL / MariaDB
- Bootstrap 5.3 + Bootstrap Icons
- Tokens CSRF en formularios
