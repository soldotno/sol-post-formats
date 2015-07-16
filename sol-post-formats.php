<?php
/*
Plugin Name: SOL Post Formats
Plugin URI: http://github.com/soldotno/sol-post-formats
Description: WordPress plugin to distinguish between different article types - for example to format some posts as listicles. WordPress has a built-in post format taxonomy, but does not allow modifications to it. Based on work by Stephen Harris (<a href="http://profiles.wordpress.org/stephenh1988/">WP profile</a>, <a href="https://github.com/stephenh1988">GitHub</a>).
Author: misund
Author URI: http://sol.no/
Version: 0.1.0
License: GPL2+
*/

class SOL_Post_Formats {
	// The taxonomy slug
	static $taxonomy = 'sol_post_format';

	// The ID of the original taxonomy metabox
	static $taxonomy_metabox_id = 'tagsdiv-sol_post_format';

	// The post type the metabox appears on
	static $post_type= 'post';

	public function load(){
		// Register taxonomy
		add_action( 'init', array(__CLASS__,'register_taxonomy'));

		// Remove old taxonomy meta box  
		add_action( 'admin_menu', array(__CLASS__,'remove_meta_box'));  

		// Add new taxonomy meta box  
		add_action( 'add_meta_boxes', array(__CLASS__,'add_meta_box'));  

		// Load admin scripts
		add_action('admin_enqueue_scripts',array(__CLASS__,'admin_script'));

		// Load admin scripts
		add_action('wp_ajax_radio_tax_add_taxterm',array(__CLASS__,'ajax_add_term'));

		// Add class names without prefix to post_class()
		add_action('post_class', array(__CLASS__,'modify_post_class'));

		// Add class name to TinyMCE
		add_action('tiny_mce_before_init', array(__CLASS__, 'modify_editor_class') , 10 , 2 );
	}


	public static function remove_meta_box(){  
		remove_meta_box(static::$taxonomy_metabox_id, static::$post_type, 'normal');  
	} 


	public function add_meta_box() {
		add_meta_box( 'customtagsdiv-sol_post_format', __( 'Format' ), array(__CLASS__,'metabox'), static::$post_type ,'side','core');  
	}  


	//Callback to set up the metabox  
	public static function metabox( $post ) {  
		//Get taxonomy and terms  
		$taxonomy = self::$taxonomy;

		//Set up the taxonomy object and get terms  
		$tax = get_taxonomy($taxonomy);  
		$terms = get_terms($taxonomy,array('hide_empty' => 0));  

		//Name of the form  
		$name = 'tax_input[' . $taxonomy . ']';  

		//Get current and popular terms  
		$popular = get_terms( $taxonomy, array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 10, 'hierarchical' => false ) );  
		$postterms = get_the_terms( $post->ID,$taxonomy );  
		$current = ($postterms ? array_pop($postterms) : false);  
		$current = ($current ? $current->term_id : 0);  
?>

			<div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">
				<?php wp_nonce_field( 'radio-tax-add-'.$taxonomy, '_wpnonce_radio-add-tag', false ); ?>

				<!-- Display tabs -->
				<ul id="<?php echo $taxonomy; ?>-tabs" class="category-tabs">
					<li class="tabs"><a href="#<?php echo $taxonomy; ?>-all" tabindex="3"><?php echo $tax->labels->all_items; ?></a></li>
					<li class="hide-if-no-js"><a href="#<?php echo $taxonomy; ?>-pop" tabindex="3"><?php _e( 'Most Used' ); ?></a></li>
				</ul>

				<!-- Display taxonomy terms -->
				<div id="<?php echo $taxonomy; ?>-all" class="tabs-panel">
					<ul id="<?php echo $taxonomy; ?>checklist" class="list:<?php echo $taxonomy?> categorychecklist form-no-clear">
<?php foreach($terms as $term){
$id = (is_taxonomy_hierarchical($taxonomy)) ? $taxonomy . '-' . $term->term_id : $taxonomy . '-' . $term->slug;
$value= (is_taxonomy_hierarchical($taxonomy) ? "value='{$term->term_id}'" : "value='{$term->slug}'");
echo "<li id='$id'><label class='selectit'>";
echo "<input type='radio' id='in-$id' name='{$name}'".checked($current,$term->term_id,false)." {$value} />$term->name<br />";
echo "</label></li>";
		}?>
					</ul>
				</div>

				<!-- Display popular taxonomy terms -->
				<div id="<?php echo $taxonomy; ?>-pop" class="tabs-panel" style="display: none;">
					<ul id="<?php echo $taxonomy; ?>checklist-pop" class="categorychecklist form-no-clear" >
<?php foreach($popular as $term){
$id = 'popular-'.$taxonomy.'-'.$term->term_id;
$value= (is_taxonomy_hierarchical($taxonomy) ? "value='{$term->term_id}'" : "value='{$term->slug}'");
echo "<li id='$id'><label class='selectit'>";
echo "<input type='radio' id='in-$id'".checked($current,$term->term_id,false)." {$value} />$term->name<br />";
echo "</label></li>";
}?>
					</ul>
				</div>

				 <p id="<?php echo $taxonomy; ?>-add" class="">
					<label class="screen-reader-text" for="new<?php echo $taxonomy; ?>"><?php echo $tax->labels->add_new_item; ?></label>
					<input type="text" name="new<?php echo $taxonomy; ?>" id="new<?php echo $taxonomy; ?>" class="form-required form-input-tip" value="<?php echo esc_attr( $tax->labels->new_item_name ); ?>" tabindex="3" aria-required="true"/>
					<input type="button" id="" class="radio-tax-add button" value="<?php echo esc_attr( $tax->labels->add_new_item ); ?>" tabindex="3" />
				</p>
		  </div>
<?php  
	}

	public function admin_script(){  
		wp_register_script( 'radiotax', plugins_url( '/js/radiotax.js', __FILE__ ), array('jquery'), null, true ); // We specify true here to tell WordPress this script needs to be loaded in the footer  
		wp_localize_script( 'radiotax', 'radio_tax', array('slug'=>self::$taxonomy));
		wp_enqueue_script( 'radiotax' );  
	}

	public function ajax_add_term(){

		$taxonomy = !empty($_POST['taxonomy']) ? $_POST['taxonomy'] : '';
		$term = !empty($_POST['term']) ? $_POST['term'] : '';
		$tax = get_taxonomy($taxonomy);

		check_ajax_referer('radio-tax-add-'.$taxonomy, '_wpnonce_radio-add-tag');

		if(!$tax || empty($term))
			exit();

		if ( !current_user_can( $tax->cap->edit_terms ) )
			die('-1');

		$tag = wp_insert_term($term, $taxonomy);

		if ( !$tag || is_wp_error($tag) || (!$tag = get_term( $tag['term_id'], $taxonomy )) ) {
			//TODO Error handling
			exit();
		}

		$id = $taxonomy.'-'.$tag->term_id;
		$name = 'tax_input[' . $taxonomy . ']';
		$value= (is_taxonomy_hierarchical($taxonomy) ? "value='{$tag->term_id}'" : "value='{$term->tag_slug}'");

		$html = '<li id="'.$id.'"><label class="selectit"><input type="radio" id="in-'.$id.'" name="'.$name.'" '.$value.' />'. $tag->name.'</label></li>';

		echo json_encode(array('term'=>$tag->term_id,'html'=>$html));
		exit();
	}

	function register_taxonomy() {
		// create a new taxonomy
		register_taxonomy(
			static::$taxonomy,
			'post',
			array(
				'label' => __( 'SOL Post Formats' ),
				//'rewrite' => array( 'slug' => 'sol-post-format' ),
				'rewrite' => false,
				'capabilities' => array(
					'manage_terms' => 'activate_plugins',
					'edit_terms' => 'activate_plugins',
					'delete_terms' => 'activate_plugins',
					'assign_terms' => 'edit_posts'
				)
			)
		);
	}

	/**
	 * Modify the post classes in the front end.
	 *
	 * In order to display listicle styles in the theme, we append a class to posts with a post type.
	 */
	public static function modify_post_class( $classes ) {
		global $post;

		if ( !get_the_terms( $post->ID , static::$taxonomy ) )
			return $classes;

		foreach ( get_the_terms( $post->ID , static::$taxonomy ) as $term )
			$classes[] = $term->slug;

		return $classes;
	}

	/**
	 * Modify the body classes on TinyMCE's iframe.
	 *
	 * In order to display listicle styles in the TinyMCE editor's iframe, we append a class to it's body element.
	 */
	public static function modify_editor_class( $mceInit, $editor_id ) {
		global $post;

		if ( !get_the_terms( $post->ID , static::$taxonomy ) )
			return $mceInit;

		$classes = array(
			$mceInit['body_class']
		);

		foreach ( get_the_terms( $post->ID , static::$taxonomy ) as $term )
			$classes[] = $term->slug;

		$mceInit['body_class'] = implode( ' ' , $classes );

		return $mceInit;
	}
}

SOL_Post_Formats::load();
?>
