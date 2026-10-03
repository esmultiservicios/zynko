# ZYNKO · Git Deployment en cPanel

ZYNKO está instalado con el repositorio Git directamente dentro del Document Root publicado de `zynkocloud.app`.

## `.cpanel.yml`

cPanel exige que `.cpanel.yml` exista en el **HEAD de la rama**, sea YAML válido y que cada elemento de `deployment.tasks` sea una cadena ejecutable.

La configuración de ZYNKO usa una tarea no destructiva porque `Update from Remote` ya actualiza el mismo checkout que sirve el sitio. No se usa `rsync` hacia la misma carpeta para evitar copiar el repositorio sobre sí mismo.

```yaml
---
deployment:
  tasks:
    - /bin/echo "ZYNKO HEAD listo para produccion en el Document Root administrado por cPanel"
```

### Importante sobre YAML

No agregues `:` seguido de un espacio dentro de una tarea sin citar todo el valor. YAML puede interpretar la tarea como un objeto en lugar de una cadena y cPanel la considerará inválida.

## Flujo correcto

1. Confirmar que `.cpanel.yml` está incluido en Git y commiteado.
2. Hacer `push` a la rama administrada por cPanel.
3. En cPanel, usar **Update from Remote**.
4. Verificar que no existan cambios locales pendientes.
5. Usar **Deploy HEAD Commit**.

## Si sigue apareciendo “The system cannot deploy”

cPanel muestra el mismo aviso cuando el árbol de trabajo tiene cambios sin commit. En Terminal ejecuta:

```bash
git status --short
```

Si aparecen archivos, revísalos antes de tocar nada. Los archivos propios de producción (`.env`, `.well-known/`, `.user.ini`, `php.ini`, logs, caché y respaldos de entorno) están excluidos en `.gitignore` para evitar que ensucien el repositorio cuando son archivos no rastreados.

Si uno de esos archivos ya estaba rastreado por Git desde antes, `.gitignore` no lo convierte automáticamente en ignorado; primero debe dejar de estar rastreado mediante un commit controlado. No uses `git reset --hard` en producción sin revisar qué cambios se perderían.
