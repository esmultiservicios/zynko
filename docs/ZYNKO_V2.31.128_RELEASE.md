# ZYNKO V2.31.128

## Alcance

Esta entrega se concentra en NIVO Web Chat y la Bandeja omnicanal.

### NIVO
- Respuestas directas para tienda/facturación, clínica, comparación IZZY/CAMI/ZYNKO, propósito de ZYNKO, omnicanalidad y autonomía de NIVO.
- Transferencia humana solo ante intención explícita, evitando falsos positivos al preguntar por una persona.
- Hasta 5 adjuntos por mensaje en Web Chat: seleccionar, arrastrar/soltar y pegar.
- Los adjuntos se almacenan en `public/uploads/chat/<tenant>/<conversation>/` y usan `messages.media_json`, ya existente.

### Bandeja
- Cliente 360° vuelve a abrir desde “Más opciones”.
- Acciones principales de modales ubicadas en footer.
- Resumen rápido cerrable.
- Hover estable en Inicio/Último.
- Emoji picker sin solapamiento entre categorías y emojis.
- Imágenes adjuntas visibles en modal; otros archivos conservan apertura/descarga.

## Base de datos
No requiere UPDATE DB.
