# ZYNKO V2.31.116

## Objetivo
Consolidar la experiencia administrativa sin alterar la mensajería que ya funciona: estados WebSocket coherentes, controles válidos según estado, KPIs uniformes, encuestas visibles, filtros alineados y vista previa NIVO fiel al widget publicado.

## WebSocket
- “Reinicio recomendado” es un diagnóstico del mismo servicio WebSocket, no un servicio separado.
- Si está ejecutándose: Iniciar queda deshabilitado; Detener y Reiniciar quedan disponibles.
- Si está detenido: solo Iniciar queda disponible.
- Dashboard y Salud muestran la misma explicación y la misma fuente de estado.

## NIVO Web Chat
La vista previa administrativa mantiene encabezado NIVO + empresa y coloca Expandir sobre Cerrar como el widget publicado.

## UI administrativa
- KPIs de Integraciones restaurados a tarjetas premium responsive.
- Tarjetas principales de Automatizaciones con microinteracción.
- Restablecer en Bandeja alineado con los filtros.
- Gráfico de visitas más alto y equilibrado con Detalle · últimos 30 días.
- Encuestas visibles en el menú principal.

## Correo
El envío por SMTP/Graph y la recepción omnicanal son capacidades distintas. “Permitir vincular” como canal entrante requiere IMAP o una suscripción/webhook de Microsoft Graph implementada y autorizada.

## Base de datos
V2.31.116 no agrega tablas ni columnas. El paquete acumulativo conserva los cambios de V2.31.115 para instalaciones que todavía estén en V2.31.114 o anteriores.
