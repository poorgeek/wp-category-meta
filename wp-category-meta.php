<?php
/*
 * Plugin Name: wp-category-meta
 * Description: Add the ability to attach meta to the Wordpress categories and other taxonomies
 * Version: 1.3.0.beta
 * Author: josecoelho, Randy Hoyt, steveclarkcouk, Vitaliy Kukin, Eric Le Bail, Tom Ransom, Pavel Riha (Papik81)
 * Author URI: http://randyhoyt.com/
 *
 * This plugin has been developped and tested with Wordpress Version 3.3.1
 *
 * Copyright 2012  Randy Hoyt (randyhoyt.com)
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 *
 */

if ( !defined('WP_CONTENT_DIR') )
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if (!defined('DIRECTORY_SEPARATOR'))
{
    if (strpos(php_uname('s'), 'Win') !== false )
    define('DIRECTORY_SEPARATOR', '\\');
    else
    define('DIRECTORY_SEPARATOR', '/');
}
//$pluginPath = ABSPATH.PLUGINDIR.DIRECTORY_SEPARATOR."wp-category-meta";
$pluginPath = plugin_dir_url( __FILE__ );
define('WPTM_PATH', $pluginPath);
$filePath = $pluginPath.'/'.basename(__FILE__);
$absolutePath = dirname(__FILE__).DIRECTORY_SEPARATOR;
define('WPTM_ABSPATH', $absolutePath);

// Initialization and Hooks
global $wpdb;
global $wp_version;
global $wptm_version;
global $wptm_db_version;
global $wptm_table_name;
global $wp_version;
$wptm_version = '1.3.0';
$wptm_db_version = '0.0.1';
$wptm_table_name = $wpdb->prefix.'termsmeta';
// register termsmeta table
$wpdb->termsmeta = $wptm_table_name;


register_activation_hook(__FILE__,'wptm_full_install');
if($wp_version >= '2.7') {
    register_uninstall_hook(__FILE__,'wptm_full_uninstall');
} else {
    register_deactivation_hook(__FILE__,'wptm_full_uninstall');
}

// Actions
add_action('admin_init', 'wptm_init');

add_filter('admin_enqueue_scripts','wptm_admin_enqueue_scripts');

if (is_admin()) {
    include ( WPTM_ABSPATH  . 'views'.DIRECTORY_SEPARATOR.'options.php' );
    $WPTMAdmin = new wptm_admin();
}

/**
 * Multisite propagation function
 * @return void
*/
function wptm_network_propagate($pfunction, $networkwide) {
    global $wpdb;
 
    if (function_exists('is_multisite') && is_multisite()) {
        // check if it is a network activation - if so, run the activation function 
        // for each blog id
        if ($networkwide) {
            $old_blog = $wpdb->blogid;
            // Get all blog ids
            $blogids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
            foreach ($blogids as $blog_id) {
                switch_to_blog($blog_id);
                call_user_func($pfunction, $networkwide);
            }
            switch_to_blog($old_blog);
            return;
        }   
    } 
    call_user_func($pfunction, $networkwide);
}
/**
 * Multisite install function
 * @return void
*/
function wptm_full_install($networkwide) {
    wptm_network_propagate('wptm_install', $networkwide);    
}
/**
 * Multisite uninstall function
 * @return void
*/
function wptm_full_uninstall($networkwide) {
    wptm_network_propagate('wptm_uninstall', $networkwide);    
}
/**
 * Function on adding site on multisite
 * @return void
 */
add_action( 'wpmu_new_blog', 'wptm_new_blog', 10, 6);        
 
function wptm_new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta ) {
    global $wpdb;
	$old_blog = $wpdb->blogid;
    if (is_plugin_active_for_network('wp-category-meta.php')) {
        switch_to_blog($blog_id);
        wptm_install();
        switch_to_blog($old_blog);
    }
}
/**
 * Function called when installing or updgrading the plugin.
 * @return void.
 */
function wptm_install()
{
    global $wpdb;
    global $wptm_db_version;
	// table name for current site
	$wptm_table_name = $wpdb->prefix."termsmeta";
    // create table on first install
    if($wpdb->get_var("show tables like '$wptm_table_name'") != $wptm_table_name) {
        wptm_createTable($wpdb, $wptm_table_name);
        add_option("wptm_db_version", $wptm_db_version);
        add_option("wptm_configuration", array());
    }

    // On plugin update only the version nulmber is updated.
    $installed_ver = get_option( "wptm_db_version" );
    if( $installed_ver < $wptm_db_version ) {

        update_option( "wptm_db_version", $wptm_db_version );
    }
	// Clear orphaned termsmeta
	
	$wpdb->query("DELETE meta FROM $wptm_table_name meta LEFT JOIN  $wpdb->terms term ON term.term_id = meta.terms_id WHERE term.term_id IS NULL");

}

/**
 * Function called when un-installing the plugin.
 * @return void.
 */
function wptm_uninstall()
{
    global $wpdb;
    global $wptm_table_name;

    // delete table
    if($wpdb->get_var("show tables like '$wptm_table_name'") == $wptm_table_name) {

        wptm_dropTable($wpdb, $wptm_table_name);
    }
    delete_option("wptm_db_version");
    delete_option("wptm_configuration");
}

/**
 * Function that creates the wptm table.
 *
 * @param $wpdb : database manipulation object.
 * @param $table_name : name of the table to create.
 * @return void.
 */
function wptm_createTable($wpdb, $table_name)
{
    $sql = "CREATE TABLE  ".$table_name." (
          meta_id bigint(20) NOT NULL auto_increment,
          terms_id bigint(20) NOT NULL default '0',
          meta_key varchar(255) default 'text',
          meta_value longtext,
          PRIMARY KEY  (`meta_id`),
          KEY `terms_id` (`terms_id`),
          KEY `meta_key` (`meta_key`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";

    $results = $wpdb->query($sql);
}

/**
 * Function that drops the plugin table.
 *
 * @param $wpdb : database manipulation object.
 * @param $table_name : name of the table to create.
 * @return void.
 */
function wptm_dropTable($wpdb, $table_name)
{
    $sql = "DROP TABLE  ".$table_name." ;";

    $results = $wpdb->query($sql);
}

/**
 * Function that initialise the plugin.
 * It loads the translation files.
 *
 * @return void.
 */
function wptm_init() {
    global $wp_version;
    if (function_exists('load_plugin_textdomain')) {
    	load_plugin_textdomain('wp-category-meta', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/');       
    }
    else
    {
        // Load language file
        $locale = get_locale();
        if ( !empty($locale) )
        load_textdomain('wp-category-meta', WPTM_ABSPATH.'lang/wp-category-meta-'.$locale.'.mo');
    }
    if($wp_version >= '3.0') {
        add_action('created_term', 'wptm_save_meta_tags');
        add_action('edit_term', 'wptm_save_meta_tags');
        add_action('delete_term', 'wptm_delete_meta_tags');
        $wptm_taxonomies=get_taxonomies('','names');
        if (is_array($wptm_taxonomies) )
        {
            foreach ($wptm_taxonomies as $wptm_taxonomy ) {
                add_action($wptm_taxonomy . '_add_form_fields', 'wptm_add_meta_textinput');
                add_action($wptm_taxonomy . '_edit_form', 'wptm_add_meta_textinput');
            }
        }
    } else {
        add_action('create_category', 'wptm_save_meta_tags');
        add_action('edit_category', 'wptm_save_meta_tags');
        add_action('delete_category', 'wptm_delete_meta_tags');
        add_action('edit_category_form', 'wptm_add_meta_textinput');
		do_action('wptm_init');
    }
}

/**
 * Add the loading of needed javascripts for admin part.
 *
 */
function wptm_admin_enqueue_scripts() {
	global $pagenow,$wptm_version, $wp_version, $wp_locale;;
    if( 'edit-tags.php' == $pagenow ) {
        // chargement des styles
        wp_register_style('thickbox-css', '/wp-includes/js/thickbox/thickbox.css');
        wp_enqueue_style('thickbox-css');
        // Chargement des javascripts
        wp_enqueue_script('thickbox');
        wp_enqueue_script('media-upload');
        wp_enqueue_script('quicktags');
        wp_enqueue_script('wp-category-meta-scripts',plugins_url('js/wp-category-meta-scripts.js', __FILE__),array('jquery'),false, $wptm_version);
		wp_enqueue_style('wptm_style', plugins_url('css/wp-category-meta.css', __FILE__),false,$wptm_version);
		if ($wp_version >= '3.5.0') 
		{
			// Color Picker
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp_color-picker-script', plugins_url('js/wp-category-meta-color.js', __FILE__ ), array( 'jquery','wp-color-picker' ), false, $wptm_version );
			// Date Picker
			wp_enqueue_script('jquery-ui-datepicker', plugins_url('js/jquery.ui.datepicker.js', __FILE__ ), array( 'jquery' ), false, $wptm_version );
			wp_enqueue_script( 'wp_date-picker-script', plugins_url('js/wp-category-meta-date.js', __FILE__ ), array( 'jquery','jquery-ui-datepicker' ), false, $wptm_version );
					      
			//localize our js
			$aryArgs = array(
				'closeText'         => __( 'Done', 'wp-category-meta'),
				'currentText'       => __( 'Today', 'wp-category-meta' ),
				'monthNames'        => strip_array_indices( $wp_locale->month ),
				'monthNamesShort'   => strip_array_indices( $wp_locale->month_abbrev ),
				'monthStatus'       => __( 'Show a different month' ,'wp-category-meta'),
				'dayNames'          => strip_array_indices( $wp_locale->weekday ),
				'dayNamesShort'     => strip_array_indices( $wp_locale->weekday_abbrev ),
				'dayNamesMin'       => strip_array_indices( $wp_locale->weekday_initial ),
				// set the date format to match the WP general date settings
				'dateFormat'        => date_format_php_to_js( get_option( 'date_format' ) ),
				// get the start of week from WP general setting
				'firstDay'          => get_option( 'start_of_week' ),
				// is Right to left language? default is false
				'isRTL'             => isset($wp_locale->text_direction) ? ($wp_locale->text_direction == "rtl" ? true : false) : (isset($wp_locale->is_rtl) ? $wp_locale->is_rtl: false)
			);
			// filter to change settings
			$aryArgs = apply_filters('wptm_localized_date',$aryArgs);
			wp_localize_script( 'jquery-ui-datepicker', 'wptm_date', $aryArgs );
			//extend settings
			$aryArgs = array(
			'changeMonth' => true, 
			'changeYear' => true,
			'showOtherMonths'=> false,
			'selectOtherMonths'=> false, 
			'showWeek' => false, 
			'calculateWeek'=> 'this.iso8601Week',
			'shortYearCutoff'=> "+10" 
			);
			$aryArgs = apply_filters('wptm_localized_date_extended',$aryArgs);
			wp_localize_script( 'jquery-ui-datepicker', 'wptm_date_ex', $aryArgs );
			
			wp_enqueue_style('jquery-style', WPTM_PATH .'css/jquery-ui.css',false,$wptm_version);
		}
		do_action('wptm_admin_enqueue_scripts',$wptm_version);
    }
}
/**
 * Format array for the datepicker
 *
 * WordPress stores the locale information in an array with a alphanumeric index, and
 * the datepicker wants a numerical index. This function replaces the index with a number
 */
if(!function_exists('strip_array_indices')) {
	function strip_array_indices( $ArrayToStrip ) {
		foreach( $ArrayToStrip as $objArrayItem) {
			$NewArray[] =  $objArrayItem;
		}
	 
		return( $NewArray );
	}
}
/**
 * Convert the php date format string to a js date format
 */
	function date_format_php_to_js( $sFormat ) {
		switch( $sFormat ) {
			//Predefined WP date formats
			case 'F j, Y':
				return( 'MM dd, yy' );
				break;
			case 'Y/m/d':
				return( 'yy/mm/dd' );
				break;
			case 'm/d/Y':
				return( 'mm/dd/yy' );
				break;
			case 'd/m/Y':
				return( 'dd/mm/yy' );
				break;
			default:
			  return ( 'dd-mm-yy' );
		 }
	}


/**
 * add_terms_meta() - adds metadata for terms
 *
 *
 * @param int $terms_id terms (category/tag...) ID
 * @param string $key The meta key to add
 * @param mixed $value The meta value to add
 * @param bool $unique whether to check for a value with the same key
 * @return bool
 */
 if (!function_exists("add_terms_meta")) {
	function add_terms_meta($terms_id, $meta_key, $meta_value, $unique = false) {

		global $wpdb;
		global $wptm_table_name;

		// expected_slashed ($meta_key)
		$meta_key = stripslashes( $meta_key );
		$meta_value = stripslashes( $meta_value );

		if ( $unique && $wpdb->get_var( $wpdb->prepare( "SELECT meta_key FROM $wptm_table_name WHERE meta_key = %s AND terms_id = %d", $meta_key, $terms_id ) ) )
		return false;

		$meta_value = maybe_serialize($meta_value);

		$wpdb->insert( $wptm_table_name, compact( 'terms_id', 'meta_key', 'meta_value' ) );

		wp_cache_delete($terms_id, 'terms_meta');

		return true;
	}
}

/**
 * delete_terms_meta() - delete terms metadata
 *
 *
 * @param int $terms_id terms (category/tag...) ID
 * @param string $key The meta key to delete
 * @param mixed $value
 * @return bool
 */
if(!function_exists("delete_terms_meta"))
{
	function delete_terms_meta($terms_id, $key, $value = '') {

		global $wpdb;
		global $wptm_table_name;

		// expected_slashed ($key, $value)
		$key = stripslashes( $key );
		$value = stripslashes( $value );

		if ( empty( $value ) )
		{
			$sql1 = $wpdb->prepare( "SELECT meta_id FROM $wptm_table_name WHERE terms_id = %d AND meta_key = %s", $terms_id, $key );
			$meta_id = $wpdb->get_var( $sql1 );
		} else {
			$sql2 = $wpdb->prepare( "SELECT meta_id FROM $wptm_table_name WHERE terms_id = %d AND meta_key = %s AND meta_value = %s", $terms_id, $key, $value );
			$meta_id = $wpdb->get_var( $sql2 );
		}

		if ( !$meta_id )
		return false;

		if ( empty( $value ) )
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wptm_table_name WHERE terms_id = %d AND meta_key = %s", $terms_id, $key ) );
		else
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wptm_table_name WHERE terms_id = %d AND meta_key = %s AND meta_value = %s", $terms_id, $key, $value ) );

		wp_cache_delete($terms_id, 'terms_meta');

		return true;
	}
}

/**
 * get_terms_meta() - Get a terms meta field
 *
 *
 * @param int $terms_id terms (required) (category/tag...) ID
 * @param string $key (required) The meta key to retrieve
 * @param bool $single (optional) Whether to return a single value
 * @return mixed The meta value or meta value list
 */
 if (!function_exists("get_terms_meta")) {
	function get_terms_meta($terms_id, $key, $single = false) {

		$terms_id = (int) $terms_id;

		$meta_cache = wp_cache_get($terms_id, 'terms_meta');

		if ( !$meta_cache ) {
			update_termsmeta_cache($terms_id);
			$meta_cache = wp_cache_get($terms_id, 'terms_meta');
		}

		if ( isset($meta_cache[$key]) ) {
			if ( $single ) {
				return maybe_unserialize( $meta_cache[$key][0] );
			} else {
				return array_map('maybe_unserialize', $meta_cache[$key]);
			}
		}

		return '';
	}
}

/**
 * get_terms_meta_by() - Get a terms meta field
 *
 *
 * @param string $field (required) - 'id','slug' or 'name'
 * @param string or int $value (required) terms (category/tag...) 
 * @param string $taxonomy (required) - taxonomy name
 * @param string $key (required) The meta key to retrieve
 * @param bool $single (optional) Whether to return a single value
 * @return mixed The meta value or meta value list
 */
 if (!function_exists("get_terms_meta_by")) {
	function get_terms_meta_by($field, $value, $taxonomy, $key, $single = false) {
		$field = trim($field);
		if($field == "name" || $field == "slug" || $field == "id")
		{
			if ($field == "id") $value = (int) $value;
			$term = get_term_by($field,$value,$taxonomy);
		}
		else 
		{
			$term = get_term_by($field,$value,$taxonomy);
			
		}
		
		if ($term !== false) $terms_id = $term->term_id;
		else return '';
		

		$meta_cache = wp_cache_get($terms_id, 'terms_meta');

		if ( !$meta_cache ) {
			update_termsmeta_cache($terms_id);
			$meta_cache = wp_cache_get($terms_id, 'terms_meta');
		}

		if ( isset($meta_cache[$key]) ) {
			if ( $single ) {
				return maybe_unserialize( $meta_cache[$key][0] );
			} else {
				return array_map('maybe_unserialize', $meta_cache[$key]);
			}
		}

		return '';
	}
}

/**
 * get_all_terms_meta_by() - Get all meta fields for a terms (category/tag...)
 *
 *
 * @param string $field (required) - 'id','slug' or 'name'
 * @param string or int $value (required) terms (category/tag...) 
 * @param string $taxonomy (required) - taxonomy name
 * @return array The meta (key => value) list
 */
 if(!function_exists("get_all_terms_meta_by")){
	function get_all_terms_meta_by($field, $value, $taxonomy) {
$field = trim($field);
		if($field == "name" || $field == "slug" || $field == "id")
		{
			if ($field == "id") $value = (int) $value;
			$term = get_term_by($field,$value,$taxonomy);
		}
		else 
		{
			$term = get_term_by($field,$value,$taxonomy);
			
		}
		
		if ($term !== false) $terms_id = $term->term_id;
		else return array();
		$meta_cache = wp_cache_get($terms_id, 'terms_meta');

		if ( !$meta_cache ) {
			update_termsmeta_cache($terms_id);
			$meta_cache = wp_cache_get($terms_id, 'terms_meta');
		}

		return maybe_unserialize( $meta_cache );

	}
}

/**
 * get_all_terms_meta() - Get all meta fields for a terms (category/tag...)
 *
 *
 * @param int $terms_id terms (category/tag...) ID
  * @param int $terms_id terms (category/tag...) ID
 * @return array The meta (key => value) list
 */
 if(!function_exists("get_all_terms_meta")){
	function get_all_terms_meta($terms_id) {

		$terms_id = (int) $terms_id;

		$meta_cache = wp_cache_get($terms_id, 'terms_meta');

		if ( !$meta_cache ) {
			update_termsmeta_cache($terms_id);
			$meta_cache = wp_cache_get($terms_id, 'terms_meta');
		}

		return maybe_unserialize( $meta_cache );

	}
}

/**
 * get_all_terms_meta() - Get all meta fields for a terms (category/tag...)
 *
 *
 * @param int $terms_id terms (category/tag...) ID
 * @return array The meta (key => value) list
 */
 if(!function_exists("get_all_terms_meta_by")){
	function get_all_terms_meta($terms_id) {

		$terms_id = (int) $terms_id;

		$meta_cache = wp_cache_get($terms_id, 'terms_meta');

		if ( !$meta_cache ) {
			update_termsmeta_cache($terms_id);
			$meta_cache = wp_cache_get($terms_id, 'terms_meta');
		}

		return maybe_unserialize( $meta_cache );

	}
}

/**
 * update_terms_meta() - Update a terms meta field
 *
 *
 * @param int $terms_id terms (category/tag...) ID
 * @param string $key The meta key to update
 * @param mixed $value The meta value to update
 * @param mixed $prev_value previous value (for differentiating between meta fields with the same key and terms ID)
 * @return bool
 */
 if(!function_exists("update_terms_meta")){
	function update_terms_meta($terms_id, $meta_key, $meta_value, $prev_value = '') {

		global $wpdb;
		global $wptm_table_name;

		// expected_slashed ($meta_key)
		$meta_key = stripslashes( $meta_key );
		$meta_value = stripslashes( $meta_value );

		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT meta_key FROM $wptm_table_name WHERE meta_key = %s AND terms_id = %d", $meta_key, $terms_id ) ) ) {
			return add_terms_meta($terms_id, $meta_key, $meta_value);
		}

		$meta_value = maybe_serialize($meta_value);

		$data  = compact( 'meta_value' );
		$where = compact( 'meta_key', 'terms_id' );

		if ( !empty( $prev_value ) ) {
			$prev_value = maybe_serialize($prev_value);
			$where['meta_value'] = $prev_value;
		}

		$wpdb->update( $wptm_table_name, $data, $where );
		wp_cache_delete($terms_id, 'terms_meta');
		return true;
	}
}

/**
 * update_termsmeta_cache()
 *
 *
 * @uses $wpdb
 *
 * @param array $category_ids
 * @return bool|array Returns false if there is nothing to update or an array of metadata
 */
 if(!function_exists("update_termsmeta_cache")){
	function update_termsmeta_cache($terms_ids) {

		global $wpdb;
		global $wptm_table_name;

		if ( empty( $terms_ids ) )
		return false;

		if ( !is_array($terms_ids) ) {
			$terms_ids = preg_replace('|[^0-9,]|', '', $terms_ids);
			$terms_ids = explode(',', $terms_ids);
		}

		$terms_ids = array_map('intval', $terms_ids);

		$ids = array();
		foreach ( (array) $terms_ids as $id ) {
			if ( false === wp_cache_get($id, 'terms_meta') )
			$ids[] = $id;
		}

		if ( empty( $ids ) )
		return false;

		// Get terms-meta info
		$id_list = join(',', $ids);
		$cache = array();
		if ( $meta_list = $wpdb->get_results("SELECT terms_id, meta_key, meta_value FROM $wptm_table_name WHERE terms_id IN ($id_list) ORDER BY terms_id, meta_key", ARRAY_A) ) {
			foreach ( (array) $meta_list as $metarow) {
				$mpid = (int) $metarow['terms_id'];
				$mkey = $metarow['meta_key'];
				$mval = $metarow['meta_value'];

				// Force subkeys to be array type:
				if ( !isset($cache[$mpid]) || !is_array($cache[$mpid]) )
				$cache[$mpid] = array();
				if ( !isset($cache[$mpid][$mkey]) || !is_array($cache[$mpid][$mkey]) )
				$cache[$mpid][$mkey] = array();

				// Add a value to the current pid/key:
				$cache[$mpid][$mkey][] = $mval;
			}
		}

		foreach ( (array) $ids as $id ) {
			if ( ! isset($cache[$id]) )
			$cache[$id] = array();
		}

		foreach ( array_keys($cache) as $terms)
		wp_cache_set($terms, $cache[$terms], 'terms_meta');

		return $cache;
	}
}

/**
 * Function that saves the meta from form.
 *
 * @param $id : terms (category) ID
 * @return void;
 */
function wptm_save_meta_tags($id) {
  
    $metaList = get_option("wptm_configuration");
	// Check that the meta form is posted
    $wptm_edit = (array_key_exists("wptm_edit", $_POST))? $_POST["wptm_edit"] :null;
    if (isset($wptm_edit) && !empty($wptm_edit)) {	
        foreach($metaList as $inputName => $inputType)
        {
			$inputType['taxonomy'] = is_array($inputType['taxonomy']) ? $inputType['taxonomy'] : array($inputType['taxonomy']);
			if(in_array($_POST['taxonomy'], $inputType['taxonomy'])) {
			//if($_POST['taxonomy']== $inputType['taxonomy']) {
		   $inputValue = $_POST['wptm_'.$inputName];
		   if(!is_array($inputValue)) 
		   {
			   delete_terms_meta($id, $inputName);
			   if (isset($inputValue) && !empty($inputValue)) {
				   add_terms_meta($id, $inputName, $inputValue);
				}
		   }
		   else 
		   {   
			   $inputValue = array_values($inputValue);
			   delete_terms_meta($id, $inputName);
			   foreach($inputValue as $value)
			   {
					if (isset($inputValue) && !empty($value)) {
				   add_terms_meta($id, $inputName, $value);
					}
			   }
		   }
	    }
        }
    }
}
/**
 * Function that deletes meta names.
 *
 * @param $name : term meta name
 * @return: number of deleted rows, on WP < 3.4.0 true on success or false on fail;
 */
function wptm_delete_meta_name($name) {
	global $wpdb, $wptm_table_name, $wp_version;
	if(!isset($name)) return false;
	if ($wp_version < '3.4.0')
	{
		$sql = $wpdb->prepare( "DELETE FROM $wptm_table_name WHERE meta_key = %s", $name );
		return $wpdb->query($sql);
	 }
	 else
	 return $wpdb->delete($wptm_table_name, array( 'meta_key' => $name ), array( '%s' ));
}

/**
 * Function that update meta names.
 *
 * @param $old_name : old term meta name to be renamed
 * @param $new_name : new term meta name
 * @return: number of updated rows, on WP < 3.4.0 true on success or false on fail;;
 */
function wptm_update_meta_name($old_name, $new_name) {
	if( !isset($old_name) || !isset($new_name)) return false;
	global $wpdb, $wptm_table_name, $wp_version;
	if ($wp_version < '3.4.0')
	{
		$sql = $wpdb->prepare( "UPDATE $wptm_table_name SET meta_key = %s WHERE meta_key = %s ", $new_name, $old_name );
		return $wpdb->query($sql);
	 }
	 else
	return $wpdb->update($wptm_table_name, array( 'meta_key' => $new_name ), array('meta_key' => $old_name), array( '%s' ));
}

/**
 * Function that deletes the meta for a terms (category/..)
 *
 * @param $id : terms (category) ID
 * @return void
 */
function wptm_delete_meta_tags($id) {

    $metaList = get_option("wptm_configuration");
    foreach($metaList as $inputName => $inputType)
    {
        delete_terms_meta($id, $inputName);
    }
}

/**
 * Function that display the meta text input.
 *
 * @return void.
 */
function wptm_add_meta_textinput($tag)
{
    global $category, $wp_version, $taxonomy;
    $category_id = '';
    if($wp_version >= '3.0'&& isset($tag->term_id)) {
        $category_id = $tag->term_id;
    } else {
        $category_id = $category;
    }
    $metaList = get_option("wptm_configuration");
    if (is_object($category_id)) {
        $category_id = $category_id->term_id;
    }
	$metadata_label = __('This additional data is attached to the current term:', 'wp-category-meta');
	
    if(!is_null($metaList) && count($metaList) > 0 && $metaList != '')
    {
		// don't show field when no meta key are assigned to and return
		$selected_taxonomies = array();
		foreach($metaList as $inputName => $inputData)
		{	
			$inputTaxonomy = is_array($inputData['taxonomy']) ? $inputData['taxonomy'] : array($inputData['taxonomy']);		
			$selected_taxonomies = array_merge($inputTaxonomy,$selected_taxonomies);
			
		}
		$selected_taxonomies = array_unique($selected_taxonomies);
		if(!in_array($taxonomy,$selected_taxonomies)) {
			$metadata_label = __('There are no data to the current term.', 'wp-category-meta');
		};
        ?>
<div id="categorymeta" class="postbox">
<h3 class='hndle'><span><?php _e('Term meta', 'wp-category-meta');?></span></h3>
<div class="inside"><input value="wptm_edit" type="hidden"
	name="wptm_edit" /> <input type="hidden" name="image_field"
	id="image_field" value="" />
<table class="form-table">
<tr><td><?php echo $metadata_label;?></td></tr>
<?php
foreach($metaList as $inputName => $inputData)
{
    $inputType = '';
    $inputTaxonomy = 'category';
    if(is_array($inputData)) {
        $inputType = $inputData['type'];
        $inputTaxonomy = is_array($inputData['taxonomy']) ? $inputData['taxonomy'] : array($inputData['taxonomy']);
		$inputLabel = !empty($inputData['label']) ? htmlspecialchars(stripcslashes($inputData['label'])) : $inputType;
		$inputDescription = !empty($inputData['description']) ? htmlspecialchars(stripcslashes($inputData['description'])) : '';
    } else {
        $inputType = $inputData;
		$inputLabel = $inputData;
		$inputTaxonomy = array('category');
		$inputDescription = '';
    }
    // display the input field in 2 cases
    // WP version if < 3.0
    // or WP version > 3.0 and $inputTaxonomy == current taxonomy
    if($wp_version < '3.0' || in_array($taxonomy, $inputTaxonomy) ) {
    //if($wp_version < '3.0' || $inputTaxonomy == $taxonomy ) {
        $inputValue = htmlspecialchars(stripcslashes(get_terms_meta($category_id, $inputName, true)));
		/**
		* Text
		*/
        if($inputType == 'text')
        {
            ?>
	<tr>
		<td><label for="<?php echo 'wptm_'.$inputName;?>"><strong><?php echo $inputLabel;?>:</strong></label><br />
		<input value="<?php echo $inputValue ?>" type="text" size="40"
			name="<?php echo 'wptm_'.$inputName;?>" /><br />
			<?php if(!empty($inputDescription)) echo '<em>'.$inputDescription.'</em><br />'; ?><br />
			
	</tr>
	<?php } 
	/**
	* Textarea
	*/
	elseif($inputType == 'textarea') { ?>
	<tr>
		<td><td><label for="<?php echo "wptm_".$inputName?>"><strong><?php echo $inputLabel;?>:</strong></label><br />
		<textarea name="<?php echo "wptm_".$inputName?>" rows="5"
			cols="50" style="width: 97%;"><?php echo $inputValue ?></textarea> <br />
			<?php if(!empty($inputDescription)) echo '<em>'.$inputDescription.'</em><br />'; ?><br /></td>
	</tr>
	<?php } 
		/**
		* Image
		*/
	elseif($inputType == 'image') {

	    $current_image_url = get_terms_meta($category_id, $inputName, true);
	  ?>
	<tr>
		<td>
		<div id="<?php echo "wptm_".$inputName;?>_selected_image"
			class="wptm_selected_image"><?php if(!empty($current_image_url)) echo '<img src="'.$current_image_url.'" width="auto" style="max-width: 400px;" />';?>
		</div>
		</td>
	</tr>
	<tr>
		<td>
		<label for="<?php echo "wptm_".$inputName;?>" class="wptm_meta_name_label"><strong><?php echo $inputLabel;?>:</strong></label><br />
		<input type="text" name="<?php echo "wptm_".$inputName;?>_url_display"
			id="<?php echo "wptm_".$inputName;?>_url_display" class="wptm_url_display" placeholder="<?php _e('No image selected', 'wp-category-meta');?>" value="<?php if ($current_image_url != '') echo $current_image_url;?>" /><br />
		<?php if(!empty($inputDescription)) echo '<em>'.$inputDescription.'</em><br />'; ?><br />
		<img src="images/media-button-image.gif"
			alt="<?php __('Add photos from your media library', 'wp-category-meta');?>" /> <a
			href="media-upload.php?type=image&#038;wptm_send_label=<?php echo $inputName; ?>&#038;TB_iframe=1&#038;tab=library&#038;height=500&#038;width=640"
			onclick="image_photo_url_add('<?php echo "wptm_".$inputName;?>')"
			class="thickbox" title="<?php _e('Add an Image', 'wp-category-meta');?>"> <strong><?php echo _e('Click here to add/change your image', 'wp-category-meta');?></strong>
		</a><br /><br />
		
		<small> <?php echo _e('Note: To choose image click the "insert into post" button in the media uploader', 'wp-category-meta');?>
		</small><br />
		
		<img src="images/media-button-image.gif" alt="<?php _e('Remove existing image', 'wp-category-meta');?>" />
		<a href="#"
			onclick="remove_image_url('<?php echo "wptm_".$inputName;?>','')">
		<strong><?php _e('Click here to remove the existing image', 'wp-category-meta');?></strong>
		</a><br />
		<input type="hidden" name="<?php echo "wptm_".$inputName;?>"
			id="<?php echo "wptm_".$inputName;?>"
			value="<?php echo $current_image_url;?>" />
		</td>
	</tr>
	<?php } 
		/**
		* File
		*/
	elseif($inputType == 'file') {

	    $current_file_url = get_terms_meta($category_id, $inputName, true);
	  ?>
	<tr>
		<td>
		<label for="<?php echo "wptm_".$inputName;?>" class="wptm_meta_name_label"><strong><?php echo $inputLabel;?>:</strong></label><br />
		<input type="text" name="<?php echo "wptm_".$inputName;?>_url_display"
			id="<?php echo "wptm_".$inputName;?>_url_display" class="wptm_url_display" placeholder="<?php _e('No file selected', 'wp-category-meta');?>" value="<?php if ($current_file_url != '') echo $current_file_url;?>" /><br />
		<?php if(!empty($inputDescription)) echo '<em>'.$inputDescription.'</em><br />'; ?><br />
		<img src="images/media-button-image.gif"
			alt="<?php __('Add photos from your media library', 'wp-category-meta');?>" /> <a
			href="media-upload.php?type=file&#038;wptm_send_label=<?php echo $inputName; ?>&#038;TB_iframe=1&#038;tab=library&#038;height=500&#038;width=640"
			onclick="image_photo_url_add('<?php echo "wptm_".$inputName;?>')"
			class="thickbox" title="<?php _e('Add a File', 'wp-category-meta');?>"> <strong><?php echo _e('Click here to add/change your file', 'wp-category-meta');?></strong>
		</a><br /><br />
		
		<small> <?php echo _e('Note: To choose file click the "insert into post" button in the media uploader', 'wp-category-meta');?>
		</small><br />
		
		<img src="images/media-button-image.gif" alt="<?php _e('Remove existing file', 'wp-category-meta');?>" />
		<a href="#"
			onclick="remove_image_url('<?php echo "wptm_".$inputName;?>','')">
		<strong><?php _e('Click here to remove the existing file', 'wp-category-meta');?></strong>
		</a><br />
		<input type="hidden" name="<?php echo "wptm_".$inputName;?>"
			id="<?php echo "wptm_".$inputName;?>"
			value="<?php echo $current_file_url;?>" />
		</td>
	</tr>
	<?php } 
	/**
	* Checkbox
	*/
	elseif($inputType == 'checkbox') { ?>
    <tr>
        <td><label for="<?php echo 'wptm_'.$inputName;?>"><strong><?php echo $inputLabel;?>:</strong></label>&nbsp;<input value="checked" type="checkbox" <?php echo $inputValue ? 'checked="checked" ' : ''; ?>
            name="<?php echo 'wptm_'.$inputName;?>" /><br />
			<?php if(!empty($inputDescription)) echo '<em>'.$inputDescription.'</em><br />'; ?><br /></td>
    </tr>
	<?php } 
	/*
	** Color Picker
	*/
	elseif($inputType == 'color') {
	if ($wp_version < '3.5.0')
	{
		echo '<tr>
        <td><p>'.__('<strong>Error:</strong> To use Color Picker, you need to have Wordpress at least 3.5. Please upgrade your instalation.','wp-category-meta').'</p></tdd></tr>';
    }
	else { 	?>
		<tr>
			<td><label for="<?php echo 'wptm_'.$inputName;?>"><strong><?php echo $inputLabel;?>:</strong></label><br />
			<input class="wptm_color" value="<?php echo $inputValue ?>" type="text" size="40"
				name="<?php echo 'wptm_'.$inputName;?>" /><br />
				<?php if(!empty($inputDescription)) echo '<em>'.$inputDescription.'</em><br />'; ?><br />
			</td>
		</tr>
	<?php } 
	} 
	/*
	** Date Picker
	*/
	elseif($inputType == 'date') {
	if ($wp_version < '3.5.0')
	{
		echo '<tr>
        <td><p>'.__('<strong>Error:</strong> To use Date Picker, you need to have Wordpress at least 3.5. Please upgrade your instalation.','wp-category-meta').'</p></tdd></tr>';
    }
	else { 	?>
		<tr>
			<td><label for="<?php echo 'wptm_'.$inputName;?>"><strong><?php echo $inputLabel;?>:</strong></label><br />
			<input class="wptm_date" value="<?php echo $inputValue ?>" type="text" size="40"
				name="<?php echo 'wptm_'.$inputName;?>" /><br />
				<?php if(!empty($inputDescription)) echo '<em>'.$inputDescription.'</em><br />'; ?><br />
			</td>
		</tr>
	<?php } 
	} 
	/**
	* Checkbox Group
	*/
	elseif($inputType == 'checkboxgroup') {
	$inputValues = $inputData['values'];
	$inputNames = $inputData['names'];
	$inputValue = get_terms_meta($category_id, $inputName, false);
	$inputValue = !empty($inputValue) ? $inputValue : array();
	$total = count($inputValues);
	 	?>
		<tr>
			<td><label for="<?php echo 'wptm_'.$inputName;?>"><strong><?php echo $inputLabel;?>:</strong></label><br />
			<?php for($i = 0 ; $i< $total; $i++)
			{
			 echo "<input type=\"checkbox\" ".(in_array($inputValues[$i], $inputValue) ? "checked=\"checked\"" : "")."  name=\"wptm_".$inputName."[]\" 
			 value=\"".htmlspecialchars(stripcslashes($inputValues[$i]))."\" /> ".$inputNames[$i]."<br />\n";
			} if(!empty($inputDescription)) echo '<em>'.$inputDescription.'</em><br />'; ?><br />
			</td>
		</tr>
	<?php 	} 
	/**
	* Select Box
	*/
	elseif($inputType == 'select') {
	$inputValues = $inputData['values'];
	$inputNames = $inputData['names'];
	$inputValue = get_terms_meta($category_id, $inputName, true);
	
	$total = count($inputValues);
	 	?>
		<tr>
			<td><label for="<?php echo 'wptm_'.$inputName;?>"><strong><?php echo $inputLabel;?>:</strong></label><br />
			<select name="wptm_<?php echo $inputName; ?>">
			<?php for($i = 0 ; $i< $total; $i++)
			{
			echo "<option value=\"".htmlspecialchars(stripcslashes($inputValues[$i]))."\" ".selected($inputValue,$inputValues[$i]).">".htmlspecialchars(stripcslashes($inputNames[$i]))."</option>\n"; 
			 } ?>

			</select>
			<?php if(!empty($inputDescription)) echo '<br /><em>'.$inputDescription.'</em><br />'; ?><br />
			</td>
		</tr>
	<?php 	} 
	
	/**
	* Radiobutton Group
	*/
	elseif($inputType == 'radio') {
	$inputValues = $inputData['values'];
	$inputNames = $inputData['names'];
	$inputValue = get_terms_meta($category_id, $inputName, false);
	
	$inputValue = !empty($inputValue) ? $inputValue : array();
	$total = count($inputValues);
	 	?>
		<tr>
			<td><label for="<?php echo 'wptm_'.$inputName;?>"><strong><?php echo $inputLabel;?>:</strong></label><br />
			<?php for($i = 0 ; $i< $total; $i++)
			{
			 echo "<input type=\"radio\" ".(in_array($inputValues[$i], $inputValue) ? "checked=\"checked\"" : "")."  name=\"wptm_".$inputName."\" 
			 value=\"".htmlspecialchars(stripcslashes($inputValues[$i]))."\" /> ".$inputNames[$i]."<br />\n";
			} if(!empty($inputDescription)) echo '<em>'.$inputDescription.'</em><br />'; ?><br />
			</td>
		</tr>
	<?php 	} // end elseif
	
	do_action('wptm_add_custom_meta_'.$inputType ,$inputLabel, $inputValue, $inputTaxonomy, $inputName, $inputDescription, $inputData);
    } // end wp check
}//end foreach
    } // end metalist empty check ?>

</table>
<textarea id="content" name="content" rows="100" cols="10" tabindex="2"
	onfocus="image_url_add()"
	style="width: 1px; height: 1px; padding: 0px; border: none display :   none;"></textarea>
<script type="text/javascript">edCanvas = document.getElementById('content');</script>
</div>
</div>
<?php
}
add_action('wck_fep_update_post','wptm_update_taxonomy_names');
add_action ('wpcf_taxonomy_renamed','wptm_update_taxonomy_names');

function wptm_update_taxonomy_names($old_taxonomy = null,$new_taxonomy = null)
{
 global $wpdb, $wptm_table_name;
 $config = get_option("wptm_configuration");
 $key_list = $wpdb->get_results("SELECT DISTINCT wptm.meta_key, tax.taxonomy FROM $wptm_table_name AS `wptm` LEFT JOIN $wpdb->term_taxonomy AS `tax` ON wptm.terms_id = tax.term_id");
 foreach($key_list as $type=>$taxonomy)
 {
  if ($config[$type]['taxonomy'] !=  $taxonomy) $config[$type]['taxonomy'] ==  $taxonomy;
 }
 
}

?>