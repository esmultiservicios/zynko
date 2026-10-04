# NIVO — aislamiento multiempresa y 10 mejoras funcionales

Versión objetivo: 2.31.86

## Aislamiento verificado
Todas las consultas de reglas, conocimiento, fuentes web, IA externa y aprendizaje usan `tenant_id`. Los joins entre conocimiento, soluciones y módulos también validan pertenencia al mismo tenant.

## 10 mejoras aplicadas
1. **Aislamiento reforzado por tenant** en motor, fuentes web, UI y búsquedas de conocimiento.
2. **Contexto conversacional reciente** para entender preguntas de seguimiento.
3. **Recuperación multi-fuente**: hasta tres fuentes relevantes por respuesta.
4. **Desduplicación de conocimiento web** antes de publicarlo.
5. **Cola de aprendizaje por tenant** para preguntas no resueltas.
6. **Prioridad por recurrencia**: preguntas repetidas acumulan ocurrencias.
7. **Aprendizaje asistido por agentes**: la respuesta humana puede quedar como candidata.
8. **Aprobación humana obligatoria** antes de convertir una candidata en conocimiento.
9. **Conversación continua**: `0` permite respuestas automáticas sin límite fijo de conversación.
10. **Fallback aclaratorio**: NIVO pide más contexto antes de transferir y registra la duda para aprender.

Nada de esto convierte a NIVO en un modelo que se autoentrena sin supervisión. El aprendizaje se realiza mediante conocimiento estructurado y aprobado por cada empresa.
