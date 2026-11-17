<?php
declare(strict_types=1);

/** Catálogo único de documentos públicos. Las claves y rutas no son editables. */
function zynkoLegalCatalog(): array
{
    return [
        'terms_conditions' => [
            'title' => 'Términos y Condiciones',
            'path' => 'terms.php',
            'icon' => 'fa-file-signature',
            'description' => 'Condiciones de registro, acceso y uso del servicio.',
        ],
        'privacy_policy' => [
            'title' => 'Política de privacidad',
            'path' => 'privacy.php',
            'icon' => 'fa-shield-halved',
            'description' => 'Tratamiento de datos personales y de las integraciones.',
        ],
        'data_deletion' => [
            'title' => 'Eliminación de datos',
            'path' => 'data-deletion.php',
            'icon' => 'fa-user-shield',
            'description' => 'Instrucciones públicas para solicitar la eliminación de información.',
        ],
    ];
}

/** Contenido inicial; INSERT IGNORE nunca reemplaza lo publicado por el Owner. */
function zynkoSeedLegalDocuments(PDO $pdo): void
{
    $defaults = [
        'privacy_policy' => <<<'LEGAL'
1. Identidad y alcance
ZYNKO es una plataforma SaaS omnicanal operada por ES MULTISERVICIOS S. DE R.L. DE C.V., Honduras. Esta política describe el tratamiento de datos relacionado con el sitio web, las cuentas de ZYNKO, sus canales de comunicación, automatizaciones y servicios de integración. Para consultas sobre privacidad puede escribir a evelasquez@esmultiservicios.com.

2. Datos que tratamos
Según las funciones utilizadas, podemos recibir datos de registro y contacto (nombre, empresa, correo, teléfono e identificación fiscal cuando se proporciona), información de cuenta, roles y permisos, datos de facturación y suscripción, mensajes, contactos, identificadores de conversación, archivos adjuntos y datos técnicos (dirección IP, navegador, dispositivo, eventos de acceso, registros de actividad y seguridad). Los datos de autenticación y las credenciales de integraciones se utilizan para autorizar y operar las funciones correspondientes; no deben enviarse por chat o correo.

3. Datos de Meta y otros canales
Cuando una empresa autoriza la conexión con WhatsApp, Facebook Messenger, Instagram u otros servicios, ZYNKO puede recibir los identificadores de cuenta, página, número de teléfono, remitente y destinatario, nombres de perfil disponibles, mensajes, archivos, estados de entrega y otros eventos necesarios según los permisos otorgados. También puede recibir credenciales de autorización y configuración de webhooks para prestar el servicio. El acceso se limita a las funciones y permisos autorizados. Los datos obtenidos de Meta no se venden, alquilan ni entregan a intermediarios de datos ni se utilizan para finalidades incompatibles con los permisos y las políticas de Meta.

4. Finalidades
Utilizamos los datos para crear y verificar cuentas, autenticar usuarios, gestionar empresas y planes, enviar y recibir comunicaciones, organizar conversaciones, prestar soporte, ejecutar automatizaciones e integraciones autorizadas, proteger la plataforma, detectar abuso, mantener la continuidad operativa y cumplir obligaciones legales aplicables. Cuando se habilita la inteligencia artificial, los datos necesarios para la respuesta pueden procesarse mediante NIVO y los proveedores de IA configurados, sujetos a sus condiciones y a las restricciones aplicables a cada origen de datos.

5. Responsabilidad de las empresas clientes
Cada empresa administra sus propios usuarios, canales, contactos y conversaciones y debe contar con las autorizaciones, avisos de privacidad y fundamentos legales necesarios para tratarlos. En el tratamiento realizado por cuenta de una empresa cliente, ZYNKO actúa según las instrucciones y configuración de esa empresa. Las consultas sobre datos de clientes finales pueden requerir coordinación con la empresa que mantiene la relación con ellos.

6. Proveedores y destinatarios
Los datos necesarios pueden ser tratados por proveedores de alojamiento, infraestructura, correo, seguridad, mensajería, IA y otros servicios que intervienen en la operación habilitada, así como por personal autorizado que brinda soporte. Los canales externos, incluyendo los servicios de Meta, también están sujetos a sus propias políticas. Podemos comunicar información cuando sea necesario para cumplir una obligación legal válida o proteger derechos y seguridad. El acceso de proveedores y personal debe limitarse a las funciones que corresponden.

7. Seguridad y separación de empresas
ZYNKO emplea controles de acceso, separación lógica por empresa y mecanismos de protección de credenciales de integración, además de medidas razonables de seguridad operativa. Ninguna medida garantiza seguridad absoluta. Las empresas deben custodiar sus credenciales, retirar accesos no autorizados y reportar eventos de seguridad.

8. Cookies y registros técnicos
El sitio y el panel pueden utilizar cookies de sesión, autenticación, preferencias y medición de visitas, así como registros técnicos para seguridad y funcionamiento. El sitio público puede utilizar servicios de validación y protección frente a abuso cuando estén configurados. Puede controlar las cookies desde el navegador; bloquear las necesarias puede impedir funciones como iniciar sesión.

9. Conservación y eliminación
Los datos se conservan durante el tiempo necesario para prestar el servicio, gestionar la cuenta, atender solicitudes y cumplir obligaciones legales o de seguridad. Los períodos pueden variar según el tipo de dato, el plan, las necesidades de la empresa y la legislación aplicable. Las copias de respaldo y registros sujetos a conservación pueden requerir un tratamiento diferenciado. Puede solicitar la eliminación siguiendo las instrucciones disponibles en la página pública de Eliminación de datos.

10. Derechos y solicitudes
Según la legislación aplicable, puede solicitar acceso, rectificación, eliminación, oposición o limitación del tratamiento y retirar consentimientos cuando proceda. Para proteger la información de terceros, podremos verificar su identidad, titularidad o autorización y coordinar con la empresa responsable. La desconexión de una integración limita la autorización futura, pero no elimina automáticamente los datos ya tratados.

11. Transferencias y servicios externos
Los proveedores utilizados pueden operar en distintos países. Cuando corresponda, se aplicarán las garantías y condiciones contractuales o legales que resulten exigibles. Consulte también las políticas de privacidad de los servicios externos que decida conectar.

12. Cambios y contacto
Esta política puede actualizarse para reflejar cambios del servicio y obligaciones aplicables. La versión y fecha publicadas identifican el documento vigente. Para consultas, solicitudes de privacidad o eliminación de datos, escriba a evelasquez@esmultiservicios.com. No incluya contraseñas, códigos de verificación ni tokens en su solicitud.
LEGAL,
        'data_deletion' => <<<'LEGAL'
1. Alcance de la solicitud
Puede solicitar la eliminación de datos personales tratados por ZYNKO, incluyendo datos de una cuenta o integración autorizada de WhatsApp, Facebook Messenger, Instagram u otros servicios, así como información de contacto y conversaciones que correspondan a su identidad. La atención de solicitudes se realiza por ES MULTISERVICIOS, como operador de ZYNKO, y puede requerir coordinación con la empresa cliente responsable de la información.

2. Cómo solicitar la eliminación
Envíe un correo a evelasquez@esmultiservicios.com con el asunto «ZYNKO - Solicitud de eliminación de datos». Indique su nombre, correo de contacto, empresa o cuenta de ZYNKO relacionada si la conoce, canal o servicio involucrado y qué datos desea eliminar. Para una solicitud relativa a WhatsApp, puede indicar el número de teléfono en formato internacional. No envíe contraseñas, códigos de acceso ni tokens.

3. Verificación y tratamiento de la solicitud
El equipo responsable revisará la información y podrá solicitar datos adicionales estrictamente necesarios para verificar su identidad o autorización. Si la información pertenece a una empresa cliente, la solicitud se gestionará en coordinación con el responsable correspondiente, evitando revelar datos de otras personas o empresas.

4. Desconexión de integraciones de Meta
Si autorizó ZYNKO desde Meta, también puede revisar y retirar el acceso desde la configuración de integraciones empresariales o aplicaciones de su cuenta de Meta, según el tipo de autorización. Si administra la empresa en ZYNKO, puede además contactar al soporte para desvincular su canal. Revocar o desconectar una integración impide usos futuros de la autorización revocada, pero no borra por sí solo los datos almacenados previamente; para eliminarlos, presente la solicitud indicada en el apartado 2.

5. Alcance, conservación y excepciones
Una vez verificada y aprobada la solicitud, se gestionará la eliminación o anonimización de los datos que corresponda y sea posible eliminar. Cierta información puede requerir conservación por obligaciones legales, registros de facturación, seguridad, resolución de controversias, derechos de terceros o ciclos de respaldo. El alcance y las excepciones se comunicarán según corresponda. La eliminación en ZYNKO no implica la eliminación automática de datos conservados directamente por Meta u otros proveedores.

6. Confirmación y seguimiento
Se dará seguimiento a la solicitud por el correo de contacto facilitado y se informará su resultado, las medidas aplicadas y, cuando corresponda, los datos que deban conservarse y el motivo. Los tiempos de gestión dependerán de la verificación, el alcance y los plazos legales aplicables. Para consultar el avance, responda al mismo correo de seguimiento.

7. Contacto oficial
ES MULTISERVICIOS (ZYNKO), Honduras. Correo para solicitudes de eliminación y privacidad: evelasquez@esmultiservicios.com. Consulte también la Política de privacidad disponible en el sitio público de ZYNKO.
LEGAL,
    ];

    $catalog = zynkoLegalCatalog();
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO legal_documents(document_key,title,content,version,updated_by,published_at) VALUES(?,?,?,1,NULL,NOW())'
    );
    foreach ($defaults as $key => $content) {
        $insert->execute([$key, $catalog[$key]['title'], $content]);
    }
}

function zynkoFindLegalDocument(PDO $pdo, string $key): ?array
{
    if (!isset(zynkoLegalCatalog()[$key])) {
        return null;
    }
    $query = $pdo->prepare('SELECT title,content,version,published_at FROM legal_documents WHERE document_key=? LIMIT 1');
    $query->execute([$key]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}
