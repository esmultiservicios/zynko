<?php
/** Public FAQ uses existing system_settings; each value fits VARCHAR(255). */
function zynkoPublicFaqDefaults(string $parent='ES MULTISERVICIOS'): array {
    return [
      ['¿Qué es ZYNKO?','Es una plataforma SaaS omnicanal para organizar conversaciones, atención, usuarios, canales, automatización e integraciones empresariales.','What is ZYNKO?','It is an omnichannel SaaS platform for organizing conversations, support, users, channels, automation and business integrations.'],
      ['¿Puedo crear una cuenta gratis?','Sí. El registro público permite crear una empresa, verificar el correo y comenzar con el Plan Gratis disponible.','Can I create a free account?','Yes. Public registration lets you create a company, verify your email and start with the available Free plan.'],
      ['¿ZYNKO trabaja con varias empresas?','Sí. La plataforma está diseñada con separación lógica de información y configuración por empresa.','Does ZYNKO support multiple companies?','Yes. The platform is designed to keep each company’s information and configuration logically separate.'],
      ['¿Puedo conectar servicios externos?','ZYNKO contempla integraciones mediante canales, API y webhooks, sujetas a la configuración y al plan correspondiente.','Can I connect external services?','ZYNKO supports integrations through channels, APIs and webhooks, subject to configuration and the applicable plan.'],
      ['¿Quién desarrolla ZYNKO?','ZYNKO es una solución de '.$parent.', creada como parte de sus herramientas de software y servicios empresariales.','Who develops ZYNKO?','ZYNKO is a solution by '.$parent.', developed as part of its business software tools and services.'],
      ['¿Los clientes administran la plataforma global?','No. Cada empresa administra únicamente su espacio. La configuración global de ZYNKO, los planes, el SEO, las redes sociales públicas y los documentos legales quedan reservados para la empresa principal.','Do customers administer the global platform?','No. Each company manages only its own workspace. Global ZYNKO configuration, plans, SEO, public social media and legal documents are reserved for the main company.'],
    ];
}
function zynkoPublicFaq(array $settings,string $parent='ES MULTISERVICIOS'): array {
    $rows=[];
    foreach(zynkoPublicFaqDefaults($parent) as $i=>$default){
        $base='public_faq_'.($i+1).'_';
        $row=['visible'=>($settings[$base.'visible']??'1')==='1'];
        foreach(['es'=>0,'en'=>2] as $lang=>$offset){
            $row['q_'.$lang]=$settings[$base.'q_'.$lang]??$default[$offset];
            $answer='';
            for($part=0;$part<4;$part++)$answer.=$settings[$base.'a_'.$lang.'_'.$part]??'';
            $row['a_'.$lang]=array_key_exists($base.'a_'.$lang.'_0',$settings)?$answer:$default[$offset+1];
        }
        $rows[]=$row;
    }
    return $rows;
}
function zynkoPublicFaqSettings(array $input): array {
    $items=[];
    for($i=0;$i<6;$i++){
        $row=$input[$i]??null;
        if(!is_array($row))throw new RuntimeException('Falta una pregunta frecuente.');
        $base='public_faq_'.($i+1).'_';
        $items[$base.'visible']=isset($row['visible'])?'1':'0';
        foreach(['es','en'] as $lang){
            $q=$row['q_'.$lang]??null;$a=$row['a_'.$lang]??null;
            if(!is_string($q)||!is_string($a))throw new RuntimeException('Completa ambos idiomas.');
            $q=trim($q);$a=trim($a);
            $qChars=preg_split('//u',$q,-1,PREG_SPLIT_NO_EMPTY);$aChars=preg_split('//u',$a,-1,PREG_SPLIT_NO_EMPTY);
            if($qChars===false||$aChars===false||$q===''||$a===''||count($qChars)>180||count($aChars)>1000)throw new RuntimeException('Cada pregunta requiere hasta 180 caracteres y cada respuesta hasta 1,000, en ambos idiomas.');
            $items[$base.'q_'.$lang]=$q;
            for($part=0;$part<4;$part++)$items[$base.'a_'.$lang.'_'.$part]=implode('',array_slice($aChars,$part*250,250));
        }
    }
    return $items;
}
