# ZYNKO WhatsApp QR Bridge

Servicio opcional para el canal **WhatsApp por QR**. Se ejecuta separado de PHP y mantiene sesiones persistentes.

1. `npm install`
2. Define `ZYNKO_QR_BRIDGE_TOKEN`, `ZYNKO_QR_WEBHOOK` y opcionalmente `PORT=8091`.
3. `npm start`
4. En ZYNKO configura el canal WhatsApp con proveedor **WhatsApp por QR**, URL del bridge (por ejemplo `http://127.0.0.1:8091`) y un `Session ID` único.

El bridge expone estado/QR, envío y eventos entrantes. ZYNKO conserva CRM, bandeja, NIVO y automatizaciones como capa superior. Este conector usa una sesión vinculada de WhatsApp Web y no debe presentarse como API oficial de Meta.
