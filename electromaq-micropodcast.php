<?php
/**
 * Plugin Name: Electromaq MicroPodcast
 * Description: Genera audio MP3 con ElevenLabs al publicar artículos seleccionados, lo guarda en WordPress y muestra un reproductor.
 * Version: 1.0.0
 * Author: Electromaq
 */
if (!defined('ABSPATH')) exit;

final class EM_MicroPodcast {
  const OPT='em_mp_settings'; const HOOK='em_mp_generate'; const CAT='micropodcast-electromaq';
  static function init(){
    add_action('transition_post_status',[__CLASS__,'transition'],10,3);
    add_action(self::HOOK,[__CLASS__,'generate']);
    add_filter('the_content',[__CLASS__,'player'],8);
    add_action('admin_menu',[__CLASS__,'menu']);
    add_action('admin_post_em_mp_save',[__CLASS__,'save']);
    add_action('admin_post_em_mp_test',[__CLASS__,'test']);
    add_action('admin_post_em_mp_generate_now',[__CLASS__,'generate_now']);
  }
  static function defaults(){return ['enabled'=>0,'key'=>'','voice'=>'','model'=>'eleven_v4','cats'=>'30,31','words'=>420];}
  static function s(){return wp_parse_args(get_option(self::OPT,[]),self::defaults());}
  static function enc($v){if(!$v||!function_exists('openssl_encrypt'))return ''; $k=hash('sha256',wp_salt('auth'),true);$iv=random_bytes(16);$c=openssl_encrypt($v,'AES-256-CBC',$k,OPENSSL_RAW_DATA,$iv);return $c?base64_encode($iv.$c):'';}
  static function dec($v){if(!$v||!function_exists('openssl_decrypt'))return ''; $r=base64_decode($v,true);if(!$r||strlen($r)<17)return ''; $k=hash('sha256',wp_salt('auth'),true);$p=openssl_decrypt(substr($r,16),'AES-256-CBC',$k,OPENSSL_RAW_DATA,substr($r,0,16));return is_string($p)?$p:'';}
  static function key(){return self::dec(self::s()['key']);}
  static function cats(){return array_values(array_filter(array_map('absint',explode(',',self::s()['cats']))));}
  static function eligible($id){return get_post_type($id)==='post' && (!self::cats() || array_intersect(self::cats(),wp_get_post_categories($id)));}
  static function transition($new,$old,$post){if($new==='publish'&&$old!=='publish'&&$post instanceof WP_Post&&self::eligible($post->ID)) self::queue($post->ID,10);}
  static function queue($id,$delay=10){$s=self::s();if(empty($s['enabled'])||!self::key()||empty($s['voice'])||get_post_meta($id,'_em_mp_audio',true))return;if(!wp_next_scheduled(self::HOOK,[$id])){update_post_meta($id,'_em_mp_status','queued');wp_schedule_single_event(time()+$delay,self::HOOK,[$id]);}}
  static function script($id){
    $s=self::s();$title=wp_strip_all_tags(get_the_title($id));$excerpt=wp_strip_all_tags(get_the_excerpt($id));$text=wp_strip_all_tags(strip_shortcodes(get_post_field('post_content',$id)));$text=preg_replace('/\s+/u',' ',trim($text));
    $words=preg_split('/\s+/u',$text);$max=max(220,min(650,absint($s['words'])));if(count($words)>$max)$text=implode(' ',array_slice($words,0,$max)).'…';
    return trim('Hoy en Electromaq: '.$title.'. '.($excerpt?$excerpt.'. ':'').$text.' Esta fue una historia de Electromaq. Encontrás el artículo completo en maquinasdecosercordoba.com.');
  }
  static function retry($id,$msg){$n=(int)get_post_meta($id,'_em_mp_try',true)+1;update_post_meta($id,'_em_mp_try',$n);update_post_meta($id,'_em_mp_error',sanitize_text_field($msg));$d=[300,1800,21600];if($n<=3){update_post_meta($id,'_em_mp_status','retry_'.$n);wp_schedule_single_event(time()+$d[$n-1],self::HOOK,[$id]);}else update_post_meta($id,'_em_mp_status','error');}
  static function generate($id){
    $id=absint($id);if(!$id||get_post_status($id)!=='publish'||!self::eligible($id)||get_post_meta($id,'_em_mp_audio',true))return;$s=self::s();$key=self::key();if(!$key||!$s['voice'])return;
    update_post_meta($id,'_em_mp_status','generating');$script=self::script($id);update_post_meta($id,'_em_mp_script',$script);
    $url='https://api.elevenlabs.io/v1/text-to-speech/'.rawurlencode($s['voice']).'?output_format=mp3_44100_128';
    $r=wp_remote_post($url,['timeout'=>120,'headers'=>['xi-api-key'=>$key,'Content-Type'=>'application/json','Accept'=>'audio/mpeg'],'body'=>wp_json_encode(['text'=>$script,'model_id'=>$s['model']?:'eleven_v4'])]);
    if(is_wp_error($r)){self::retry($id,$r->get_error_message());return;}$code=wp_remote_retrieve_response_code($r);$audio=wp_remote_retrieve_body($r);if($code<200||$code>=300||strlen($audio)<1000){self::retry($id,'ElevenLabs HTTP '.$code);return;}
    $up=wp_upload_bits('electromaq-micropodcast-'.$id.'.mp3',null,$audio);if(!empty($up['error'])){self::retry($id,$up['error']);return;}
    $aid=wp_insert_attachment(['post_mime_type'=>'audio/mpeg','post_title'=>'MicroPodcast: '.get_the_title($id),'post_status'=>'inherit'],$up['file'],$id);if(is_wp_error($aid)){self::retry($id,$aid->get_error_message());return;}
    require_once ABSPATH.'wp-admin/includes/image.php';$m=wp_generate_attachment_metadata($aid,$up['file']);if($m)wp_update_attachment_metadata($aid,$m);
    update_post_meta($id,'_em_mp_audio',$aid);update_post_meta($id,'_em_mp_url',esc_url_raw($up['url']));update_post_meta($id,'_em_mp_status','complete');delete_post_meta($id,'_em_mp_error');
    $size=@filesize($up['file']);if($size)update_post_meta($id,'enclosure',$up['url']."\n".$size."\naudio/mpeg");
    $t=get_term_by('slug',self::CAT,'category');if(!$t){$x=wp_insert_term('MicroPodcast Electromaq','category',['slug'=>self::CAT]);if(!is_wp_error($x))$t=get_term($x['term_id']);}if($t&&!is_wp_error($t))wp_set_post_categories($id,[(int)$t->term_id],true);
  }
  static function player($c){if(is_admin()||is_feed()||!is_singular('post')||!in_the_loop()||!is_main_query())return $c;$id=get_the_ID();$u=get_post_meta($id,'_em_mp_url',true);if(!$u)return $c;$script=get_post_meta($id,'_em_mp_script',true);$p='<section style="border:1px solid #e2ded4;border-radius:14px;padding:18px;margin:0 0 28px;background:#faf9f6"><p><strong>🎧 Escuchá este artículo</strong></p><audio controls preload="none" style="width:100%"><source src="'.esc_url($u).'" type="audio/mpeg"></audio><p style="font-size:.82rem;opacity:.72">Micro‑podcast narrado con voz generada por inteligencia artificial.</p>';if($script)$p.='<details><summary>Ver transcripción</summary><p>'.esc_html($script).'</p></details>';$p.='</section>';return $p.$c;}
  static function menu(){add_options_page('Electromaq MicroPodcast','Electromaq MicroPodcast','manage_options','em-micropodcast',[__CLASS__,'page']);}
  static function page(){if(!current_user_can('manage_options'))return;$s=self::s();$configured=self::key()&&$s['voice'];?>
    <div class="wrap"><h1>Electromaq MicroPodcast</h1><p><strong>Estado:</strong> <?php echo $configured?'✅ ElevenLabs configurado':'⚠️ Falta API key y/o Voice ID'; ?></p>
    <?php if(isset($_GET['saved'])):?><div class="notice notice-success"><p>Configuración guardada.</p></div><?php endif; ?>
    <?php if(isset($_GET['tested'])):?><div class="notice <?php echo $_GET['tested']==='ok'?'notice-success':'notice-error';?>"><p><?php echo esc_html(wp_unslash($_GET['msg']??''));?></p></div><?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="em_mp_save"><?php wp_nonce_field('em_mp_save');?>
    <table class="form-table"><tr><th>Automático</th><td><input type="checkbox" name="enabled" value="1" <?php checked($s['enabled']);?>> Activado</td></tr>
    <tr><th>API key</th><td><input type="password" class="regular-text" name="api" autocomplete="new-password" placeholder="<?php echo $s['key']?'•••••••• (configurada; dejar vacío para conservar)':'Pegar API key';?>"><p class="description">Se cifra antes de guardarse.</p></td></tr>
    <tr><th>Voice ID</th><td><input type="text" class="regular-text" name="voice" value="<?php echo esc_attr($s['voice']);?>" required></td></tr>
    <tr><th>Model ID</th><td><input type="text" class="regular-text" name="model" value="<?php echo esc_attr($s['model']);?>"></td></tr>
    <tr><th>Categorías</th><td><input type="text" name="cats" value="<?php echo esc_attr($s['cats']);?>"><p class="description">30,31 = investigación + Navidad.</p></td></tr>
    <tr><th>Palabras</th><td><input type="number" min="220" max="650" name="words" value="<?php echo esc_attr($s['words']);?>"></td></tr></table><?php submit_button('Guardar');?></form>
    <?php if($configured):?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="em_mp_test"><?php wp_nonce_field('em_mp_test');submit_button('Probar ElevenLabs','secondary','submit',false);?></form><?php endif;?>
    <p>Feed de podcast: <code><?php echo esc_html(home_url('/category/'.self::CAT.'/feed/'));?></code></p></div><?php }
  static function save(){if(!current_user_can('manage_options'))wp_die('Forbidden');check_admin_referer('em_mp_save');$s=self::s();$s['enabled']=!empty($_POST['enabled'])?1:0;$s['voice']=sanitize_text_field(wp_unslash($_POST['voice']??''));$s['model']=sanitize_text_field(wp_unslash($_POST['model']??'eleven_v4'));$s['cats']=preg_replace('/[^0-9,]/','',wp_unslash($_POST['cats']??'30,31'));$s['words']=max(220,min(650,absint($_POST['words']??420)));$api=trim((string)($_POST['api']??''));if($api){$e=self::enc($api);if($e)$s['key']=$e;}update_option(self::OPT,$s,false);wp_safe_redirect(add_query_arg('saved','1',admin_url('options-general.php?page=em-micropodcast')));exit;}
  static function test(){if(!current_user_can('manage_options'))wp_die('Forbidden');check_admin_referer('em_mp_test');$s=self::s();$r=wp_remote_get('https://api.elevenlabs.io/v1/voices/'.rawurlencode($s['voice']),['timeout'=>30,'headers'=>['xi-api-key'=>self::key(),'Accept'=>'application/json']]);$ok=!is_wp_error($r)&&wp_remote_retrieve_response_code($r)>=200&&wp_remote_retrieve_response_code($r)<300;$msg=$ok?'Conexión correcta con ElevenLabs.':(is_wp_error($r)?$r->get_error_message():'ElevenLabs HTTP '.wp_remote_retrieve_response_code($r));wp_safe_redirect(add_query_arg(['tested'=>$ok?'ok':'error','msg'=>rawurlencode($msg)],admin_url('options-general.php?page=em-micropodcast')));exit;}
  static function generate_now(){if(!current_user_can('edit_posts'))wp_die('Forbidden');$id=absint($_GET['post_id']??0);if(!$id)wp_die('Missing post');check_admin_referer('em_mp_now_'.$id);delete_post_meta($id,'_em_mp_audio');delete_post_meta($id,'_em_mp_url');delete_post_meta($id,'_em_mp_try');wp_schedule_single_event(time()+2,self::HOOK,[$id]);wp_safe_redirect(get_edit_post_link($id,'url'));exit;}
}
EM_MicroPodcast::init();
register_activation_hook(__FILE__,function(){if(!wp_next_scheduled('em_mp_reconcile'))wp_schedule_event(time()+300,'hourly','em_mp_reconcile');});
add_action('em_mp_reconcile',function(){if(!EM_MicroPodcast::key())return;$q=new WP_Query(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>10,'category__in'=>EM_MicroPodcast::cats(),'meta_query'=>[['key'=>'_em_mp_audio','compare'=>'NOT EXISTS']]]);foreach($q->posts as $p)EM_MicroPodcast::queue($p->ID,5);});
