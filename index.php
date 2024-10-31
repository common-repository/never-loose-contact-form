<?php
/*
Plugin Name: Never Loose Contact Form
Plugin URI: 
Description: Simple to use spam free contact form using simple checkbox captcha, saving messages to database and emailing your admin contact
Author: andy_moyle
Version: 3.2.1
Author URI: http://www.themoyles.co.uk/web-development/contact-form-plugin/
*/
if ( ! defined( 'ABSPATH' ) ) exit('You need Jesus!'); // Exit if accessed directly

require_once(plugin_dir_path(__FILE__).'widget.php');

add_action('init','contact_form_install');
function contact_form_install()
{
    global $wpdb;
    
    define('CONT_TBL',$wpdb->prefix.'contact_form');
    define('CONT_URL',WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)));
    $wpdb->query('CREATE TABLE IF NOT EXISTS '.CONT_TBL.' (`name` text NOT NULL,`comment` text NOT NULL,`subject` text NOT NULL, `email` text NOT NULL,`post_date` datetime NOT NULL,`ip` TEXT NOT NULL,`date_read` datetime NULL DEFAULT NULL ,`id` int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;');
    if($wpdb->get_var('SHOW COLUMNS FROM '.CONT_TBL.' LIKE "phone"')!='phone')
	{
    	$sql='ALTER TABLE  '.CONT_TBL.' ADD phone TEXT AFTER `email`';
    	$wpdb->query($sql);
	}

}
add_action('admin_enqueue_scripts','contact_form_scripts');
add_action('wp_enqueue_scripts','contact_form_scripts');

function contact_form_scripts()
{
    wp_enqueue_style('never-loose-contact-form',plugin_dir_url(__FILE__).'contact-form.css',array(), filemtime(plugin_dir_path(__FILE__).'contact-form.css'), 'all' );
    
}
//add localisation
$nlcf_translator_domain   = 'nlcf';
$nlcf_is_setup = 0;
function nlcf_loc_setup(){
  global $nlcf_translator_domain, $nlcf_translator_is_setup;
  if($nlcf_translator_is_setup) {
    return;
  }
  load_plugin_textdomain( 'nlcf', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
  $nlcf_translator_is_setup = 1;
}
add_action('plugins_loaded', 'nlcf_loc_setup');
// Admin Bar Customisation
function contact_form_admin_bar_render() {
 global $wp_admin_bar,$wpdb,$current_user,$never_loose_contact_settings;
 if(current_user_can('edit_posts'))
 {
    $sql='SELECT Count(*) FROM '.CONT_TBL.' WHERE DATE(post_date)=CURDATE()';
    $count=$wpdb->get_var($sql);
    // Add a new top level menu link
    // Here we add a customer support URL link
    $wp_admin_bar->add_menu( array('parent' => false, 'id' => 'contact_form', 'title' => __('Contact Form ','nlcf'). $count.' '.__('Today','nlcf'), 'href' => admin_url().'admin.php?page=contact_form/index.php' ));
    $wp_admin_bar->add_menu(array('parent' => 'contact_form','id' => 'contact_form_settings', 'title' => __('Settings','nlcf'), 'href' => admin_url().'admin.php?page=contact_form/index.php&action=contact_form_settings' ));
 }
}

// Finally we add our hook function
add_action( 'wp_before_admin_bar_render', 'contact_form_admin_bar_render' );
//front_end
add_shortcode('contact_form','contact_form_shortcode');


function contact_form_shortcode($atts, $content = null)
{
     return contact_form(false);
}

function contact_form($widget=false,$title=null)
{
    
    global $wpdb;
	$maxurls=2;
    $out='';
    //$out.=print_r($_POST,TRUE);
    $id=get_current_user_id();
   if(!empty($_POST['contact_form_nonce']))contact_form_debug($_POST); 
    if(empty($_POST['contact_form_extra'])&&!empty($_POST['save_contact_form_message']) && wp_verify_nonce($_POST['contact_form_nonce'],'contact_form_comment'))
    {
		$error=array();
        if(empty($_POST['contact_form_comment']))
		{
			//no comment
			$error[]=__("You haven't left a message",'nlcf');
		}
        if(!empty($_POST['contact_form_comment'])&& (str_word_count($_POST['contact_form_comment'],0)<2))
        {
			//not long enough
            $error[]=__('Message length','nlcf').str_word_count($_POST['contact_form_comment'],0);
			$error[]=__("Your message is a bit short",'nlcf');
		}
		if(empty($_POST['contact_form_email']))
		{
			//no comment
			$error[]=__("You haven't left an email address for reply",'nlcf');
		}
		if(empty($_POST['contact_form_name']))
		{
			//no comment
			$error[]=__("You haven't left a name",'nlcf');
		}
		if(!empty($_POST['contact_form_extra']))
        {//not real human checked
                $error[]=__("You appear to be a spammer, so the message wasn't sent",'nlcf');
        }
        if(substr_count($_POST['contact_form_comment'], "https://") >= $maxurls)
        {//too many urls
            $error[]=__("There were too many web links in it - makes it look like you are a spammer.",'nlcf');
			 
        }
        if(substr_count($_POST['contact_form_comment'], "http://") >= $maxurls)
        {//too many urls
            $error[]=__("There were too many web links in it - makes it look like you are a spammer.",'nlcf');
			 
        }//end too many urls
        if(substr_count($_POST['contact_form_subject'], "http") > 0)
        {
            $error[]=__("Web links in the subject is a pretty spammy thing to do.",'nlcf');
        }
        if(substr_count($_POST['contact_form_name'], "http") > 0)
        {
            $error[]=__(" Web links for your name is a pretty spammy thing to do.",'nlcf');
        }
		if(!empty($_POST['contact_form_email'])&&!is_email($_POST['contact_form_email']))
		{
			//no comment
			$error[]=__("Your email address doesn't look like an email address",'nlcf');
		}
        //one word messages are spammy too
        $pattern=' ';
        if((!strpos(stripslashes($_POST['contact_form_comment']),$pattern)))
        {
            $error[]=__('One word? Really?','nlcf');
        }
        if(empty($_POST['funkybit']))
		{
			//no comment
			$error[]=__("You seem to be a bot.",'nlcf');
		}
        $needle=array('Page 1 rankings','bitcoin','shemail','lesbian','gay','Make $1000','casino','teen photos','passive income','porn','bitcoin','viagra','fuck','penis','sex','visit your website','www.yandex.ru','сайт','products on this site','business directory','<script','onClick','boobs','tits','horny','all-night');
        if(contact_form_strposa($_POST['contact_form_comment'], $needle,0))
        {
            $error[]=__("You used some forbidden words",'nlcf');
        }
		if(!empty($error))
		{
			contact_form_debug($error);
            $out.='<div class="nlcf-message"><p><strong>'.__('Your message has not been sent, due to some errors','nlcf').'</strong><br/>'.implode("<br/>",$error).'</p></div>';
			$out.=contact_form_form($widget,$title);
            $spams=get_option('never-loose-contact-form-spams');
            if(empty($spams))$spams=0;
            $spams++;
            update_option('never-loose-contact-form-spams',$spams);
		}
        else
        {//reasonably happy it's not spam
            $form=array();
            foreach($_POST AS $key=>$value)$form[$key]=sanitize_text_field(stripslashes($value));
        
                $sql=array();
                foreach($form AS $key=>$value)$sql[$key]=esc_sql($value);
                $check=$wpdb->get_var('SELECT id FROM '.CONT_TBL.' WHERE name="'.$sql['contact_form_name'].'" AND phone="'.$sql['contact_form_number'].'" AND email="'.$sql['contact_form_email'].'" AND comment="'.$sql['contact_form_comment'].'" AND subject="'.$sql['contact_form_subject'].'" AND ip="'.esc_sql($_SERVER['REMOTE_ADDR']).'" ');
                if(!$check)
                {
                    $wpdb->query('INSERT INTO '.CONT_TBL.' (name,email,subject,comment,post_date,ip,phone)VALUES("'.$sql['contact_form_name'].'","'.$sql['contact_form_email'].'","'.$sql['contact_form_subject'].'","'.$sql['contact_form_comment'].'","'.date('Y-m-d H:i:s').'","'.esc_sql($_SERVER['REMOTE_ADDR']).'","'.$sql['contact_form_number'].'")');
                    
                    $to=get_option('admin_email');
                    $sendTo=get_option('contact-form-email');
                    if(!empty($sendTo))$to=$sendTo;
                    $subject='Website Message from '.site_url();
                    $message='<table><tr><td>'.__('Name','nlcf').':</td><td>'.esc_html($form['contact_form_name']).'</td></tr>';
                    $message.='<tr><td>'.__('Email','nlcf').':</td><td><a href="'.esc_url('mailto:'.$form['contact_form_email']).'">'.esc_html($form['contact_form_email']).'</a></td></tr>';
                    $message.='<tr><td>'.__('IP Address','nlcf').':</td><td>'.esc_html($_SERVER['REMOTE_ADDR']).'</td></tr>';
                    $message.='<tr><td>'.__('Message','nlcf').':</td><td>'.esc_html($form['contact_form_comment']).'</td></tr></table>';
                    add_filter( 'wp_mail_from_name','contact_form_from_name' );
				    add_filter( 'wp_mail_from', 'contact_form_from_email');
                    add_filter('wp_mail_content_type','contact_form_content_type');
                    $headers = array(
                        'Reply-To: '.esc_html($form['contact_form_name']).' <'.$form['contact_form_email'].'>',
                    );
                    wp_mail($to,$subject,$message,$headers); 
                    remove_filter('wp_mail_content_type','contact_form_content_type');

                    //send SMS 
                    $SMSsettings=get_option('never-loose-contact-sms-settings');
                    if(!empty($SMSsettings))
                    {
                        $url='https://rest.textmagic.com/api/v2/messages';
                        $SMSmessage='New contact form message for '.get_option('blogname')."\n ".admin_url().'admin.php?page=contact_form%2Findex.php ';
                        $args=array('text'=>$SMSmessage,'phones'=>$SMSsettings['sender'],'from'=>$SMSsettings['sender']);
                        $jsonDataEncoded = json_encode($args);
                        $auth=array('Content-Type: application/json','X-TM-Username:'.$SMSsettings['username'],'X-TM-Key:'.$SMSsettings['APIKey']);
                        
                        $ch = curl_init($url); 
                        curl_setopt($ch, CURLOPT_POST,1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); 
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $auth); 
                        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
                        $result = curl_exec($ch);
                        $response=json_decode($result,TRUE);
                        contact_form_debug(print_r($response,TRUE));
                    }
                    $out.='<div  class="nlcf-message"><p><strong>'.__('Your message has  been sent, thank you. We will be in touch soon.','nlcf').'</strong></p></div>';
                }//not already in db
				
        }//not spam
       
        
    }//process form

    else
    {//form
        $out=contact_form_form($widget,$title);
    }//end form
    
    return $out;
}
/******
 * show errors in debug file!
 */
add_action( 'wp_mail_failed', 'contact_form_onMailError', 10, 1 );
    function contact_form_onMailError( $wp_error ) {
        contact_form_debug(print_r($wp_error,TRUE));
        
    }       
function contact_form_form($widget=FALSE,$title=NULL)
{
	$id=get_current_user_id();
	$out='';
		$info='';
        if(!$widget){$out.='<div class="contact_form_wrap">';}else{$out.='<div class="contact_form_widget">';}
        if(!empty($title))$out.='<h2>'.esc_html($title).'</h2>';                   
        $out.='<form  action="'.get_permalink().'" method="post" >';
        $out.=' <div class="nlcf-form-group"><label for="contact_name">Name</label><input id="contact_name" class="text_input nlcf-form-control" type="text" required="required" name="contact_form_name"';
        
		if(!empty($_POST['contact_form_name']))$out.=' value="'.esc_html(stripslashes($_POST['contact_form_name'])).'" ';
		else
		{	
			if($id)$info=get_userdata($id);
			if($info)$out.=' value="'.$info->user_nicename.'" ';
		}
		$out.='/></div>';
        $out.=' <div class="nlcf-form-group"><label for="contact_email">'.__('Email','nlcf').'</label><input type="text" id="contact_email" class="text_input nlcf-form-control" required="required" name="contact_form_email"';
        if(!empty($_POST['contact_form_email']))$out.=' value="'.esc_html(stripslashes($_POST['contact_form_email'])).'" ';
		else
		{	
			if($id)$info=get_userdata($id);
			if($info)$out.=' value="'.$info->user_email.'" ';
		}
        $out.='/></div>';
        $out.='<div class="nlcf-form-group"><label for="contact_subject">'.__('Contact Number','nlcf').'</label><input type="text" id="contact_number" class="text_input nlcf-form-control" name="contact_form_number"';
        if(!empty($_POST['contact_form_number']))$out.=' value="'.esc_html(stripslashes($_POST['contact_form_number'])).'" ';
		
        $out.='/></div>';
        $out.='<div class="nlcf-form-group"><label for="contact_subject">'.__('Subject','nlcf').'</label><input type="text" id="contact_subject" class="text_input nlcf-form-control" name="contact_form_subject"';
        if(!empty($_POST['contact_form_subject']))$out.=' value="'.esc_html(stripslashes($_POST['contact_form_subject'])).'" ';
		
        $out.='/></div>';
        $out.='<div class="nlcf-form-group"><label>'.__('Message','nlcf').'</label><textarea  class="textarea nlcf-form-control" rows=4 name="contact_form_comment">';
		if(!empty($_POST['contact_form_comment']))$out.=esc_html(stripslashes($_POST['contact_form_comment']));
		
		$out.='</textarea></div>';
        $out.=wp_nonce_field('contact_form_comment','contact_form_nonce',false,false);
        $out.='<div class="never-loose-contact-form nlcf-form-group never-loose-contact-extra"><input type="text" name="contact_form_extra"/></div><div class="funkybit"></div>';
        $out.='<div class="nlcf-form-group"><input type="hidden" name="save_contact_form_message" value="yes"/><input type="submit"  value="'.__('Send Message','nlcf').'" class="button-primary btn btn-warning"/></div></form>';
        $out.='</div>';
        $out.='<noscript>'.__('This contact form only works with Javascript enabled','nlcf').'</noscript><script>
              var funkybit = document.querySelector(".funkybit"); 
              var FN = document.createElement("input"); 
                FN.setAttribute("type", "hidden"); 
                FN.setAttribute("name", "funkybit"); 
                FN.setAttribute("value", "notabot");
                funkybit.appendChild(FN)
        </script>';
		return $out;
}

add_shortcode('bootstrap_modal_contact_form','contact_form_bootstrap_modal');
function contact_form_bootstrap_modal($atts)
{
    extract(shortcode_atts(array("modal" => 'bootstrap_modal_contact_form','title'=>"Contact Us"), $atts));
    $out='<!-- Modal --><div class="modal fade" id="'.$modal.'" tabindex="-1" role="dialog" aria-labelledby="'.$modal.'" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="exampleModalLongTitle">'.$title.'</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body">';
    $out.=    contact_form_form(FALSE);
    $out.='</div></div></div></div>';
    return $out;
}
//end front end


/******************************************************
*
*   back end
*
*******************************************************/
//Admin Menu
add_action('admin_menu', 'contact_form_admin_menus');
function contact_form_admin_menus() 
{
    //add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
    add_menu_page('Contact Form', 'Contact Form',  'edit_pages', 'contact_form/index.php', 'contact_form_main','dashicons-email-alt');
    
}
//End Admin Menu

function contact_form_main()
{
    
    if(!empty($_POST['contact-form-recipient']))
    {
        $sendTo=stripslashes($_POST['contact-form-recipient']);
        if(is_email($sendTo))update_option('contact-form-email',$sendTo);
    }
    if(!empty($_GET['action']))
    {
        switch($_GET['action'])
        {
    
            case 'delete_comment':check_admin_referer('delete_comment');contact_form_delete_comment($_GET['id']);break;
            case 'settings':check_admin_referer('settings');contact_form_settings();break;
           
        }
    }
    else{contact_form_list();}
}


function contact_form_list()
{
     $spams=get_option('never-loose-contact-form-spams');
    global $wpdb,$never_loose_contact_settings;
    echo'<h2>'.__('Contact Form Messages','nlcf').'</h2><p>A plugin by <a href="http://wwww.themoyles.co.uk">Andy Moyle</a>&nbsp;<form class="right" action="https://www.paypal.com/cgi-bin/webscr" method="post"><input type="hidden" name="cmd" value="_s-xclick"><input type="hidden" name="hosted_button_id" value="R7YWSEHFXEU52"><input type="image"  src="https://www.paypal.com/en_GB/i/btn/btn_donate_LG.gif"  name="submit" alt="PayPal - The safer, easier way to pay online."><img alt=""  border="0" src="https://www.paypal.com/en_GB/i/scr/pixel.gif" width="1" height="1"></form></p>';
    $settingsURL=wp_nonce_url('admin.php?page=contact_form/index.php&amp;action=settings','settings');
    echo'<p><a class="button-primary" href="'.$settingsURL.'">Set up SMS alerts of new messages</a></p>';
    if(!empty($spams))echo'<p>'.sprintf(__('You have been protected from %1$s spam messages','nlcf'),$spams).'</p>';
    
    /*****************************
     * Contact form destination
     *****************************/
    
    if(!empty($_POST['contact-form-recipient'])&&is_email(stripslashes($_POST['contact-form-recipient'])))
    {
        update_option('contact-form-email',stripslashes($_POST['contact-form-recipient']));
    }
    if(!empty($_POST['contact-form-recipient-name']))
    {
        update_option('contact-form-recipient-name',stripslashes($_POST['contact-form-recipient-name']));
    }
    echo'<h2>Contact form destination</h2>';
    $sendTo=get_option('contact-form-email');
    if(empty($sendTo))$sendTo=get_option('admin_email');
    $sendToName=get_option('contact-form-recipient-name');
    
    echo'<p>Contact messages get sent to '.$sendTo.'</p>';
    echo'<form action="" method="post">';
    echo'<p><label>Destination name</label><input type="text" name="contact-form-recipient-name" value="'.esc_html($sendToName).'"/><p>';
    echo '<p><label>Destination email for messages</label><input type="email" name="contact-form-recipient" value="'.esc_html($sendTo).'"/><p>';
    echo'<p><input type="submit"></p></form>';

    /*****************************
     * Contact form message list
     *****************************/

    echo'<h2>Messages</h2>';
    $theader='<tr><th>'.__('Delete','nlcf').'</th><th>'.__('Date Posted','nlcf').'</th><th>'.__('Name','nlcf').'</th><th>'.__('Email','nlcf').'</th><th>'.__('Phone','nlcf').'</th><th>'.__('Comment','nlcf').'</th><th>'.__('Read','nlcf').'</th></tr>';
    $table='<table class="widefat"><thead>'.$theader.'</thead></tbody>';
    $results=$wpdb->get_results('SELECT * FROM '.CONT_TBL.'  ORDER BY post_date DESC');
    
    if($results)
    {
        foreach($results AS $row)
        {
            if(!empty($row-->data_read) && $row->date_read=='0000-00-00 00:00:00'){$class=' class="contact_read" ';}else{$class='';}
            $delete='<a href="'.wp_nonce_url('admin.php?page=contact_form/index.php&amp;action=delete_comment&amp;id='.(int)$row->id,'delete_comment').'">Delete</a>';

            $read='<a class="button-secondary nlcf-popup-trigger" href="#" data-id="'.intval($row->id).'">'.__('View complete message','nlcf').'</a> <div class="nlcf-popup" id="message'.(int)$row->id.'" style="display:none" ><div class="nlcf-popup-header"><h2>'.__('Message from','nlcf').' '.esc_html($row->name).'</h2></div><div class="nlcf-popup-content"><p>'.__('Posted','nlcf').':'.mysql2date('d M Y H:i',$row->post_date).'</p><p>'.__('From','nlcf').':<a href="'.esc_url('mailto:'.$row->email).'">'.esc_html($row->email).'</a></p><p>'.__('Subject','nlcf').': '.esc_html($row->subject).'</p><p>'.esc_html($row->comment).'</p><span class="button-secondary close-me" data-id="'.intval($row->id).'">Close</span></div></div>';
        
            $table.='<tr '.$class.'><td>'.$delete.'</td><td>'.mysql2date('d M Y H:i',$row->post_date).'</td><td>'.esc_html($row->name).'</td><td><a href="'.esc_url('mailto:'.$row->email).'">'.esc_html($row->email).'<a></td><td>'.esc_html($row->phone).'</td><td>'.contact_form_truncate($row->comment,75,'... ').'</td><td>'.$read.'</td></tr>';
        }
        $table.='</tbody></table>';
        echo $table;
        echo '<script>jQuery(document).ready(function($){
            $(".nlcf-popup-trigger").click(function(){
                var id=$(this).data("id");
                console.log("id: "+id);
                $("#message"+id).show();
            });
            $(document).mouseup(function(e) 
            {
                var container = $("nlcf-popup");
                if (!container.is(e.target) && container.has(e.target).length === 0) 
                {
                    $(".nlcf-popup").hide();
                }
            });
      
            $(".close-me").click(function(){
                
                $(".nlcf-popup").hide();
            });
        
        });</script>';
    }
    else{echo'<p>No messages yet</p>';}
    
}
function contact_form_truncate($str, $length=10, $trailing='...')
    {
    /*
    ** $str -String to truncate
    ** $length - length to truncate
    ** $trailing - the trailing character, default: "..."
    */
          // take off chars for the trailing
          $length-=mb_strlen($trailing);
          if (mb_strlen($str)> $length)
          {
             // string exceeded length, truncate and add trailing dots
             return mb_substr($str,0,$length).$trailing;
          }
          else
          {
             // string was already short enough, return the string
             $res = $str;
          }
     
          return $res;
     
    }



function contact_form_delete_comment($id)
{
    global$wpdb;
    $wpdb->query('DELETE FROM '.CONT_TBL.' WHERE id="'.(int)$id.'"');
    echo'<div class="updated fade"><p><strong>Comment deleted</strong></p></div>';
    echo contact_form_list();
}
//end back end
function contact_form_content_type()
{
    return 'text/html';
}
/************
* 
* Block
*
************/
add_action('enqueue_block_assets','contact_form_block_assets');
function contact_form_block_assets()
{
    wp_enqueue_style('never-loose-contact-form',plugin_dir_url(__FILE__).'contact-form.css',array(), filemtime(plugin_dir_path(__FILE__).'contact-form.css'), 'all' );
}
add_action( 'init', 'contact_form_php_block_init' );
function contact_form_php_block_init() {
	// Register our block editor script.
    if(is_admin())
    {
        wp_enqueue_script(
            'never-loose-contact-form',
            plugins_url( '/', __FILE__  ) . 'block.js',
            array( 'wp-blocks', 'wp-element','wp-components','wp-block-editor','wp-hooks','wp-server-side-render')
        );
    }
	register_block_type( 'never-loose-contact-form/contact-form', array(
		'attributes'      => array('title' => array('type' => 'string','default'=>__('Contact us','nlcf'))),
		'keywords'=>array(__( 'Contact form','nlcf' )),
		'editor_script'   => 'php-block', // The script name we gave in the wp_register_script() call.
		'render_callback' => 'contact_form_block',
	) );
}
function contact_form_block($attributes)
{
    return contact_form(FALSE,$attributes['title']);
}

function contact_form_strposa($haystack, $needle, $offset=0) {
    if(!is_array($needle)) $needle = array($needle);
    foreach($needle as $query) {
        if(strpos(strtoupper($haystack), strtoupper($query), $offset) !== false) return true; // stop on first true result
    }
    return false;
}

function contact_form_debug($message)
{  
    $text="<?php exit('Nothing is good!') ;?>";
	$upload_dir = wp_upload_dir();
	$debug_path=$upload_dir['basedir'].'/contact-form-cache/';
    $header="********** ".date('Y-m-d H:i:s')."\r\n";
    
    //create cache directory
    wp_mkdir_p($debug_path);
    
    $index="<?php\r\n//nothing is good;\r\n?>";
    if(!file_exists($debug_path.'index.php'))
    {
        $index="<?php\r\n//nothing is good;\r\n?>";
        $fp = fopen($debug_path.'index.php', 'w');
        fwrite($fp, $index);
        fclose($fp); 
    }
    
	if(is_array($message))$message=print_r($message,TRUE);
	if(!file_exists($debug_path.'debug_log.php'))
	{

		
		$fp = fopen($debug_path.'debug_log.php', 'w');
		fwrite($fp, $text."\r\n");
	}
	if(empty($fp))$fp = fopen($debug_path.'debug_log.php', 'a');
    fwrite($fp, $header.$message."\r\n");
    fclose($fp);
    if(!file_exists($debug_path.'index.php'))
    {
        $fp = fopen($debug_path.'index.php', 'w');
        fwrite($fp, $text."\r\n");
        fclose($fp);
    }
}

/****************************
 * SETTINGS
 */
function contact_form_settings()
{
    echo'<h2>Get SMS notification of new contact form messages</h2>';
    echo'<p>Using <a class="button-primary" href="https://shareasale.com/r.cfm?b=1087412&u=2478286&m=75317&urllink=&afftrack=">Textmagic.com</a></p>';
    echo'<p><a class="button-secondary" href="https://www.themoyles.co.uk/never-loose-contact-sms.pdf">PDF how to guide</a></p>';
    
    if(!empty($_POST['save-settings']))
    {
        $SMSsettings=array(
            'APIKey'=>stripslashes($_POST['sms-api-key']),
            'username'=>stripslashes($_POST['sms-username']),
            'sender'=>stripslashes($_POST['sms-sender'])
        );
        update_option('never-loose-contact-sms-settings',$SMSsettings);
        
    }
    $SMSsettings=get_option('never-loose-contact-sms-settings');
    echo contact_form_credits();
    echo'<h2>'.__('SMS provider settings','church-admin').'</h2>';
    echo'<form action="" method="post">';
    echo'<table class="form-table">';
   
    echo'<tr><th scope="row">Your cell number with country code eg:4478901234567</th><td><input type="text" name="sms-sender" ';
    if(!empty($SMSsettings['sender'])) echo' value="'.esc_html($SMSsettings['sender']).'" ';
    echo'/></td></tr>';
    echo'<tr class="sms-username"';
    
    echo'><th scope="row">Textmagic Username</th><td><input type="text" name="sms-username" ';
    if(!empty($SMSsettings['username']))echo'value="'.esc_html($SMSsettings['username']).'" ';
    echo'/></td></tr>';
    echo'<tr class="sms-api-key"';
  
    echo'><th scope="row">Textmagic API key</th><td><input type="text" name="sms-api-key" ';
    if(!empty($SMSsettings['APIKey']))echo'value="'.esc_html($SMSsettings['APIKey']).'" ';
    echo'/></td></tr>';
    echo'<tr><th scope="row">&nbsp;</th><td><input type="hidden" name="save-settings" value="TRUE"/><input type="submit" value="'.__('Save','church-admin').' &raquo;" class="button-primary"/></form>';
    echo'</table>';
}

function contact_form_credits()
{
    $url='https://rest.textmagic.com/api/v2/user';
    
    $SMSsettings=get_option('never-loose-contact-sms-settings');
 
    if(!empty($SMSsettings))
    {
        echo '<p>Check balance</p>';
        $ch = curl_init($url);
        $url='https://rest.textmagic.com/api/v2/messages';
        
        $auth=array('Content-Type: application/json','X-TM-Username:'.$SMSsettings['username'],'X-TM-Key:'.$SMSsettings['APIKey']);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $auth); 
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $response=json_decode($result,TRUE);
    
        echo'<p>Balance: '.$response['currency']["htmlSymbol"].$response["balance"].'</p>';
        echo'<p><a href="https://my.textmagic.com/payment">Top up textmagic.com account</a>';
    }
}

function contact_form_from_name( $from ) {return get_option('contact-form-recipient-name');}
function contact_form_from_email( $email) {return get_option('contact-form-email');}