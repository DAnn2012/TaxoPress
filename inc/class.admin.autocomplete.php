<?php
class SimpleTags_Admin_Autocomplete extends SimpleTags_Admin {
	
	function SimpleTags_Admin_Autocomplete() {
		global $pagenow;
		
		// Get options
		$options = get_option( STAGS_OPTIONS_NAME );
		
		// Ajax action, JS Helper and admin action
		add_action('wp_ajax_'.'simpletags', array(&$this, 'ajaxCheck'));
		
		// Save tags from advanced input
		add_action( 'save_post', 	array(&$this, 'saveAdvancedTagsInput'), 10, 2 );
		
		// Box for advanced tags
		add_action( 'add_meta_boxes', array(&$this, 'registerMetaBox'), 999 );
		
		// Simple Tags hook
		add_action( 'simpletags-auto_terms', array(&$this, 'autoTermsJavaScript') );
		add_action( 'simpletags-manage_terms', array(&$this, 'manageTermsJavaScript') );
		add_action( 'simpletags-mass_terms', array(&$this, 'massTermsJavascript') );
		
		// Register JS/CSS
		if ( isset($options['autocomplete_mode']) && $options['autocomplete_mode'] == 'jquery-autocomplete' ) {
			wp_register_script('jquery-bgiframe',			STAGS_URL.'/ressources/jquery.bgiframe.min.js', array('jquery'), '2.1.1');
			wp_register_script('jquery-autocomplete',		STAGS_URL.'/ressources/jquery.autocomplete/jquery.autocomplete.min.js', array('jquery', 'jquery-bgiframe'), '1.1');
			wp_register_script('st-helper-autocomplete', 	STAGS_URL.'/inc/js/helper-autocomplete.min.js', array('jquery', 'jquery-autocomplete'), STAGS_VERSION);	
			wp_register_style ('jquery-autocomplete', 		STAGS_URL.'/ressources/jquery.autocomplete/jquery.autocomplete.css', array(), '1.1', 'all' );
		} else {
			wp_register_script('prototype-textboxlist', STAGS_URL.'/ressources/textboxlist/lib/prototype.js', array(), '1.6.0.2');
			wp_register_script('textboxlist', 			STAGS_URL.'/ressources/textboxlist/src/TextBoxList.js', array('prototype-textboxlist'), '0.6');
			wp_register_script('st-helper-textboxlist', STAGS_URL.'/inc/js/helper-textboxlist.min.js', array('textboxlist'), STAGS_VERSION);
			wp_register_style ('textboxlist', 			STAGS_URL.'/ressources/textboxlist/demo/default.css', array(), '0.6', 'all' );
		}
		
		
		// Register location
		$wp_post_pages = array('post.php', 'post-new.php');
		$wp_page_pages = array('page.php', 'page-new.php');
		
		// Helper for posts/pages and for Auto Tags, Mass Edit Tags and Manage tags !
		if ( (in_array($pagenow, $wp_post_pages) || ( in_array($pagenow, $wp_page_pages) && is_page_have_tags() )) || (isset($_GET['page']) && in_array( $_GET['page'], array('st_auto', 'st_mass_terms', 'st_manage') )) ) {
			if ( isset($options['autocomplete_mode']) && $options['autocomplete_mode'] == 'jquery-autocomplete' ) {
				wp_enqueue_script('st-helper-autocomplete');
				wp_enqueue_style ('jquery-autocomplete');
			} else {
				wp_enqueue_script('st-helper-textboxlist');
				wp_enqueue_style ('textboxlist');
			}
		}
	}
	
	/**
	 * Ajax Dispatcher
	 *
	 * @return void
	 * @author Amaury Balmer
	 */
	function ajaxCheck() {
		if ( isset($_GET['st_action']) && $_GET['st_action'] == 'collection_jquery_autocomplete' )  {
			$this->ajaxjQueryAutoComplete();
		} elseif( isset($_GET['st_action']) && $_GET['st_action'] == 'collection_textboxlist' ) {
			$this->ajaxTextboxList();
		} elseif( isset($_GET['st_action']) && $_GET['st_action'] == 'collection_textboxlist_post' ) {
			$this->ajaxTextboxListPost();
		} elseif( isset($_GET['st_action']) && $_GET['st_action'] == 'collection_textboxlist_auto' ) {
			$this->ajaxTextboxListAuto();
		}
	}
	
	/**
	 * Display a javascript collection for textbox list script ! output json
	 *
	 * @return void
	 * @author Amaury Balmer
	 */
	function ajaxTextboxList() {
		status_header( 200 ); // Send good header HTTP
		header("Content-Type: text/javascript; charset=" . get_bloginfo('charset'));
		
		$taxonomy = 'post_tag';
		if ( isset($_REQUEST['taxonomy']) && taxonomy_exists($_REQUEST['taxonomy']) ) {
			$taxonomy = $_REQUEST['taxonomy'];
		}
		
		if ( (int) wp_count_terms($taxonomy, 'ignore_empty=false') == 0 ) { // No tags to suggest
			exit();
		}
		
		// Prepare search
		$search = ( isset($_REQUEST['keyword']) ) ? trim(stripslashes($_REQUEST['keyword'])) : '';
		
		// Get all terms, or filter with search
		$terms = $this->getTermsForAjax( $taxonomy, $search );
		if ( empty($terms) || $terms == false ) {
			exit();
		}
		
		// Format terms
		$output = array();
		foreach ( (array) $terms as $term ) {
			$term->name = stripslashes($term->name);
			$term->name = str_replace( array("\r\n", "\r", "\n"), '', $term->name );
			
			$output[] = array( 'caption' => $term->name, 'value' => $term->term_id );
		}
		
		echo json_encode($output);
		exit();
	}
	
	/**
	 * Get list of tags for a specific post, output json
	 *
	 * @return void
	 * @author Amaury Balmer
	 */
	function ajaxTextboxListPost() {
		status_header( 200 ); // Send good header HTTP
		header("Content-Type: text/javascript; charset=" . get_bloginfo('charset'));
		
		$taxonomy = 'post_tag';
		if ( isset($_REQUEST['taxonomy']) && taxonomy_exists($_REQUEST['taxonomy']) ) {
			$taxonomy = $_REQUEST['taxonomy'];
		}
		
		if ( (int) $_REQUEST['post_id'] == 0 ) { // No tags to suggest
			exit();
		}

		// Get all terms, or filter with search
		$terms = wp_get_post_terms( (int) $_REQUEST['post_id'], $taxonomy );
		if ( empty($terms) || $terms == false ) {
			exit();
		}
		
		// Format terms
		$output = array();
		foreach ( (array) $terms as $term ) {
			$term->name = stripslashes($term->name);
			$term->name = str_replace( array("\r\n", "\r", "\n"), '', $term->name );
			$output[] = array( 'caption' => $term->name, 'value' => $term->term_id );
		}
		
		echo json_encode($output);
		exit();
	}
	
	/**
	 * Get list of tags for autotags
	 *
	 * @return void
	 * @author Amaury Balmer
	 */
	function ajaxTextboxListAuto() {
		status_header( 200 ); // Send good header HTTP
		header("Content-Type: text/javascript; charset=" . get_bloginfo('charset'));
		
		$current_terms = array();
		if ( isset($_REQUEST['current_tags']) && !empty($_REQUEST['current_tags']) ) {
			$current_terms = explode(',', $_REQUEST['current_tags']);
			$current_terms = array_filter($current_terms, '_delete_empty_element');
		}
		
		// Format terms
		$output = array();
		foreach ( (array) $current_terms as $name ) {
			$name = stripslashes($name);
			$name = str_replace( array("\r\n", "\r", "\n"), '', $name );
			$output[] = array( 'caption' => $name, 'value' => 0 );
		}
		
		echo json_encode($output);
		exit();
	}
	
	/**
	 * Display a javascript collection for jquery autocomple script !
	 *
	 * @return void
	 * @author Amaury Balmer
	 */
	function ajaxjQueryAutoComplete() {
		status_header( 200 ); // Send good header HTTP
		header("Content-Type: text/javascript; charset=" . get_bloginfo('charset'));
		
		$taxonomy = 'post_tag';
		if ( isset($_REQUEST['taxonomy']) && taxonomy_exists($_REQUEST['taxonomy']) ) {
			$taxonomy = $_REQUEST['taxonomy'];
		}
		
		if ( (int) wp_count_terms($taxonomy, 'ignore_empty=false') == 0 ) { // No tags to suggest
			exit();
		}
		
		// Prepare search
		$search = ( isset($_GET['q']) ) ? trim(stripslashes($_GET['q'])) : '';
		
		// Get all terms, or filter with search
		$terms = $this->getTermsForAjax( $taxonomy, $search );
		if ( empty($terms) || $terms == false ) {
			exit();
		}
		
		// Format terms
		foreach ( (array) $terms as $term ) {
			$term->name = stripslashes($term->name);
			$term->name = str_replace( array("\r\n", "\r", "\n"), '', $term->name );
			
			echo "$term->term_id|$term->name\n";
		}
		
		exit();
	}
	
	/**
	 * Save tags input for old field
	 *
	 * @param string $post_id 
	 * @param object $object 
	 * @return boolean
	 * @author Amaury Balmer
	 */
	function saveAdvancedTagsInput( $post_id = 0, $object = null ) {
		if ( isset($_POST['textboxlist-tags']) ) {
			$terms = json_decode(stripslashes($_POST['textboxlist-tags']));
			
			// Build an array with ID.
			$terms_ids = array();
			foreach( $terms as $term ) {
				$terms_ids[] = $term->value;
			}
			
			// Clean, unicity
			if ( !empty($terms_ids) ) {
				$terms_ids = array_map('intval', $terms_ids);
				$terms_ids = array_unique($terms_ids);
			}
			
			// Add new tag (no append ! replace !)
			wp_set_object_terms($post_id, $terms_ids, 'post_tag');
			
			// Clean cache
			if ( 'page' == $object->post_type ) {
				clean_page_cache($post_id);
			} else {
				clean_post_cache($post_id);
			}
			
			return true;
		} elseif ( isset($_POST['adv-tags-input']) ) {
			// Trim/format data
			$tags = preg_replace( "/[\n\r]/", ', ', stripslashes($_POST['adv-tags-input']) );
			$tags = trim($tags);
			
			// String to array
			$tags = explode( ',', $tags );
			
			// Remove empty and trim tag
			$tags = array_filter($tags, '_delete_empty_element');
			
			// Add new tag (no append ! replace !)
			wp_set_object_terms( $post_id, $tags, 'post_tag' );
			
			// Clean cache
			if ( 'page' == $object->post_type ) {
				clean_page_cache($post_id);
			} else {
				clean_post_cache($post_id);
			}
			
			return true;
		}
		
		return false;
	}
	
	/**
	 * Call meta box function for taxonomy tags for each CPT
	 *
	 * @param string $post_type 
	 * @return boolean
	 * @author Amaury Balmer
	 */
	function registerMetaBox( $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type );
		if ( in_array('post_tag', $taxonomies) ) {
			if ( $post_type == 'page' && !is_page_have_tags() )
				return false;
			
			remove_meta_box( 'post_tag'.'div', $post_type, 'side' );
			remove_meta_box( 'tagsdiv-'.'post_tag', $post_type, 'side' );
			
			add_meta_box('adv-tagsdiv', __('Tags (Simple Tags)', 'simpletags'), array(&$this, 'boxTags'), $post_type, 'side', 'core', array('taxonomy'=>'post_tag') );
			return true;
		}
		
		return false;
	}
	
	/**
	 * Content of custom meta box of Simple Tags
	 *
	 * @param object $post
	 * @return void
	 * @author Amaury Balmer
	 */
	function boxTags( $post ) {
		// Get options
		$options = get_option( STAGS_OPTIONS_NAME );
		?>
		<p id="adv-tags-input-wrap">
			<input type="text" class="widefat" name="adv-tags-input" id="adv-tags-input" value="<?php echo esc_attr($this->getTermsToEdit( 'post_tag', $post->ID )); ?>" />
			<?php _e('Separate tags with commas', 'simpletags'); ?>
		</p>
		<?php
		if ( isset($options['autocomplete_mode']) && $options['autocomplete_mode'] == 'jquery-autocomplete' ) :
			?>
			<script type="text/javascript">
				<!--
				initjQueryAutoComplete( '#adv-tags-input', '<?php echo admin_url("admin-ajax.php?action=simpletags&st_action=collection_jquery_autocomplete"); ?>', 300 );
				-->
			</script>
			<?php
		else :
			?>
			<script type="text/javascript">
				<!--
				initTextboxList_Post(
					'adv-tags-input-wrap',
					'textboxlist-tags', 
					'<?php echo admin_url("admin-ajax.php"); ?>', 
					'<?php echo esc_js(__("Type the beginning of the name of term which you wish to add.", "simpletags")); ?>',
					'<?php echo esc_js(__("No values found", "simpletags")); ?>',
					<?php echo $post->ID; ?>
				 );
				-->
			</script>
		<?php
		endif;
	}
	
	/**
	 * Function called on auto terms page
	 *
	 * @param string $taxonomy 
	 * @return void
	 * @author Amaury Balmer
	 */
	function autoTermsJavaScript( $taxonomy = '' ) {
		// Get options
		$options = get_option( STAGS_OPTIONS_NAME );
		
		if ( isset($options['autocomplete_mode']) && $options['autocomplete_mode'] == 'jquery-autocomplete' ) :
			?>
			<script type="text/javascript">
				<!--
				initjQueryAutoComplete( '#auto_list', "<?php echo admin_url('admin-ajax.php?action=simpletags&st_action=collection_jquery_autocomplete&taxonomy='.$taxonomy); ?>", 300 );
				-->
			</script>
			<?php
		else :
			?>
			<script type="text/javascript">
				<!--
				initTextboxList_AutoList(
					'auto_list-wrap',
					'textboxlist-tags', 
					'<?php echo admin_url("admin-ajax.php"); ?>', 
					'<?php echo esc_js(__("Type the beginning of the name of term which you wish to add.", "simpletags")); ?>',
					'<?php echo esc_js(__("No values found", "simpletags")); ?>'
				 );
				-->
			</script>
		<?php
		endif;
	}
	
	/**
	 * Function called on manage terms page
	 *
	 * @param string $taxonomy 
	 * @return void
	 * @author Amaury Balmer
	 */
	function manageTermsJavaScript( $taxonomy = '' ) {
		// Get options
		$options = get_option( STAGS_OPTIONS_NAME );
		?>
		<script type="text/javascript">
			<!--
			initjQueryAutoComplete( '.autocomplete-input', "<?php echo admin_url('admin.php?st_ajax_action=collection_jquery_autocomplete&taxonomy='.$taxonomy); ?>", 300 );
			-->
		</script>
		<?php
	}
	
	/**
	 * Function called on mass terms page
	 *
	 * @param string $taxonomy 
	 * @return void
	 * @author Amaury Balmer
	 */
	function massTermsJavascript( $taxonomy = '' ) {
		// Get options
		$options = get_option( STAGS_OPTIONS_NAME );
		?>
		<script type="text/javascript">
			<!--
			initjQueryAutoComplete( '.autocomplete-input', '<?php echo admin_url('admin-ajax.php') .'?action=simpletags&st_action=collection_jquery_autocomplete&taxonomy='.$taxonomy; ?>', 300 );
			-->
		</script>
		<?php
	}
}
?>