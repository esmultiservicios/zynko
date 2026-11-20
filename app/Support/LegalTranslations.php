<?php
/** Translation of the exact originally published text. Never substitute a stale translation for an edited document. */
function zynkoLegalLocalizedDocument(string $key, array $doc, string $lang): array {
    static $translations=null;
    if($translations===null)$translations=json_decode(file_get_contents(__DIR__.'/legal-translations.json'),true,512,JSON_THROW_ON_ERROR);
    $source=(string)($doc['content']??'');$entry=$translations[$key]??null;
    $match=$entry&&hash_equals($entry['hash'],hash('sha256',str_replace("\r\n","\n",trim($source))));
    return ['content'=>$match?$entry[$lang==='en'?'en':'es']:$source,'translated'=>(bool)$match,'fallback'=>$lang==='en'&&!$match];
}
function zynkoLegalLocalizedCatalog(string $lang): array {
    $catalog=zynkoLegalCatalog();if($lang!=='en')return $catalog;
    $en=['terms_conditions'=>['Terms and Conditions','Registration, access and use of the service.'],'privacy_policy'=>['Privacy Policy','Processing of personal and integration data.'],'commercial_conditions'=>['Commercial conditions and activation','Subscription, external costs and assisted activation.'],'data_deletion'=>['Data deletion','Public instructions for requesting deletion of information.']];
    foreach($en as $key=>$labels){$catalog[$key]['title']=$labels[0];$catalog[$key]['description']=$labels[1];}return $catalog;
}
