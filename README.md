# PowerFit Gym

Sistema web sencillo para mostrar membresias, registrar clientes y permitir login con PHP y MySQL.

## Requisitos

- XAMPP instalado en `C:\xampp`
- Apache encendido
- MySQL encendido

## Como abrirlo correctamente

No uses Live Server para abrir archivos `.php`, porque mostrara el codigo PHP como texto. Usa XAMPP:

```text
http://127.0.0.1:5500/
http://127.0.0.1:5500/signup/
http://127.0.0.1:5500/login/
http://127.0.0.1:5500/admin/
```

## Base de datos

La conexion esta en `config/database.php`. Cuando se abre Login, Sign Up, Admin o Dashboard con MySQL encendido, el sistema crea automaticamente:

- Base de datos `powerfit_gym`
- Tabla `membresias`
- Tabla `usuarios`
- Tabla `pagos`
- Tabla `password_resets`
- Membresias Basica, Estandar, Premium y VIP

Tambien puedes importar manualmente `database/powerfit_gym.sql` desde phpMyAdmin.

## Abrir en XAMPP

Ejecuta:

```bat
fix_php.bat
```

Ese archivo abre la URL correcta usando Apache en el puerto `5500`.

## GitHub Pages

GitHub Pages puede publicar la parte estatica del sitio, pero no puede ejecutar `login/index.php`, `signup/index.php` ni MySQL. Por eso la version publicable esta separada en la carpeta `docs/`.

Para publicarlo en GitHub:

1. Sube el repositorio a GitHub.
2. Entra en `Settings > Pages`.
3. En `Build and deployment`, elige `Deploy from a branch`.
4. Selecciona la rama `main` y la carpeta `/docs`.
5. Guarda los cambios.

La pagina publicada mostrara la portada, programas y membresias. Login y Sign Up mostraran un aviso indicando que el registro real necesita XAMPP o un hosting con PHP y MySQL.
