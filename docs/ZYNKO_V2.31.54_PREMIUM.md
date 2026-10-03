# ZYNKO V2.31.54 · mejoras premium

## NIVO Web Chat · 20 controles funcionales
1. Clave única por sitio autorizado.
2. Validación estricta de dominio por installation_key.
3. CORS dinámico multiempresa.
4. Sitio principal ZYNKO automático.
5. Límite de sitios por plan.
6. Activar/desactivar instalaciones sin borrar historial.
7. Autoapertura configurable.
8. Autoapertura una sola vez por navegador.
9. Indicador de escritura.
10. Retraso de escritura configurable.
11. Respuestas rápidas.
12. Timestamps.
13. Sonido y contador de mensajes.
14. Persistencia de abierto/minimizado.
15. Caducidad de sesión del visitante.
16. Ocultar en móvil opcionalmente.
17. Rutas permitidas.
18. Rutas bloqueadas.
19. Reconexión/polling configurables.
20. Alerta en título, estado online, animación y respeto a reduced-motion.

## NIVO IA · 20 controles funcionales
1. Activación por empresa.
2. Canales permitidos por empresa.
3. Orígenes web permitidos.
4. Límite de solicitudes por minuto.
5. Redacción de datos sensibles antes de IA externa.
6. Conocimiento aprobado como fuente segura.
7. Registro de consumo/decisiones.
8. Reglas determinísticas prioritarias.
9. Conocimiento manual aprobado.
10. Conocimiento web administrable.
11. Exclusión de rutas privadas en fuentes web.
12. Separación por solución/módulo.
13. Auto-handoff configurable.
14. Palabras de handoff configurables.
15. Umbral de confianza.
16. Máximo de respuestas automáticas.
17. Cooldown entre respuestas.
18. Pausa cuando hay agente asignado.
19. Máximo de caracteres por pregunta y palabras bloqueadas.
20. Protección de datos/enlaces, horario comercial, contexto configurable y fallback externo opcional.

## Canales, Integraciones y API · 20 controles funcionales
1. Claves API secretas hasheadas.
2. Prefijo identificable sin revelar secreto.
3. Claves revocables.
4. Vencimiento configurable.
5. Scope channels:read.
6. Scope messages:send.
7. Scope messages:receive.
8. Scope nivo:context.
9. Orígenes web permitidos por clave.
10. IP/CIDR permitidos por clave.
11. HTTPS obligatorio por clave.
12. Rate limit por clave.
13. CORS/preflight compatible con navegador.
14. Idempotency-Key para evitar duplicados.
15. Validación de tenant en cada canal.
16. Validación de estado conectado antes de enviar.
17. Límites comerciales por plan.
18. Auditoría de solicitudes sin guardar el secreto.
19. Webhooks HTTPS firmados y separados de CORS.
20. Política editable desde Admin sin regenerar la clave.

## Documentación complementaria incluida en V2.31.55
- `docs/API_INTEGRATION.md`: autenticación, scopes, orígenes, IP/CIDR, HTTPS, rate limit, CORS y endpoints.
- `docs/NIVO_WEB_CHAT.md`: instalación, `installation_key`, dominio autorizado, controles premium y diagnóstico.
- `docs/NIVO_IA.md`: seguridad, conocimiento, handoff, contexto y consumo externo.
- `docs/ADMIN_SERVER_SEO.md`: `.env`, cambio de dominio, SEO, robots/sitemap y Cloudflare Turnstile.
- La documentación pública y la documentación visible en Admin también fueron actualizadas para reflejar estas políticas.
