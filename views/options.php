<?php
	
Class wptm_admin {

    function wptm_admin() {

        // Load language file
       /* $locale = get_locale();
        if ( !empty($locale) )
        //load_textdomain('wp-category-meta', WPTM_ABSPATH.'lang'.DIRECTORY_SEPARATOR.'wp-category-meta-'.$locale.'.mo');*/
		
        add_action('admin_enqueue_scripts', array(&$this, 'wptm_options_script'));
        add_action('admin_menu', array(&$this, 'wptm_add_options_panel'));

    }
    
    //admin script
    function wptm_options_script() {
        if(@$_GET['page'] == 'category_meta') {
			global $wptm_version;
		  // what about global $wptm_version instead?
		  wp_enqueue_style('wptm_style_options',  WPTM_PATH .'css/wp-category-options.css',false,$wptm_version);
		  wp_enqueue_script('wptm_admin', WPTM_PATH . 'js/wp-category-meta-options.js', array('jquery'), $wptm_version );
		  wp_localize_script('wptm_admin', 'wptm', array(
		  'name_is_empty' => __('Meta Name is empty!', 'wp-category-meta'), 
		  'taxonomy_changed_warning' => __('Do you really want to change the taxonomy? All data with this meta name assigned to the old taxonomy will be deleted!', 'wp-category-meta') ,
		  'no_taxonomies' => __('Error: You have selected no taxonomies. Choose at least one.', 'wp-category-meta')
		  ) );
		  do_action('wptm_options_script');
     }
    }
    
    //Add configuration page into admin interface.
    function wptm_add_options_panel() {
        add_options_page(__('Category Meta Options', 'wp-category-meta'), __('Category Meta', 'wp-category-meta'), 'manage_options', 'category_meta', array(&$this, 'wptm_option_page'));
    }
    
    //build admin interface
    function wptm_option_page() 
    {   
		global $wp_version, $wptm_version;
        $configuration = get_option("wptm_configuration",array());
		$form_action = "add";
		$submit_label = __('Add Meta', 'wp-category-meta');
		$meta_taxonomy = array();
		$readonly_taxonomy = "";
		$meta_type = '';
		$wptm_types = array("text" => __("Text","wp-category-meta"),
							"textarea" =>  __("Text Area","wp-category-meta"),
							"image" => __("Image","wp-category-meta"), 
							"file" => __("File","wp-category-meta"), 
							"checkbox" => __("Check Box","wp-category-meta"), 
							"checkboxgroup" => __("Check Box Group","wp-category-meta"), 
							"select" => __("Select","wp-category-meta"), 
							"radio" => __("Radio buttons","wp-category-meta"), 
							"color" =>  __("Color","wp-category-meta"),
							"date" =>  __("Date Picker","wp-category-meta") );
		$wptm_types = apply_filters("wptm_meta_types", $wptm_types);
		
		 if($wp_version >= '3.0') $taxonomies_obj=get_taxonomies('','objects'); 
        if(is_null($configuration) || $configuration == '')
        {
            $configuration = array();
        }
		$form_url = admin_url("options-general.php?page=category_meta");
		
        //add data
        if(isset($_POST['action']) && $_POST['action'] == "add") 
        {
            $new_meta_name = sanitize_text_field($_POST["new_meta_name"]);
            $new_meta_label = sanitize_text_field($_POST["new_meta_label"]);
            $new_meta_desc = wp_kses_post($_POST["new_meta_desc"]);
            $new_meta_label = !empty($new_meta_label) ? $new_meta_label : $new_meta_name;
			//Always sanitize
            $new_meta_name = sanitize_title($new_meta_name);
            $new_meta_type = $_POST["new_meta_type"];
            $new_meta_taxonomy = isset($_POST["new_meta_taxonomy"]) ? array_values($_POST["new_meta_taxonomy"]) : array();
			//$new_meta_taxonomy = $_POST["new_meta_taxonomy"];
			$new_meta_values = isset($_POST["new_meta_values"]) ? $_POST["new_meta_values"] : array();
			if(count($new_meta_values) > 0)
			{	foreach($new_meta_values as $field_value)
				{
					$field_value = sanitize_text_field($field_value);
				}
			}
            $new_meta_names = isset($_POST["new_meta_names"]) ? $_POST["new_meta_names"] : array();
            if(count($new_meta_names) > 0)
			{	foreach($new_meta_names as $field_name)
				{
					$field_name = sanitize_text_field($field_name);
				}
			}
			$configuration = get_option("wptm_configuration");
			
			if (!isset($configuration[$new_meta_name]))
			{
			
				//  just doublecheck, it is controlled by jScript
				if(!empty($new_meta_name))
				{
					$configuration[$new_meta_name] = array('type' => $new_meta_type, 'label' => $new_meta_label, 'description' => $new_meta_desc, 'taxonomy' => $new_meta_taxonomy, 'values' => $new_meta_values, 'names' => $new_meta_names);
					$configuration = apply_filters('wptm_save_config',$configuration,$_POST);
					update_option("wptm_configuration", $configuration);
				}
				else 
				{
					 echo '<div id="message" class="error"><p>'.__('Error: Field <strong>Meta Name</strong> cannot be empty!', 'wp-category-meta').'</strong></p></div>';
				}
			}
			else {
			 echo '<div id="message" class="error"><p>'.sprintf(__('Error: Meta Name <strong>%s</strong> already exists!', 'wp-category-meta'),$new_meta_name).'</p></div>';
			}
            
        }
		// delete data
        else if(isset($_POST['action']) && $_POST['action'] == "delete") 
        {
            $delete_Meta_Name = $_POST["delete_Meta_Name"];
            unset($configuration[$delete_Meta_Name]);
			wptm_delete_meta_name($delete_Meta_Name);
            update_option("wptm_configuration", $configuration);
			echo '<div id="message" class="updated"><p>'.sprintf(__('Meta Name <strong>%s</strong> succesfully deleted.', 'wp-category-meta'), $delete_Meta_Name).'</p></div>';
					$can_update = false;
        }
		// load data for edit
		else if(isset($_POST['action']) && $_POST['action'] == "edit") 
        {
			$edit_Meta_Name = $_POST["edit_Meta_Name"];
			
            $configuration = get_option("wptm_configuration");
			$meta_name = htmlspecialchars(stripcslashes(sanitize_title($edit_Meta_Name)));
			$meta_label = isset($configuration[$edit_Meta_Name]['label']) ? htmlspecialchars(stripcslashes($configuration[$edit_Meta_Name]['label'])) : $edit_Meta_Name;
			$meta_desc = isset($configuration[$edit_Meta_Name]['description']) ? htmlspecialchars(stripcslashes($configuration[$edit_Meta_Name]['description'])) : '';
			$meta_taxonomy = is_array($configuration[$edit_Meta_Name]['taxonomy']) ? $configuration[$edit_Meta_Name]['taxonomy'] : array($configuration[$edit_Meta_Name]['taxonomy']);
			$meta_type = $configuration[$edit_Meta_Name]['type'];
			$meta_values = isset($configuration[$edit_Meta_Name]['values']) ? $configuration[$edit_Meta_Name]['values'] : array();
			$meta_names = isset($configuration[$edit_Meta_Name]['names']) ? $configuration[$edit_Meta_Name]['names'] :array();
			$form_action = "update";
			$submit_label = __("Update", 'wp-category-meta');
			$readonly_taxonomy = "readonly=\"readonly\"";
			
        }
		// save data after edit
		else if(isset($_POST['action']) && $_POST['action'] == "update") 
        {
			$can_update = true;
			$edit_meta_name = sanitize_text_field($_POST["new_meta_name"]);
			$old_edit_meta_name = $_POST["old_meta_name"];
            $edit_meta_label = sanitize_text_field($_POST["new_meta_label"]);
            $edit_meta_desc = wp_kses_post($_POST["new_meta_desc"]);
			$message = '';
            //Always sanitize
            $edit_meta_name = !empty($edit_meta_name) ? sanitize_title($edit_meta_name) : sanitize_title($edit_meta_label);
            $edit_meta_type = $_POST["new_meta_type"];
			$edit_meta_values = isset($_POST["new_meta_values"]) ? $_POST["new_meta_values"] : array();
			if(count($edit_meta_values) > 0)
			{	foreach($edit_meta_values as $field_value)
				{
					$field_value = sanitize_text_field($field_value);
				}
			}
            $edit_meta_names = isset($_POST["new_meta_names"]) ? $_POST["new_meta_names"] : array();
			if(count($edit_meta_names) > 0)
			{	foreach($edit_meta_values as $field_name)
				{
					$field_name = sanitize_text_field($field_name);
				}
			}
            //$edit_meta_taxonomy = $_POST["new_meta_taxonomy"];
			$edit_meta_taxonomy = isset($_POST["new_meta_taxonomy"]) ? array_values($_POST["new_meta_taxonomy"]) : array();
			$old_edit_meta_taxonomy = explode(";",$_POST["old_meta_taxonomy"]);
			
			// what if Meta Name was changed?
			if($edit_meta_name !== $old_edit_meta_name)
			{
				//To prevent overwrite some other meta
				if(!isset($configuration[$edit_meta_name]))
				{
					// rename key by copying all data from old name to new name and unset old name
					$configuration[$edit_meta_name] = $configuration[$old_edit_meta_name];
					unset($configuration[$old_edit_meta_name]);
					// we also change all meta keys
					wptm_update_meta_name($old_edit_meta_name, $edit_meta_name);
					$message = '<br />'.sprintf(__('Meta Name succefully changed from <strong>%s</strong> to <strong>%s</strong>. Don\'t forget to update your PHP code!','wp-category-meta'),$old_edit_meta_name, $edit_meta_name);
				}
				else 
				{
					echo '<div id="message" class="error"><p>'.sprintf(__('Error: Meta Name <strong>%s</strong> already exists!', 'wp-category-meta'), $edit_meta_name).'</p></div>';
					$can_update = false;
				}
			}
			// delete all metadata if metafield is reassigned to different taxonomy
			if($edit_meta_taxonomy !== $old_edit_meta_taxonomy )
			{
				//$old_tax_id = get_term_by('slug',$old_edit_meta_taxonomy,
				foreach($old_edit_meta_taxonomy as $old_taxonomy)
				{
					if(!in_array($old_taxonomy,$edit_meta_taxonomy) )wptm_delete_meta_name($old_edit_meta_name);
				}
			}
			if($can_update)
			{
				echo '<div id="message" class="updated"><p>'.sprintf(__('Meta Name <strong>%s</strong> succesfully updated.','wp-category-meta'),$old_edit_meta_name, $edit_meta_name).$message.'</p></div>';
				$configuration[$edit_meta_name] = array('type' => $edit_meta_type, 'label' => $edit_meta_label,'description' => $edit_meta_desc, 'taxonomy' => $edit_meta_taxonomy, 'values' => $edit_meta_values, 'names' => $edit_meta_names );
				$configuration = apply_filters('wptm_save_config',$configuration,$_POST);
				update_option("wptm_configuration", $configuration);
			}
			
			$action = "add";
			
		}
    ?>
        <div class="wrap">
            <h2><?php _e('Category Meta Version:', 'wp-category-meta'); ?> <?php echo $wptm_version; ?></h2>
			<!-- Listing saved meta definitions -->
            <table class="widefat fixed">
                <thead>
                    <tr class="title">
                        <th scope="col"  colspan="4" class="manage-column"><?php _e('Meta list', 'wp-category-meta'); ?></th>
                        <?php if($wp_version >= '3.0') {?>
                        <th scope="col" class="manage-column"></th>
                        <?php } ?>
                        <th scope="col" class="manage-column"></th>
                    </tr>
                    <tr class="title">
						<th scope="col" class="manage-column"><?php _e('Meta Name', 'wp-category-meta'); ?></th>
						<th scope="col" class="manage-column"><?php _e('Input Label', 'wp-category-meta'); ?></th>
                        <th scope="col" class="manage-column"><?php _e('Input Description', 'wp-category-meta'); ?></th>
                        <th scope="col" class="manage-column"><?php _e('Meta Type', 'wp-category-meta'); ?></th>
                        <?php if($wp_version >= '3.0') {?>
                        <th scope="col" class="manage-column"><?php _e('Meta Taxonomy', 'wp-category-meta'); ?></th>
                        <?php } ?>
                        <th scope="col" class="manage-column"><?php _e('Action', 'wp-category-meta'); ?></th>
                    </tr>
                </thead>
                <?php 
                    foreach($configuration as $name => $data)
                    { 
                        $type = '';
                        $taxonomy = 'category';
                        if(is_array($data)) {
							$type = isset($wptm_types[$data['type']]) ? $wptm_types[$data['type']] : "N/A"; 
							$label = isset($data['label']) ? $data['label'] : $name;
                            $description = isset($data['description']) ? $data['description'] : '';
                            $taxonomy = !empty($data['taxonomy']) ? $data['taxonomy'] : 'category';
							
							//$taxonomy_name =  isset($taxonomies_obj[$taxonomy]) ? $taxonomies_obj[$taxonomy]->labels->singular_name : $taxonomy;
							if ( is_array($data['taxonomy']))
							{
								$taxonomy_name = array();
								foreach( $data['taxonomy'] as $taxonomy)
								{
									$taxonomy_name[] = isset($taxonomies_obj[$taxonomy]->labels->singular_name) ? $taxonomies_obj[$taxonomy]->labels->singular_name." (".$taxonomy.")" : $taxonomy;
								}
								unset($taxonomy);
								$taxonomy_name = implode(", ",$taxonomy_name);
							}
							else $taxonomy_name =  isset($taxonomies_obj[$taxonomy]->labels->singular_name) ? $taxonomies_obj[$taxonomy]->labels->singular_name." (".$taxonomy.")" : $taxonomy;
							
                        } 
						else 
						{
                            $type = $data;
							$label = $data;
							$descrition = '';
                        }
                        ?>
                <tr class="mainrow"> 
					 <td class="titledesc">
						<?php echo $name;?>
					</td>
                    <td class="forminp">
                       <?php echo htmlspecialchars(stripcslashes($label)); ?>
                    </td>       
                    <td class="forminp">
                       <em><?php echo htmlspecialchars(stripcslashes($description)); ?></em>
                    </td>       
                    <td class="forminp">
                       <?php echo $type;?>
                    </td>
                     <?php if($wp_version >= '3.0') {?>
                    <td class="forminp">
                        <?php // echo $taxonomy_name.' ('.$taxonomy.')';?>
						<?php echo $taxonomy_name;?>
                    </td>

                    <?php } ?>
                    <td class="forminp">
                        <form method="post" action="<?php echo $form_url; ?>" style="width: 50%; float: left;">
                        <input type="hidden" name="action" value="edit" />
                        <input type="hidden" name="edit_Meta_Name" value="<?php echo $name;?>" />
                        <input type="submit" value="<?php _e('Edit', 'wp-category-meta') ?>" />
                        </form>
						<form method="post" action="<?php echo $form_url; ?>" style="width: 50%; float: left;">
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="delete_Meta_Name" value="<?php echo $name;?>" />
                        <input type="submit" onclick="return confirm('<?php echo addslashes(sprintf(__('Do you really want to delete \'%s\'? All terms meta with this name will be also deleted!', 'wp-category-meta'), $name)); ?>');" value="<?php _e('Delete', 'wp-category-meta') ?>" />
                        </form>
                    </td>
                </tr>
                    <?php }
					unset($data); unset($name);
                ?>
            </table>
            <br/>
			<!-- Add & Edit Form Begin -->
            <form method="post" action="<?php echo $form_url; ?>" id="wptm_meta_form">
                <table class="widefat editform">
                    <thead>
                        <tr class="title">
                            <th scope="col" class="manage-column"><?php _e('Add a Meta', 'wp-category-meta'); ?></th>
                            <th scope="col" class="manage-column"></th>
                        </tr>
                    </thead>
					<tr class="mainrow">        
                        <td class="titledesc"><?php _e('Meta Name','wp-category-meta'); ?>*:</td>
                        <td class="forminp">
                            <input type="text" id="new_meta_name" name="new_meta_name" value="<?php if(isset($meta_name)) echo $meta_name; ?>" /> <em><?php _e('(e.g. \'image\', use a-z, A-Z and _or -, no spaces)', 'wp-category-meta'); ?></em>
                        </td>
                    </tr>
					<tr class="mainrow">        
                        <td class="titledesc"><?php _e('Input Label','wp-category-meta'); ?></td>
                        <td class="forminp">
                            <input type="text" id="new_meta_label" name="new_meta_label" value="<?php if(isset($meta_label)) echo $meta_label; ?>" /> <em><?php _e('(e.g. \'My Image\')', 'wp-category-meta'); ?></em>
                        </td>
                    </tr>
                    <tr class="mainrow">        
                        <td class="titledesc"><?php _e('Input Description','wp-category-meta'); ?>:</td>
                        <td class="forminp">
                            <textarea id="new_meta_desc" name="new_meta_desc" rows="3" cols="60"><?php if(isset($meta_desc)) echo $meta_desc; ?></textarea>
                        </td>
                    </tr>
					
					<!-- Here should go the values by meta type for select boxes, check boxes and radiobuttons -->
					<tr class="mainrow">        
                        <td colspan="2" class="titledesc"><?php _e('Values and names (checkbox groups, select and radiobuttons only)','wp-category-meta'); ?>:</td>
                    </tr>
					
			<?php		if(isset($meta_values) && count($meta_values) > 0) 
			{	$cycle = count($meta_values);
				for($i = 0; $i < $cycle; $i++ )
				{ ?>
					<tr class="mainrow values">        
                        <td class="titledesc"><label for="new_meta_values"><?php _e('Value:','wp-category-meta'); ?></label><input type="text" name="new_meta_values[]" 
						value="<?php echo htmlspecialchars(stripcslashes($meta_values[$i])); ?>"  class="new_meta_values" /> <br />
						<input type="button" class="wptm_add_row" value="<?php _e('Add value','wp-category-meta'); ?>" /></td>
                        <td class="forminp">
                            <label for="new_meta_names"><?php _e('Value:','wp-category-meta'); ?></label><input type="text" name="new_meta_names[]" 
							value="<?php echo htmlspecialchars(stripcslashes($meta_names[$i])); ?>" class="new_meta_names" /> <br />
						<input type="button" class="wptm_delete_row" value="<?php _e('Delete value','wp-category-meta'); ?>" />
                        </td>
                    </tr>
			<?php	}
			}
			else {?>		<tr class="mainrow values">        
                        <td class="titledesc"><label for="new_meta_values"><?php _e('Value:','wp-category-meta'); ?></label>
						<input type="text" name="new_meta_values[]" value="" class="new_meta_values" /> <br />
						<input type="button" class="wptm_add_row" value="<?php _e('Add value','wp-category-meta'); ?>" /></td>
                        <td class="forminp">
                            <label for="new_meta_names"><?php _e('Name for value:','wp-category-meta'); ?></label><input type="text" name="new_meta_names[]" value="" class="new_meta_names" /> <br />
						<input type="button" class="wptm_delete_row" value="<?php _e('Delete value','wp-category-meta'); ?>" />
                        </td>
                    </tr>
					<?php }?>
					<?php do_action('wptm_add_option_row',$type, $meta_taxonomy, isset($data) ? $data : null); ?>
                    <tr class="mainrow">        
                        <td class="titledesc"><?php _e('Meta Type','wp-category-meta'); ?>:</td>
                        <td class="forminp">
                            <select id="new_meta_type" name="new_meta_type">
							<?php foreach($wptm_types as $type=>$name)
							{
								echo "<option ".selected($meta_type,$type)." value=\"".$type."\">".$name."</option>\n";
							}
							unset($type); unset($name);
							?>
                            </select>
                        </td>
                    </tr>
					<?php
					/**
					* You may add here any addtional controls for your type
					*/
					?>
					
                    <?php if($wp_version >= '3.0') {?>
                    <tr class="mainrow">        
                        <td class="titledesc"><?php _e('Meta Taxonomy','wp-category-meta'); ?>:</td>
                        <td class="forminp">
                         <?php /* <select id="new_meta_taxonomy" <?php echo $readonly_taxonomy; ?> name="new_meta_taxonomy">
                                  <?php $my_taxonomies = array();
								  foreach ($taxonomies_obj as $taxonomy ) 
								  {
                                      echo '<option '.selected($meta_taxonomy,$taxonomy->name,false).' value="'.$taxonomy->name.'">'. $taxonomy->labels->singular_name. ' ('.$taxonomy->name.')</option>';
									  $my_taxonomies[] = $taxonomy->name;
                                   }
								   // we add it to the select box if taxonomy is not currently active
								   if(!empty($meta_taxonomy) && !in_array($meta_taxonomy,$my_taxonomies)) echo '<option '.selected($meta_taxonomy,$meta_taxonomy,false).' value="'.$meta_taxonomy.'">'. $meta_taxonomy.'</option>';
									  $my_taxonomies[] = $taxonomy->name;
                                ?>
                            </select>*/ ?>
									
                                  <?php 
								  $my_taxonomies = $meta_taxonomy;
								  //echo "<pre>\$my_taxonomies: \n".print_r($my_taxonomies,true)."</pre>";
								  //echo "<pre>\$meta_taxonomy: \n".print_r($meta_taxonomy,true)."</pre>";
								  //$my_taxonomies = array();
								  foreach ($taxonomies_obj as $taxonomy ) 
								  {
                                      echo '<input type="checkbox" name="new_meta_taxonomy[]" '.(in_array($taxonomy->name,$meta_taxonomy) ? "checked=\"checked\"" : "").' value="'.$taxonomy->name.'"> <label for="new_meta_taxonomy[]">'. $taxonomy->labels->singular_name. ' ('.$taxonomy->name.')</label>'."<br />\n";
									  //remove existing key, it is always first;
									  if(in_array($taxonomy->name,$meta_taxonomy)) array_shift($my_taxonomies);
                                   }
								   unset($taxonomy);
								   // we add it to the select box if taxonomy is not currently active
								   if(count($my_taxonomies) > 0) 
								   {
									   foreach ($my_taxonomies as $mytax)
									   {
											echo '<input type="checkbox" name="new_meta_taxonomy[]" checked=\"checked\" value="'.$mytax.'"> 
											<label for="new_meta_taxonomy[]">'. $mytax.'</label>'."<br />\n";
									   }
								   }
								   unset($my_taxonomies); unset($mytax);
									 // $my_taxonomies[] = $taxonomy->name;*/
                                ?>
							
                        </td>
                    </tr>
                    <?php } ?>
                    <tr class="mainrow">
                        <td class="titledesc">
                        <input type="hidden" name="action" id="wptm_action" value="<?php echo $form_action; ?>" />
                        <input type="hidden" name="old_meta_name" id="old_meta_name" value="<?php if(isset($meta_name)) echo $meta_name; ?>" />
                        <input type="hidden" name="old_meta_taxonomy" id="old_meta_taxonomy" value="<?php if(isset($meta_taxonomy)) echo implode(";",$meta_taxonomy); ?>" />
                        </td>
                        <td class="forminp">
                        <input type="submit" value="<?php echo $submit_label; ?>" />
						</td>
                    </tr>
                </table>
            </form>
			<!-- Add & Edit Form End -->
        </div>
    <?php 
    }
}
?>