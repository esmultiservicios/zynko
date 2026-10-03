# NIVO IA — configuración premium y seguridad

NIVO IA se configura por empresa y no debe compartir reglas, conocimiento ni permisos entre tenants.

## Controles disponibles
- activación por empresa;
- canales permitidos;
- orígenes web autorizados;
- rate limit;
- redacción de datos sensibles antes de IA externa;
- conocimiento aprobado;
- registro de consumo/decisiones;
- reglas determinísticas prioritarias;
- conocimiento manual;
- fuentes web administrables;
- exclusión de rutas privadas;
- separación por solución/módulo;
- auto-handoff;
- palabras de handoff;
- umbral de confianza;
- máximo de respuestas automáticas;
- cooldown;
- pausa con agente humano;
- cantidad de contexto conversacional;
- máximo de caracteres por pregunta;
- palabras bloqueadas;
- horario comercial;
- protección de datos y enlaces;
- fallback externo opcional.

## Orden recomendado de decisión
1. reglas explícitas;
2. conocimiento aprobado;
3. conocimiento web autorizado;
4. proveedor externo, solo si está habilitado;
5. handoff humano cuando corresponda.

## Consumo externo
El endpoint `v1/nivo/context` requiere una API key con scope `nivo:context`. Además puede validar los orígenes autorizados configurados específicamente para NIVO IA.

## Recomendaciones
Mantener fuentes de conocimiento aprobadas, excluir rutas privadas o administrativas, limitar contexto y tamaño de preguntas, y no enviar datos sensibles innecesarios a proveedores externos.
