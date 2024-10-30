<?php
/*
Plugin Name: BookList Widget
Plugin URI: http://www.et.byu.edu/~cambium/blog/?page_id=49
Description: A way to list your most recently read books (from your Shelfari account).
Version: 0.20
License: GPL
Author: Shayne Holmes 
Author URI: http://www.et.byu.edu/~cambium/blog/
*/

/*
  Note

  This is *largely* based on the WP Audioscrobbler plugin
  by Marc Hodges (http://sevennine.net/).
 */

class WP_BookList extends WP_Widget {

  // Constructor
  function WP_BookList() {
    $widget_ops = array('description' => __('Put a list of books from Shelfari on your sidebar', 'wp-booklist'));
    $this->WP_Widget('booklist', __('BookList'), $widget_ops);
    $this->version = "0.20";
    $this->default_settings = array(
        'title' => 'BookList',
        'cache_expire' => '60',
        'list' => 'IsRead',
        'emptymsg' => '<p>No recently read books.</p>',
        'template' => <<<EOF
<p><a href="%link%" title="%fulltitle%">
<img src="%imgurl%" style="float:right;clear:right"
height="75" width="50" />
%title%</a> by %author%
<div style="clear:right"> </div></p>
EOF
        ,
        'limit' => '5',
        'trunc' => '30',
        );
    $this->list_names = array("Shelf" => "My Shelf",
        /*
        // these aren't pertinent to individual accounts, but
        // they are in Shelfari's web services...
        "None" => "None", // why does Shelfari provide this?
        "ClubRead" => "Read (Club)",
        "ClubToRead" => "To Read (Club)",
        "ClubSummary" => "Summary (Club)"
        "BooksInCommon" => "???",
         */
        "Top" => "Favorites",
        "IsRead" => "I've read",
        "IsOwned" => "Own",
        "Reading" => "I plan to read",
        "NowReading" => "I'm reading",
        "Wish" => "Wish List"
        );
  }

  function widget($args, $instance) {

    // $args is an array of strings that help widgets to conform to
    // the active theme: before_widget, before_title, after_widget,
    // and after_title are the array keys. Default tags: li and h2.
    extract($args);

    $title = $instance['title'];

    // These lines generate our output. Widgets can be very complex
    // but as you can see here, they can also be very, very simple.
    echo $before_widget . $before_title . $title . $after_title;

    $bl = new booklist($instance, 'booklist_widget_' . $this->number);
    echo $bl->get_info('recent_books');
    unset($bl);

    echo $after_widget;
  }

  function update($new_instance, $old_instance) {

    if (!isset($new_instance['submit'])) {
      return false;
    }
    $instance = $old_instance;

    $instance['title']        = strip_tags(stripslashes($new_instance['title']));
    $instance['version']      = $this->version;
    $instance['username']     = strip_tags(stripslashes($new_instance['username']));
    $instance['cache_expire'] = strip_tags(stripslashes($new_instance['cache_expire']));
    $instance['list']         = strip_tags(stripslashes($new_instance['list']));
    $instance['emptymsg']     = stripslashes($new_instance['emptymsg']);
    $instance['template']     = stripslashes($new_instance['template']);
    $instance['limit']        = strip_tags(stripslashes($new_instance['limit']));
    $instance['trunc']        = strip_tags(stripslashes($new_instance['trunc']));

    if ($new_instance['reset_to_defaults']) {
      $instance = array_merge($instance, $this->default_settings); // $defaults overrides $instance
    }

    // invalidate cache on update and explicit cache clear
    if ($new_instance['clear_cache'] || ($old_instance !== $instance)) {
      update_option('widget_booklist_' . $this->number . '_cache_ts', 0);
    }

    return $instance;
  }

  function form($instance) {
    $instance = wp_parse_args((array) $instance, $this->default_settings);

    if ($instance['version'] != $this->version){
      $instance['version'] = $this->version;
    }

    // Be sure you format your options to be valid HTML attributes.
    $title = htmlspecialchars($instance['title'], ENT_QUOTES);
    $version = htmlspecialchars($instance['version'], ENT_QUOTES);
    $username = htmlspecialchars($instance['username'], ENT_QUOTES);
    $cache_expire = htmlspecialchars($instance['cache_expire'], ENT_QUOTES);
    $list = htmlspecialchars($instance['list'], ENT_QUOTES);
    $emptymsg = htmlspecialchars($instance['emptymsg'], ENT_QUOTES);
    $template = htmlspecialchars($instance['template'], ENT_QUOTES);
    $limit = htmlspecialchars($instance['limit'], ENT_QUOTES);
    $trunc = htmlspecialchars($instance['trunc'], ENT_QUOTES);

    ?>
      <input type="hidden" id="<?php echo $this->get_field_id('version'); ?>" name="<?php echo $this->get_field_name('version'); ?>" value="<?php echo $version; ?>" />

      <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'wp-booklist'); ?>
      <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label>

      <label for="<?php echo $this->get_field_id('username'); ?>"><?php _e('Shelfari Username:', 'wp-booklist'); ?>
      <input class="widefat" id="<?php echo $this->get_field_id('username'); ?>" name="<?php echo $this->get_field_name('username'); ?>" type="text" value="<?php echo $username; ?>" /></label>

      <label for="<?php echo $this->get_field_id('cache_expire'); ?>"><?php _e('Cache Expiration (in minutes):', 'wp-booklist'); ?>
      <input class="widefat" id="<?php echo $this->get_field_id('cache_expire'); ?>" name="<?php echo $this->get_field_name('cache_expire'); ?>" type="text" value="<?php echo $cache_expire; ?>" /></label>

      <label for="<?php echo $this->get_field_id('list'); ?>"><?php _e('List to Display:', 'wp-booklist'); ?>
      <select id="<?php echo $this->get_field_id('list'); ?>" name="<?php echo $this->get_field_name('list'); ?>">
      <?php foreach($this->list_names as $list_id => $list_title) { ?>
        <option value="<?php echo $list_id; ?>"<?php selected($list_id, $list); ?>><?php echo $list_title; ?></option>;
      <?php } ?>
      </select>
      </label>

      <label for="<?php echo $this->get_field_id('emptymsg'); ?>"><?php _e('Empty Message:', 'wp-booklist'); ?>
      <input class="widefat" id="<?php echo $this->get_field_id('emptymsg'); ?>" name="<?php echo $this->get_field_name('emptymsg'); ?>" type="text" value="<?php echo $emptymsg; ?>" /></label>

      <label for="<?php echo $this->get_field_id('template'); ?>"><?php _e('Template:', 'wp-booklist'); ?>
      <a href="#" title="Valid tags: %author%, %title%, %fulltitle%, %imgurl%, %link%">(?)</a>

      <textarea class="widefat" rows="5" cols="10" id="<?php echo $this->get_field_id('template'); ?>" name="<?php echo $this->get_field_name('template'); ?>"><?php echo $template; ?></textarea>
      </label>

      <label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('How many books to show?:', 'wp-booklist'); ?>
      <input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit; ?>" /></label>

      <label for="<?php echo $this->get_field_id('trunc'); ?>"><?php _e('How long can the titles get?:', 'wp-booklist'); ?>
      <input class="widefat" id="<?php echo $this->get_field_id('trunc'); ?>" name="<?php echo $this->get_field_name('trunc'); ?>" type="text" value="<?php echo $trunc; ?>" /></label>

      <p>
      <input id="<?php echo $this->get_field_id('clear_cache'); ?>" name="<?php echo $this->get_field_name('clear_cache'); ?>" type="checkbox">
      <label for="<?php echo $this->get_field_id('clear_cache'); ?>"><?php _e('Clear cache?', 'wp-booklist'); ?></label>
      <br />
      <input id="<?php echo $this->get_field_id('reset_to_defaults'); ?>" name="<?php echo $this->get_field_name('reset_to_defaults'); ?>" type="checkbox">
      <label for="<?php echo $this->get_field_id('reset_to_defaults'); ?>"><?php _e('Reset to defaults?', 'wp-booklist'); ?></label>
      </p>

      <input type="hidden" id="<?php echo $this->get_field_id('submit'); ?>" name="<?php echo $this->get_field_name('submit'); ?>" value="1" />
      <?php
  }

}
// end widget code

// start pre-widget backwards-compatibility code
function booklist_settings_panel() {

	$bl_setting = get_option('booklist_settings');
	$bl_ver = '0.01b';

	if ($bl_setting['version'] != $bl_ver){
		$bl_setting['version'] = $bl_ver;
		update_option('booklist_settings', $bl_setting);
		$bl_setting = get_option('booklist_settings');
	}

	//if form was submitted
	if(isset($_POST['submitted'])){

		if (isset($_POST['clear_cache']))
			update_option('booklist_cache_ts', 0);

		$new_settings = array(
				'version'		=> $_POST['version'],
				'username'		=> $_POST['username'],
				'cache_expire' => $_POST['cache_expire'],
				'list'		=> stripslashes($_POST['list']),
				'emptymsg'	=> stripslashes($_POST['emptymsg']),
				'template'	=> stripslashes($_POST['template']),
				'limit'		=> $_POST['limit'],
				'trunc'		=> $_POST['trunc'],
				);

		if ($new_settings != $bl_setting){
			update_option('booklist_settings', $new_settings);
			$bl_setting = $new_settings;
		}
	}//end if submitted

	//if seeing the plugin admin for the first time
	//or user wants to reset to defaults
	if (empty($bl_setting) || $_POST['reset_to_defaults']){
		$bl_setting_default = array(
				'version' => $bl_ver,
				'username' => '',
				'cache_expire' => '60',
				'list' => 'Shelf',
				'emptymsg' => "<p>No recently read books.</p>",
				'template' =>
				'<p><a href="%link%" title="%fulltitle%">'.
				'%title%</a> by %author%</p>',
				'limit' => '5',
				'trunc' => '30',
				);
		update_option('booklist_settings', $bl_setting_default);
		$bl_setting = $bl_setting_default;
	}//end default settings

	//so people can view " in the form
	function html_entities($s){
		$s = str_replace('"', '&quot;', $s);
		return $s;
	}
?>
		<div class="wrap">
		<h2>BookList Settings</h2>
		<div class="error use-widgets-instead"><p>
		Note: this settings page is for inserting a <code>booklist()</code> call into your theme, which is deprecated. Try using the Widgets menu instead.
		</p></div>
		<form name="bl-settings" action="<?php echo $_SERVER[PHP_SELF];?>?page=booklist.php" method="post">
		<input type="hidden" name="version" value="<?php echo html_entities($bl_setting['version']); ?>" />
		<table width="100%" cellspacing="2" cellpadding="5" class="editform" summary="WP BookList Settings">
		<tr valign="top">
		<th scope="row" width="33%"><label for="username">Username:</label></th>
		<td><input name="username" type="text" size="40" value="<?php echo html_entities($bl_setting['username']); ?>" class="code"/>
		<br/>Your shelfari username.
		<br/><code>http://www.shelfari.com/username</code></td>
		</tr>
		</table>

		<fieldset>
		<legend>Cache Settings</legend>
		<table width="100%" cellspacing="2" cellpadding="5" class="editform" summary="WP BookList Settings">
		<tr valign="top">
		<th scope="row" width="33%"><label for="cache_expire">Cache Expire:</label></th>
		<td><input name="cache_expire" type="text" size="10" value="<?php echo html_entities($bl_setting['cache_expire']); ?>" class="code"/> minutes
		<br/>How often your recent books list will get updated. It is recomended that you don't set it lower then an hour, so that <a href="http://www.shelfari.com/">shelfari</a> isn't overwelmed with requests.
		<br/>example: <code>60</code> minutes = 1 hour</td>
		</tr>
		<tr valign="top">
		<th scope="row" width="33%"><label for="clear_cache">Reset Cache:</label></th>
		<td><input name="clear_cache" type="checkbox" />
		Do you want to reset the stored data?</td>
		</tr>
		</table>
		</fieldset>

		<br />

		<fieldset>
		<legend>Book Listing</legend>
		<table width="100%" cellspacing="2" cellpadding="5" class="editform" summary="WP BookList Settings">
		<tr valign="top">
		<th scope="row" width="33%"><label for="list">Book List:</label></th>
		<td><select name="list"><?php
		$listItems = array("Shelf" => "My Shelf",
				/*
				// these aren't pertinent to individual accounts, but
				// they are in Shelfari's web services...
				"None" => "None", // why does Shelfari provide this?
				"ClubRead" => "Read (Club)",
				"ClubToRead" => "To Read (Club)",
				"ClubSummary" => "Summary (Club)"
				"BooksInCommon" => "???",
				 */
				"Top" => "Favorites",
				"IsRead" => "I've read",
				"IsOwned" => "Own",
				"Reading" => "I plan to read",
				"NowReading" => "I'm reading",
				"Wish" => "Wish List"
				);
	foreach($listItems as $i => $title){
		$selected = (strcmp($bl_setting['list'],$i) == 0) ? ' selected': '';
		_e("<option value=\"$i\"$selected> $title </option>\n");
	}// end for
	?></select>
		<br />Which of the lists tied to your username
		the book list will be pulled from.
		</td>
		</tr>
		<tr valign="top">
		<th scope="row" width="33%"><label for="emptymsg">No Books Message:</label></th>
		<td><input name="emptymsg" type="text" size="30" value="<?php echo html_entities($bl_setting['emptymsg']); ?>" class="code"/>
		<br/>This is the message that will appear when there are no books listed on shelfari.</td>
		</tr>
		<tr valign="top">
		<th scope="row" width="33%"><label for="limit">Number of Books:</label></th>
		<td><select name="limit"><?php
		for($i=1;$i<=10;$i++){
			$selected = ($bl_setting['limit'] == $i) ? '  selected': '';
			_e("<option value=\"$i\"$selected> $i </option>\n");
		}// end for
	?></select>
		<br/>The number of books that will be displayed.</td>
		</tr>
		<tr valign="top">
		<th scope="row" width="33%"><label for="template">Display Template:</label></th>
		<td><input name="template" type="text" size="60" value="<?php echo html_entities($bl_setting['template']); ?>" class="code"/>
		<br/>The layout for each book in the list.
		<br/>example: <code>&lt;p&gt;&lt;a href="%link%" title="%fulltitle%"&gt;%title%&lt;/a&gt; by %author%&lt;/p&gt;</code>
		<br/>Available tags:
		<br/><code>%title%</code> -> the book title (cropped, see truncation length below)
		<br/><code>%fulltitle%</code> -> the book title
		<br/><code>%author%</code> -> the book author
		<br/><code>%link%</code> -> the URL to the track's page on <a href="http://www.amazaon.com/">amazon</a>, if no URL exists, will return a '#'
		<br/><code>%imgurl%</code> -> the URL to the cover image (<code> &lt;img src="%imgurl%"/&gt;</code>)
		</td>
		</tr>
		<tr valign="top">
		<th scope="row" width="33%"><label for="trunc">Truncation Length:</label></th>
		<td><input name="trunc" type="text" size="10" value="<?php echo html_entities($bl_setting['trunc']); ?>" class="code"/> characters
		<br/>How many characters (at a maximum) in %title%. Use this to lessen the effect of super-long titles in your listing.
		<br/>example: <code>30</code> characters = "The Complete Stories of..."</td>
		</tr>
		</fieldset>
		</table>

		<br />

		<fieldset>
		<legend>
		Reset to Defaults:
		</legend>
		<table width="100%" cellspacing="2" cellpadding="5" class="editform" summary="WP BookList Settings">
		<tr valign="top">
		<th scope="row" width="33%"><label for="reset_to_defaults">Reset to Defaults:</label></th>
		<td><input name="reset_to_defaults" type="checkbox" />
		Do you want to reset all settings to defaults?</td>
		</tr>
		</fieldset>
		</table>
		<p class="submit"><input type="hidden" name="submitted" /><input type="submit" name="Submit" value="<?php _e($rev_action);?> Update Settings &raquo;" /></p>
		</form>
		</div> <!-- wrap -->

		<?php
} /* end function: booklist_settings_menu */

function booklist($get='recent_books'){
	$bl = new booklist();
	echo $bl->get_info($get);
	unset($bl);
}

// Hook for admin menu
function booklist_admin_menu(){
	add_submenu_page('plugins.php', 'BookList', 'BookList',
			8, basename(__FILE__), 'booklist_settings_panel');
}
add_action('admin_menu', 'booklist_admin_menu');

// Make sure we have prereq's
function booklist_require(){
  require_once(dirname(__FILE__).'/classes.php');
}
add_action('init', 'booklist_require');

function widget_booklist_init() {
    register_widget('WP_BookList');
}
add_action('widgets_init', 'widget_booklist_init');

?>
