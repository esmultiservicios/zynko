# ZYNKO · Git Deployment correcto en cPanel

## Arquitectura

cPanel exige que el repositorio administrado tenga:

1. `.cpanel.yml` confirmado en el HEAD.
2. Una rama local o remota.
3. Working tree limpio.

ZYNKO no debe usar el mismo directorio para el repositorio administrado y para los archivos vivos de producción. La aplicación crea o modifica `.env`, `storage/installed.lock`, uploads, logs, caché y otros archivos runtime; si cualquiera de ellos está versionado o se modifica dentro del checkout administrado, cPanel deshabilita **Deploy HEAD Commit**.

Configuración recomendada:

- Repositorio cPanel: `$HOME/repositories/zynko`
- Producción / Document Root: `$HOME/public_html/zynkocloud.app`

## .cpanel.yml incluido

El archivo de esta versión publica el checkout limpio hacia el Document Root mediante `rsync` y preserva datos propios de producción:

- `.env`
- `.well-known/`
- `.user.ini`
- `php.ini`
- `error_log`
- `public/uploads/`
- `storage/installed.lock`
- `storage/logs/`
- `storage/cache/`
- `storage/env-backups/`

El deployment usa `--delete` para retirar archivos de código eliminados del repositorio, pero los paths excluidos se conservan en producción.

## Primera configuración en cPanel

1. Mantén el sitio activo en `$HOME/public_html/zynkocloud.app`.
2. En **Git Version Control**, crea/clona el repositorio en una carpeta separada, por ejemplo `$HOME/repositories/zynko`.
3. Confirma que `.cpanel.yml` esté en la raíz del repositorio y forme parte del commit remoto.
4. Pulsa **Update from Remote**.
5. Cuando el working tree esté limpio, pulsa **Deploy HEAD Commit**.

No edites archivos dentro del repositorio cPanel. Los cambios de producción deben quedar en el Document Root o en rutas runtime excluidas.

## Diagnóstico

Desde Terminal, dentro del repositorio cPanel:

```bash
git status --short
git ls-files .cpanel.yml
git branch --show-current
```

`git status --short` debe quedar vacío. Si muestra `.env`, `storage/installed.lock`, uploads u otro archivo runtime, ese archivo fue versionado anteriormente y debe retirarse del índice en el repositorio fuente y confirmarse en un commit; agregarlo a `.gitignore` por sí solo no deja de rastrear un archivo ya versionado.
