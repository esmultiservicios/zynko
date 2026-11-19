<?php
declare(strict_types=1);
require __DIR__.'/../app/Support/InboxContact.php';
function check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "OK $label\n";}
function reject(callable $f):bool{try{$f();return false;}catch(RuntimeException $e){return true;}}
check(InboxContact::phone('9999 0000','HN')==='50499990000','Honduras número local');
check(InboxContact::phone('+504 9999-0000','HN')==='50499990000','Honduras internacional');
check(reject(fn()=>InboxContact::phone('9999','HN')),'Longitud Honduras');
check(reject(fn()=>InboxContact::phone('+1 2125550101','HN')),'Prefijo incompatible');
check(reject(fn()=>InboxContact::phone('99990000','XX')),'País no admitido');
check(reject(fn()=>InboxContact::phone('9999<script>','HN')),'Rechaza caracteres HTML');
check(InboxContact::phone('2125550101','US')==='12125550101','Estados Unidos');
check(InboxContact::split('50499990000')===['HN','99990000'],'Separar prefijo');
check(InboxContact::split('99990000')===['HN','99990000'],'Honduras legado local');
check(InboxContact::split('12425550101')===['BS','5550101'],'Prefijo diferenciado NANP');
check(count(InboxContact::countries())===246,'246 países y territorios con prefijo');
check(str_contains(InboxContact::options(),'value="HN" data-dial="504" selected'),'Honduras preseleccionada');
check(InboxContact::isSaved(['custom_fields'=>'{"inbox_saved":true}']),'Contacto guardado');
check(!InboxContact::isSaved(['custom_fields'=>'{"inbox_saved":false}']),'Contacto no guardado');
check(InboxContact::mediaUrl(['url'=>'uploads/chat/1/3/photo.png'],7,0)==='channel-media.php?message_id=7&index=0','Adjunto servido por endpoint autenticado');
check(InboxContact::mediaUrl(['url'=>'javascript:alert(1)'],7,0)==='','Rechazo URL peligrosa');
check(InboxContact::mediaUrl(['url'=>'uploads/chat/1/../x.pdf'],7,0)==='','Rechazo traversal');
check(InboxContact::mediaUrl(['provider_id'=>'a'],9,2)==='channel-media.php?message_id=9&index=2','Adjunto proveedor');
