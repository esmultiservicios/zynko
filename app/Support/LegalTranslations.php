<?php
/** Compare the wording and hyperlinks of the actual revision; HTML formatting alone must not invalidate a translation. */
function zynkoLegalTranslationSignature(string $value): string {
    preg_match_all('~<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1~is',$value,$links);
    $urls=array_map(fn($url)=>html_entity_decode($url,ENT_QUOTES|ENT_HTML5,'UTF-8'),$links[2]??[]);
    $value=preg_replace('~</?(?:p|div|h[1-6]|li|ul|ol|blockquote|br|hr)\b[^>]*>~i',' ',$value)??$value;
    $text=html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_HTML5,'UTF-8');
    $text=trim(preg_replace('/[\s\x{00a0}]+/u',' ',$text)??$text);
    return hash('sha256',json_encode([$text,$urls],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
}
function zynkoLegalLocalizedDocument(string $key, array $doc, string $lang): array {
    static $translations=null;
    if($translations===null)$translations=json_decode(file_get_contents(__DIR__.'/legal-translations.json'),true,512,JSON_THROW_ON_ERROR);
    $source=(string)($doc['content']??'');$entry=$translations[$key]??null;
    $catalog=zynkoLegalLocalizedCatalog($lang);$definition=$catalog[$key]??[];
    // Owner publishes both language versions together; the English text belongs to this specific revision.
    if(trim((string)($doc['content_en']??''))!==''){
        return ['title'=>$lang==='en'?(trim((string)($doc['title_en']??''))?:($definition['title']??'')):(string)($doc['title']??($definition['title']??'')), 'content'=>$lang==='en'?(string)$doc['content_en']:$source,'translated'=>true,'fallback'=>false];
    }
    $original=$entry&&hash_equals($entry['hash'],hash('sha256',str_replace("\r\n","\n",trim($source))));
    $base=$original||($entry&&hash_equals(zynkoLegalTranslationSignature($entry['es']),zynkoLegalTranslationSignature($source)));
    $revised=false;
    if($entry&&!empty($entry['appendix_es'])){
        // Phase 2 stored the base in HTML and then appended the Spanish commercial section.
        $expected=$entry['es']."\n".$entry['appendix_es'];
        $revised=hash_equals(zynkoLegalTranslationSignature($expected),zynkoLegalTranslationSignature($source));
    }
    if($base||$revised){
        $content=$lang==='en'?$entry['en']:$entry['es'];
        if($revised){$content=zynkoLegalContentToHtml($content).$entry[$lang==='en'?'appendix_en':'appendix_es'];}
        return ['title'=>$definition['title']??(string)($doc['title']??''),'content'=>$content,'translated'=>true,'fallback'=>false];
    }
    // Do not silently replace a customized, legally binding revision with an older translation.
    return ['title'=>$lang==='en'?($definition['title']??''):(string)($doc['title']??($definition['title']??'')),'content'=>$source,'translated'=>false,'fallback'=>$lang==='en'];
}
function zynkoLegalLocalizedCatalog(string $lang): array {
    $catalog=zynkoLegalCatalog();if($lang!=='en')return $catalog;
    $en=['terms_conditions'=>['Terms and Conditions','Registration, access and use of the service.'],'privacy_policy'=>['Privacy Policy','Processing of personal and integration data.'],'commercial_conditions'=>['Commercial conditions and activation','Subscription, external costs and assisted activation.'],'data_deletion'=>['Data deletion','Public instructions for requesting deletion of information.']];
    foreach($en as $key=>$labels){$catalog[$key]['title']=$labels[0];$catalog[$key]['description']=$labels[1];}return $catalog;
}
