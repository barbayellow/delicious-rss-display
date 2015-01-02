<?php
/*
Plugin Name: Delicious rss display
Plugin URI: http://blog.barbayellow.com
Description: Displays your delicious bookmarks on post pages depending on the tags or categories assigned to the post.
Version: 1.0
Author: Grégoire Pouget
Author URI: http://blog.barbayellow.com
*/

// change 19/12/11 : change WP_Http use class for the standard wordpress wrapper wp_remote_get()
// change 19/12/11 : change use of tag/category->slug for the tag/category-> name propriety. Solve a special character problem (accent)

// todo : widget
// todo : check delicious username
// todo : one delicious account per author

// compatibility stuffs
global $wp_version;
$exit_msg = 'Delicious rss display requires WordPress 2.9 or newer. <a href="http://codex.wordpress.org/Upgrading_WordPress">Please update !</a>';
if(version_compare($wp_version, '2.9.0', '<')) {
	exit($exit_msg);
}

// useful variables
define('DRD_DELICIOUSURL', 'http://delicious.com/'); 
define('DRD_DELICIOUSFEED', 'http://feeds.delicious.com/v2/');
define('DRD_MAX_COUNT', 10);
define('DRD_PATH', trailingslashit(WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__))));
$drd_addcontent = get_option('drd_addcontent', 0);
$drd_type = get_option('drd_type', 1);
$drd_user_name = get_option('drd_user_name');
$drd_count = get_option('drd_count', 3);
$drd_title = get_option('drd_title');
$drd_titletag = get_option('drd_titletag', 'h2');
$drd_json = get_option('drd_json');

// init actions
add_filter( "the_content", "drd_addbookmarkstocontent" );
add_action('drd_refresh', 'drd_get_tags'); // function called in the drd_options_do_page() function for mutual hostings
add_action('wp', 'drd_activation'); // useless on a mutual hosting
add_action('admin_menu', 'drd_options_add_page');
add_action('admin_init', 'drd_options_init' );
add_action('init', 'drd_load_plugin_textdomain');
register_deactivation_hook(__FILE__, 'drd_deactivation');

// Add cron event
function drd_activation() {
	if ( !wp_next_scheduled( 'drd_refresh' ) ) {
		wp_schedule_event(time(), 'daily', 'drd_refresh');
		//wp_schedule_event(time(), 'weekly', 'drd_refresh');		
	}
}

// Add a new cron frequency  - not used yet
add_filter('cron_schedules', 'cron_add_weekly'); 
function cron_add_weekly( $schedules )
{
	// Adds once weekly to the existing schedules.
	$schedules['weekly'] = array(
		'interval' => 604800,
		'display' => __('Once Weekly')
	);
	return $schedules;
}


//  On deactivation
function drd_deactivation() {

	// unregister options
	unregister_setting('drd_options', 'drd_count', 'intval');
	unregister_setting('drd_options', 'drd_user_name', 'wp_filter_nohtml_kses');
	unregister_setting('drd_options', 'drd_title', 'wp_filter_nohtml_kses');
	unregister_setting('drd_options', 'drd_titletag');	
	unregister_setting('drd_options', 'drd_addcontent', 'intval');
	unregister_setting('drd_options', 'drd_type', 'intval');

	// delete options
	/*
	delete_option('drd_json');
	delete_option('drd_title');
	delete_option('drd_titletag');	
	delete_option('drd_user_name');
	delete_option('drd_count');
	delete_option('drd_type');
	*/
}

// internationalisation
function drd_load_plugin_textdomain() {
	load_plugin_textdomain( 'drd', false, basename(dirname(__FILE__)). '/languages' );
}

// register options
function drd_options_init() {
	register_setting('drd_options', 'drd_addcontent', 'intval');
	register_setting('drd_options', 'drd_type', 'intval');
	register_setting('drd_options', 'drd_count', 'intval');
	register_setting('drd_options', 'drd_user_name', 'wp_filter_nohtml_kses');
	register_setting('drd_options', 'drd_title');
	register_setting('drd_options', 'drd_titletag');	
}

// add settings page
function drd_options_add_page() {
	add_options_page(__('Delicious RSS display', 'drd'), __('Delicious RSS display', 'drd'), 'manage_options', 'drd_options', 'drd_options_do_page');
}

// create settings page
function drd_options_do_page() { 
	global $drd_user_name, $drd_count, $drd_title, $drd_type, $drd_addcontent, $drd_titletag;
	?>
	<div class="wrap">
		<h2><?php _e('Delicious RSS display', 'drd') ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields('drd_options'); ?>
			<?php drd_get_tags(); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e('Delicious user name', 'drd') ?></th>
					<td><input name="drd_user_name" type="text" value="<?php echo $drd_user_name ?>"  class="regular-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Title', 'drd') ?></th>
					<td><input type="text" name="drd_title" value="<?php echo $drd_title; ?>" class="regular-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Title tag', 'drd') ?></th>
					<td>
						<select name="drd_titletag" id="drd_titletag">
							<option value="h2" <?php if ('h2' == $drd_titletag) echo 'selected="1"'?>>h2</option>
							<option value="h3" <?php if ('h3' == $drd_titletag) echo 'selected="1"'?>>h3</option>
							<option value="h4" <?php if ('h4' == $drd_titletag) echo 'selected="1"'?>>h4</option>
							<option value="h5" <?php if ('h5' == $drd_titletag) echo 'selected="1"'?>>h5</option>
							<option value="h6" <?php if ('h6' == $drd_titletag) echo 'selected="1"'?>>h6</option> 
							<option value="div" <?php if ('div' == $drd_titletag) echo 'selected="1"'?>>div</option> 							
						</select>
					</td>
				</tr>					
				<tr valign="top">
					<th scope="row"><?php _e('Category or tags', 'drd') ?></th>
					<td>
						<select name="drd_type" id="drd_type">
							<option value="1" <?php if (1 == $drd_type) echo 'selected="1"'?>><?php _e('Tag') ?></option>	
							<option value="2" <?php if (2 == $drd_type) echo 'selected="1"'?>><?php _e('Category') ?></option>
						</select>
					</td>
				</tr>							
				<tr valign="top">
					<th scope="row"><?php _e('Number of item per tag', 'drd') ?></th>
					<td>
						<select name="drd_count" id="drd_count">
							<?php for ($i=1; $i <= DRD_MAX_COUNT ; $i++) { ?>
								<option value="<?php echo $i ?>" <?php if ($i == $drd_count) echo 'selected="1"'?>><?php echo $i ?></option>
							<?php 
							} ?>						
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Other settings', 'drd') ?></th>
					<td>
						<input type="checkbox" name="drd_addcontent" id="drd_addcontent" value="1" <?php if ($drd_addcontent): ?>checked="checked"<?php endif ?> />
						<label for="drd_addcontent"><?php _e('Auto Insert delicious bookmark in post content', 'drd') ?></label>
					</td>
				</tr>				
			</table>
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
	</div>
<?php 
}

// get tags from delicious - daily
function drd_get_tags() {
	global $drd_user_name;	
	
	if (!$drd_user_name) { // not usefull without username
		return;
	}

	// get json for delicious tags	
	
	$drd_url =  DRD_DELICIOUSFEED . 'json/tags/' . $drd_user_name;

	$drd_request = wp_remote_get($drd_url);
	if( is_wp_error( $drd_request ) ) {
		echo _e('Ahem... Something got wrong. Delicious is not responding to our request.', 'drd');
		return;
	} 
	$drd_jsontags = json_decode($drd_request['body']); 	// a enregistrer dans les options
	update_option('drd_json', $drd_jsontags);
}

// display rss function
function drd_display_rss($var="") {
	$defaults = array(
			'url_flux' => '',
			'max_items' => '3',
			'utf8_encode' => 0,
			'display' => 1
	);
	$endvar = wp_parse_args( $var, $defaults );	
	extract( $endvar, EXTR_SKIP );
	
	$feed = fetch_feed($url_flux);
	
	// get flux
	if(!is_wp_error($feed)) { // flux is ok
		$feed_items = $feed->get_items(0, $feed->get_item_quantity($max_items) );
		if ( !$feed_items ) {
		    $result = '<li>no items</li>';
		} else {
			$result ='';
		    foreach ( $feed_items as $item ) {
		        $result .= '<li><a href="' . $item->get_permalink() . '">' . $item->get_title() . '</a></li>';
		    }			
		}
	} else { // something got wrong
		$error_string = $feed->get_error_message();
		if($return_format=='array'){
			$result[] = '<div id="message" class="error"><p>' . $error_string . '</p></div>';	
		} else {
			$result = '<div id="message" class="error"><p>' . $error_string . '</p></div>';	
		}
	}
	
	// display or return
	if($display) {
		echo $result;
	} else {
		return($result);
	}
}


// display bookmarks
function drd_show_bookmarks($var='') {	
	global $post, $drd_user_name, $drd_count, $drd_title, $drd_type, $drd_json, $drd_titletag;
	$defaults = array(
			'show' => 1
	);

	$endvar = wp_parse_args( $var, $defaults );	
	extract( $endvar, EXTR_SKIP );

	// nothing if in admin area and nothing if username is not set - not useful yet since nothing is called within a hook
	if(is_admin() || (!$drd_user_name) || (!$drd_json)) {
		return;
	}

	// get tags or cat from the post - depending on the settings
	if($drd_type==1) { // tag
		$drd_post_tags = get_the_tags($post->ID);
	} 
	elseif($drd_type==2) { // cat
		$drd_post_tags = get_the_category($post->ID);	
	}

	// object becomes array
	foreach($drd_json as $key => $value) {
	    $drd_deltags[]=$key;
	}
		
	// Si pas de tag ou de cat, on ne fait rien
	if (!$drd_post_tags) {
		return;
	}
	
	foreach($drd_post_tags as $tag) {
		// if tag from the post and bundle name match
		if(in_array($tag->name, $drd_deltags)) {
			// display link to tags
			$drd_deltagstodisplay [] = '<a href="' . DRD_DELICIOUSURL . $drd_user_name . '/' .$tag->slug .'">' . $tag->name . '</a>';
			// get the posts via rss and fetch_feed so we can cache our request
			$drd_url_flux = DRD_DELICIOUSFEED . 'rss/' . $drd_user_name . '/' . $tag->name;
			$drd_bookmarks[]= drd_display_rss('url_flux=' . $drd_url_flux . '&max_items=' .$drd_count. '&display=0');		
		}
	}

	if (isset($drd_bookmarks)) { // we found some delicious bookmarks
		$drd_bookmarks = array_unique($drd_bookmarks); // dédoublonnage du tableau (un lien peut être tagué avec pls tags différents )
		$drd_display = '<' . $drd_titletag . ' class="drd_rss_display">';
		$drd_display .= $drd_title;
		$drd_display .= '</' . $drd_titletag . '>';
		$drd_display .= '<ul class="drd_rss_display">';
		foreach($drd_bookmarks as $drd_li) {
			$drd_display .= $drd_li;
		}
		$drd_display .= '<li class="delicious"><img  src="' . DRD_PATH .'/i/delicious_16x16.png" alt="Delicious" /> ' . __('See on delicious :', 'drd') . '&nbsp;' . implode(', ' ,$drd_deltagstodisplay) . '</li>';
		$drd_display .= '</ul>';
	} else { // no delicious bookmarks found
		// pb  >>
		$drd_display = '<' . $drd_titletag . ' class="drd_rss_display">';
		$drd_display .= $drd_title;
		$drd_display .= '</' . $drd_titletag . '>';		
		$drd_display .= '<ul class="drd_rss_display">';
		$drd_display .= '<li class="delicious"><img  src="' . DRD_PATH .'/i/delicious_16x16.png" alt="Delicious" /> ' . __('See', 'drd') . ' <a href="'. DRD_DELICIOUSURL .  $drd_user_name .'">'. __('my delicious', 'drd') .'</a></li>';
		$drd_display .= '</ul>';
	} 
	
	// display or return - depending on the settings options
	if ($show) {
		echo $drd_display;
	} else {
		return $drd_display;
	}
	
}

function drd_addbookmarkstocontent($content) {
	global $drd_addcontent;
	if (is_single() && $drd_addcontent) {
		$content .= drd_show_bookmarks('show=0');
	}
	return $content;
}
?>