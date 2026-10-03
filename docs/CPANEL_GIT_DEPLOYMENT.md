# ZYNKO · Git Deployment en cPanel

ZYNKO puede trabajar con el repositorio administrado por cPanel directamente dentro del Document Root de `zynkocloud.app`.

## Requisitos de cPanel

Para habilitar **Deploy HEAD Commit**, cPanel exige:

1. Un archivo `.cpanel.yml` válido, guardado y confirmado en Git en la raíz del repositorio.
2. Al menos una rama local o remota.
3. Un árbol de trabajo limpio, sin cambios pendientes ni archivos no rastreados que Git considere modificaciones.

## Archivos del hosting que no deben ensuciar Git

El `.gitignore` del proyecto excluye archivos creados por cPanel o por el runtime, entre ellos:

- `.env` y secretos locales.
- `.well-known/` usado por AutoSSL / ACME.
- `.user.ini` y `php.ini` creados por el hosting.
- `error_log` y logs del servidor.
- `storage/installed.lock`.
- contenido runtime de `storage/logs`, `storage/cache` y `storage/env-backups`.

Estos archivos pueden existir en producción sin bloquear el deployment.

## Flujo recomendado

1. Hacer los cambios en el repositorio fuente.
2. Commit y push al remoto.
3. En cPanel → Git Version Control → Manage → Pull or Deploy, usar **Update from Remote**.
4. Confirmar que el estado del repositorio esté limpio.
5. Si se desea registrar el deployment en cPanel, usar **Deploy HEAD Commit**.

Como el checkout de ZYNKO ya está dentro del Document Root publicado, `.cpanel.yml` no copia el proyecto sobre sí mismo. Su tarea es deliberadamente segura y no destructiva.

## Si cPanel sigue mostrando “No uncommitted changes”

El servidor todavía tiene cambios locales en archivos rastreados o archivos no ignorados. Desde Terminal/SSH, dentro del repositorio, revisar:

```bash
git status
```

No se debe ejecutar `git reset --hard` a ciegas en producción. Primero hay que identificar el archivo modificado y decidir si debe conservarse, ignorarse o restaurarse desde Git.
