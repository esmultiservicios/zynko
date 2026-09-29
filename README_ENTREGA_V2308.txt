ZYNKO V2.30.8 - WHATSAPP SIN APERTURA DUPLICADA

Correccion aplicada sobre V2.30.7:
- Se elimina la interceptacion JavaScript que abria WhatsApp manualmente y podia provocar dos navegaciones.
- Se mantiene un unico enlace nativo con target _blank.
- Se cambia el destino al enlace oficial wa.me para un comportamiento mas directo en escritorio, tablet y movil.
- Se conserva el mensaje prellenado configurado desde el panel.
- No se modifica NIVO Web Chat, NIVO IA ni el resto de la logica funcional.

Archivos modificados:
- app/Views/home.php
- public/assets/js/seo-home.js
