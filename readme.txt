=== Category Meta plugin ===
Contributors: josecoelho, Randy Hoyt, steveclarkcouk, Vitaliy Kukin, Eric Le Bail, Tom Ransom
Donate link: #
Tags: category,meta,category meta,admin,plugin,image,icon,images,icons,category icon,category image,taxonomy metadata,taxonomy meta,custom taxonomy meta, color
Requires at least: 3.5
Tested up to: 4.2.2
Stable tag: 1.3.0

Add the ability to attach meta data to the wordpress categories and terms.
It can be used to add meta to custom taxonomies (terms).

== Description ==

This plugins add meta data to the wordpress categories and terms.
It Creates a wp-termsmeta table to store the entered meta.
It adds input fields to the category and other term administration interface to enter the meta values.
It provides functions to retrive / create / update / delete the category and terms meta.
It can be used to add meta to custom taxonomies (terms).

This plugin has been tested with WP2.8.6 and WPmu2.8.6 and WP4.4.2

== Update Log ==

= 1.3.0 =
* Major Release
* Fixed Image Upload feature
* Added Multisite support
* Added Edit feature and rewritten edit page
* Added Czech translation (thanks to Pavel Riha)
* Now are on deleting meta name in config also deleted meta values in termsmeta table (added a confirmation dialog for this)
* Upon plugin activation orphaned termsmeta are deleted and slug names for taxonomies are updated
* Added Checkboxes, Select boxes, Radiobuttons, File, ColorPicker and DatePicker Metabox (this feature requires Wordpres 3.5+, on lower version is disabled)
* Added action hooks and filters to add custom metafield types (filters 'wptm_meta_types', 'wptm_save_config', 'wptm_localized_date_extended', 'wptm_localized_date'; actions 'wptm_options_script', 
'wptm_add_option_row', 'wptm_add_custom_meta', 'wptm_meta_enqueue_scripts' and 'wptm_init')
* Added support for Types and WCK plugins to auto update config whenever a taxonomy slug is changed
* You may add your fields to multiple taxonomies at once


= 1.2.8=
* Add Brazilian Portuguese translation thanks to josecoelho

= 1.2.7 =
* Small fix to make the image fields work in WordPress 3.4.

= 1.2.6 =
* Fix bug introduced in 1.2.5, allowing images to be inserted into posts again.

= 1.2.5 =
* Small fix to make the image fields work in WordPress 3.3.1.

= 1.2.4 =
* Small fix to make the meta list appear in the taxonomy edit page with WP3.0.1. && WP3.0.4 (thanks to webgrafia).

= 1.2.3 =
* Add a new meta type "check-box" with meta_value of "checked" when the box is checked (thanks to Joseph).

= 1.2.2 =
* Small fix to make the meta list appear in the taxonomy edit page with WP3.0.1. && WP3.0.4 (thanks to Patrick).

= 1.2.1 =
* Small fix to avoid problem wih global variables in the taxonomy list with WP3.0.1. (thanks to Jeff).

= 1.2.0 =
* Major release. Add the terms meta administration form on all terms (category / Tags / custom taxonomies) for WP version 3.0 and above.

= 1.1.1 =
* Fix a typo error when no meta has been configured.

= 1.1.0 =
* Major release. Test and debug to be fully compatible with version 3.0.x . Add the new "image" type for meta.

= 1.0.3 =
* Fix an error on the meta update method thanks to Tom Ransom for sending the fix.

= 1.0.2 =
* Debug when no meta is set.

= 1.0.1 =
* Add the possibility to replace special chars into meta name in the meta creation form in the option page.

= 1.0.0 =
* Add the option page to easily configure the meta list

= 0.0.1 =
* plugin initial creation.

== Installation ==

1. Unzip into your `/wp-content/plugins/` directory. If you're uploading it make sure to upload
the top-level folder. Don't just upload all the php files and put them in `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to your Administration interface in the "Settings" menu a new "Category Meta" page is created.
Configure the meta you want to use.
4. go to your Administration interface, in the "Category" menu -> new fields are displayed in the category creation/modification form with the meta you configured.
5. That's it!



== Frequently Asked Questions ==

### What phase is it? ### 
This plugin is in stable phase.

### How can I use it? ###
You can use the folowing functions into your templates to retreive 1 meta: 
`
if (function_exists('get_terms_meta'))
{
    $metaValue = get_terms_meta($taxonomy_id, $meta_key, false);
}
`
You can use the folowing functions into your templates to retreive all meta:
`
if (function_exists('get_all_terms_meta'))
{
    $metaList = get_all_terms_meta($taxonomy_id);
}
`

== Screenshots ==
1. Editing screen
2. Category example


