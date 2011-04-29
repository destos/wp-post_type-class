<?php

/*
Plugin Name: New Post Type
Plugin URI: http://patrick.forringer.com/plugins/newposttype
Description: Allows the easy creation of posttypes and taxonomies.
Version: 1
Author: Patrick Forringer
Author URI: http://patrick.forringer.com
*/

// --------------------------------------------------------
// Base for creating new post types, logs new post types for icon support.
//

class NewPostType{
	
	private static $_instance;
	
	public static $_registered_types = array();
	
	public static function instance(){
	
		if(!isset(self::$_instance)){
			self::$_instance = new NewPostType();
		}
		
		return self::$_instance;
	}
	
	public function __construct(){
	
		add_action( 'admin_head',			array( &$this, 'admin_head' ) );
		
		add_action( 'init',						array( &$this, 'register_columns' ), 40 ); //40 is after post types are registered
		
		// manage column output for new types
		add_action("manage_posts_custom_column",	array( &$this, "column_content" ));
	}
	
	// Output post type icons.
	public static function admin_head( ){

		if( !is_array( self::$_registered_types ) || count(self::$_registered_types) <= 0 )
			return;
		
?>
		
<!-- custom post type icons -->
<style type="text/css" media="screen">
<?php
		foreach( self::$_registered_types as $type_obj ){
			
			$menu_icon = $type_obj->args['menu_icon'];
			
			// if no menu icon skip
			if( empty( $menu_icon ) )
				continue;
				
			$post_type = str_ireplace('_','', $type_obj->post_type); // no idea why it does this
			
			$image_selectors[] = "#menu-posts-{$post_type} .wp-menu-image img";
			$hov_cur_selectors[] = "#menu-posts-{$post_type}:hover .wp-menu-image,\n\t#menu-posts-{$post_type}.wp-has-current-submenu .wp-menu-image"
?>
	#menu-posts-<?php echo $post_type ?> .wp-menu-image {
	    background: url(<?php echo $menu_icon ?>) no-repeat 6px -17px;
	}
<?php	}	?>
	
	<?php echo implode(",\n\t", $hov_cur_selectors)?>{
		background-position:6px 7px;
	}
	
	<?php echo implode(",\n\t", $image_selectors)?>{
		display: none;
	}
	
</style>
<?php

	}
		
	public static function add( $args ){
		
		$type = new PostTypeTemplate( $args );
		
		$instance = NewPostType::instance();
		#TODO php version check here :: isn't supported below 5.3 apparently
		$instance::$_registered_types[(string)$type] = &$type;
		
		return $type;
	}
	
		
	public function register_columns(){
		
		foreach( self::$_registered_types as $post_type )
			add_filter("manage_{$post_type}_posts_columns",		array( &$this, "column_headers" ) );
		
		//global $wp_filters;
		//print_r($wp_filters);
	}
	
	public function column_headers( $columns ){
		
		$post_type = get_post_type();
		$post_type_obj = self::$_registered_types[$post_type];
		
		// Add thumbnail
		if( post_type_supports( $post_type, 'thumbnail' ) )
			$columns['featured_thumb'] = 'Thumbnail';
		
		// Add registered taxonomies
		$taxonomies = $post_type_obj->_registered_taxonomies;
		
		if(is_array($taxonomies) && !empty($taxonomies)){
			foreach( $taxonomies as $tax )
				$columns[(string)$tax] = $tax->column_header; // maybe att a 'vague' property eg. taxonomy(ies)
		}
		
		//$columns['an_excerpt'] = 'Excerpt';
	
		#TODO try to sort columns into preferred order
		
		return $columns;
	}
	
	// runs on manage_posts_custom_column defined in __construct
	public function column_content($column){
		
		$post_type = get_post_type();
		
		$taxonomies =& self::$_registered_types[$post_type]->_registered_taxonomies;

		// find out if its a taxonomy
		if( $taxonomies != null AND array_key_exists( $column, $taxonomies ) ){
			//echo 'column is tax!';
			$tax_slug = $column;
			$column = 'taxonomy';
		}
		
		//global $post;
		switch ($column)
		{
			case "an_excerpt":
				the_excerpt();
				break;
			case "featured_thumb":
				if(has_post_thumbnail())
					the_post_thumbnail( array(60,60) );
				break;
			case "taxonomy":
				$terms = get_the_terms(get_the_ID(), $tax_slug);
				if( $terms ){
					$term_html = array();
					foreach ($terms as $term)
						array_push( $term_html, '<a href="' . get_term_link( $term->slug, $tax_slug ) . '">' . $term->name . '</a>' );
					
					echo implode( $term_html, ", " );
				}
				break;
			case "attachments":
				#TODO output list of attachnments
				break;
		}
		
	}

}

// --------------------------------------------------------
// Taxonomy Template
//

class TaxonomyTemplate{

	// has to check if taxonomy already exists (or is reserved term) and
	// if it does clone its settings into template obj
	
	public $taxonomy;
	
	public $taxonomy_single;
	
	public $taxonomy_plural;
	
	public $column_header;
	
	public $labels = array();
	
	public $args = array();
	
	public $post_type;
	
	function __construct( $tax_args ){
		
		$tax_args = array_intersect_key( $tax_args, array_flip( array(
			'taxonomy',
			'taxonomy_single',
			'taxonomy_plural',
			'column_header',
			'post_type',
			'args',
			'labels'
		)));
		
    foreach($tax_args as $_key => $_value){
        $this->$_key = $_value;
    }
    
    unset( $tax_args, $_key, $_value);
		
		if( empty($this->taxonomy) || !is_string($this->taxonomy) )
			return;	
			
		$this->taxonomy_single = (string) ($this->taxonomy_single)
			? $this->taxonomy_single
			: ucfirst( $this->taxonomy );
			
		$this->taxonomy_plural = (string) ($this->taxonomy_plural)
			? $this->taxonomy_plural
			: PostTypeUtil::pluralize( $this->taxonomy_single );
		
		$this->column_header = (string) ($this->column_header)
			? $this->column_header
			: $this->taxonomy_plural;
		
		// register last, so arguments can be modified.
		add_action('init',									array( &$this, 'register' ), 10 );
    
	}
	
	//
	// return the posttypes' name
	//
	public function __toString(){
		if( !empty($this->taxonomy) )
			return $this->taxonomy;
	}
	
	function register(){

		$this->labels = wp_parse_args( $this->labels, array(
			'name' => sprintf( _x( '%s', 'taxonomy general name' ), $this->taxonomy_plural ),
			'singular_name' => sprintf( _x( '%s', 'taxonomy singular name' ), $this->taxonomy_single ),
			'search_items' =>  sprintf( __( 'Search %s' ), $this->taxonomy_plural ),
			'popular_items' => sprintf( __( 'Popular %s' ), $this->taxonomy_plural ),
			'all_items' => sprintf( __( 'All %s' ), $this->taxonomy_plural ),
			'parent_item' => sprintf( __( 'Parent Genre' ), $this->taxonomy_single ),
			'parent_item_colon' => sprintf( __( 'Parent Genre:' ), $this->taxonomy_single),
			'edit_item' => sprintf( __( 'Edit %s' ), $this->taxonomy_single ), 
			'update_item' => sprintf( __( 'Update %s' ), $this->ta_single ),
			'add_new_item' => sprintf( __( 'Add New %s' ), $this->taxonomy_single ),
			'new_item_name' => sprintf( __( 'New %s Name' ), $this->taxonomy_single ),
			'separate_items_with_commas' => sprintf( __( 'Separate %s with commas' ),strtolower( $this->taxonomy_plural ) ),
			'add_or_remove_items' => sprintf( __( 'Add or remove %s' ), strtolower( $this->taxonomy_plural ) ),
			'choose_from_most_used' => sprintf( __( 'Choose from the most used %s' ), strtolower( $this->taxonomy_plural ) ),
			'menu_name' => $this->taxonomy_plural
	  ) );
	  
	  $this->args = wp_parse_args( $this->args, array(
			'hierarchical' => false,
			'labels' => $this->labels,
			'show_ui' => true,
			'query_var' => true,
			'rewrite' => array( 'slug' => $this->taxonomy ),
	  ));
		
		register_taxonomy( $this->taxonomy, $this->post_type, $this->args );
		
	}
}

// --------------------------------------------------------
// Post Type Template
//

class PostTypeTemplate{
	
	// public settable vars set when constructing new class
	public $post_type = false;

	public $post_type_name = false;
	
	public $post_type_plural = false;
	
	public $labels = array();
	
	public $args = array();
	
	public $messages = array();
	
	public $thumbs = false;
	
	public $taxonomies = array();
	
	public $_registered_taxonomies = array();
	
	//
	//  setup
	//
	function __construct( $type_args ){
		
		// only allow these arguments
		$type_args = array_intersect_key( $type_args, array_flip( array(
			'post_type',
			'post_type_name',
			'post_type_plural',
			'args',
			'labels',
			'messages',
			'thumbs')));
		
		//print_r($type_args);
		
    foreach($type_args as $_key => $_value){
        $this->$_key = $_value;
    }
    
    unset( $type_args, $_key, $_value);
		
		// require post type specified
		if( empty($this->post_type) || !is_string($this->post_type) )
			return;
			
		$this->post_type_name = (string) ($this->post_type_name)
			? $this->post_type_name
			: ucfirst( $this->post_type );
			
		$this->post_type_plural = (string) ($this->post_type_plural)
			? $this->post_type_plural
			: PostTypeUtil::pluralize( $this->post_type_name );
		
		// handle rendering of thumbnails
		add_action("npt_before_{$this->post_type}_register", array( &$this, 'register_thumbs' ), 10 );
		
		// register any taxonomies if present.
		add_action('init',										array( &$this, 'register_taxonomies' ), 20 );
		
		// register last, so arguments can be modified.
		add_action('init',										array( &$this, 'register' ), 30 );
		
		// update posttypes' messages
		add_filter('post_updated_messages',		array( &$this, 'update_messages' ) );
		
	}
	
	//
	// return the posttypes' name
	//
	public function __toString(){
		if( !empty($this->post_type) )
			return $this->post_type;
	}
	
	//
	// processes thumbnails
	//
	public function register_thumbs(){
		
		//register thumbnail sizes
		if( is_array($this->thumbs) && !empty($this->thumbs) ){
			
			// add support for thumbnails it not already present.
			$this->add_support('thumbnail');
			
			foreach( $this->thumbs as $name => $vals){
				list( $width, $height, $crop ) = $vals;
				add_image_size( $name, $width, $height, $crop );
			}
		}
	}
	
	//
	// allows you to programatically add support for features you haven't specified if required.
	// accepts array or single string of values.
	//
	public function add_support($support_arr){
	
		if( !is_array($support_arr))
			$support_arr = (array) $support_arr;
		
		$support_arr = array_values($support_arr);
			
		$this->args['supports'] = wp_parse_args( $support_arr, $this->args['supports']);
		
		return $this;
	}
	
	public function register_taxonomies(){
		//print_r( $this->_registered_taxonomies );
	}
	
	public function add_taxonomy( $taxonomy, $args = array() ){
	
		$args['post_type'] = $this->post_type;
		$args['taxonomy'] = $taxonomy;
		
		$taxonomy = new TaxonomyTemplate( $args );
		
		$this->_registered_taxonomies[(string)$taxonomy] =& $taxonomy;
		
		return $this;
	}
	
	public function add_metabox( $args = array() ){
	
		if( class_exists('cmb_Meta_Box') ){
		
			$meta_args = wp_parse_args( $args, array(
				'id' => $this->post_type.'_metabox', // TODO: auto increment
				'title' => $this->post_type_name.' Metabox',
				'pages' => array(  $this->post_type ), // post type
				'context' => 'normal',
				'priority' => 'high',
				'show_names' => true, // Show field names on the left
				'fields' => array()
			));
			
			new cmb_Meta_Box($meta_args);
		}
		
	}
	
	public function add_columns(){
		
	}
	
	public function register(){
		
		#TODO overides for labels and arguments.
		$this->labels = wp_parse_args( $this->labels, array(
	    'name' => _x( $this->post_type_plural, 'post type general name' ),
	    'singular_name' => _x( $this->post_type_name, 'post type singular name' ),
	    'add_new' => _x( 'Add New', $this->post_type_name ),
	    'add_new_item' => sprintf( __( 'Add New %s') ,$this->post_type_name ),
	    'edit_item' => sprintf( __( 'Edit %s'), $this->post_type_name ),
	    'new_item' => sprintf( __( 'New %s'), $this->post_type_name ),
	    'view_item' => sprintf( __( 'View %s' ), $this->post_type_name ),
	    'search_items' => sprintf( __( 'Search %s' ), $this->post_type_plural ),
	    'not_found' =>  sprintf( __( 'No %s found' ), strtolower( $this->post_type_plural) ),
	    'not_found_in_trash' => sprintf( __( 'No %s found in Trash' ), strtolower( $this->post_type_plural ) ),
	    'parent_item_colon' => '',
	    'menu_name' =>  $this->post_type_plural
	  ));
	  
	  $this->args = wp_parse_args( $this->args, array(
	    'labels' => $this->labels,
	    'public' => true,
	    'publicly_queryable' => true,
	    'show_ui' => true, 
	    'show_in_menu' => true, 
	    'query_var' => true,
	    'rewrite' => true,
	    'capability_type' => 'post',
	    'hierarchical' => false,
	    'menu_position' => null,
	    'supports' => array( 'title','editor','author','excerpt','comments' ),
	    'has_archive' => strtolower( $this->post_type_plural ),
	    'show_in_nav_menus' => true
	  ));
	  
	  do_action( "npt_before_{$this->post_type}_register" );
	  
	  register_post_type( $this->post_type, $this->args );

	}
	
	public function update_messages( $messages ){
		
		$this->messages[ $this->post_type ] = wp_parse_args( $this->messages, array(
			0 => '', // Unused. Messages start at index 1.
			1 => sprintf( __('%s updated. <a href="%s">View %s</a>'), $this->post_type_name, esc_url( get_permalink($post_ID) ), strtolower( $this->post_type_name ) ),
			2 => __('Custom field updated.'),
			3 => __('Custom field deleted.'),
			4 => sprintf(__('%s updated.'), $this->post_type_name),
			/* translators: %s: date and time of the revision */
			5 => isset($_GET['revision']) ? sprintf( __('%s restored to revision from %s'), $this->post_type_name, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __('%s published. <a href="%s">View %s</a>'), $this->post_type_name, esc_url( get_permalink($post_ID) ), strtolower( $this->post_type_name ) ),
			7 => sprintf( __('%s saved.'), $this->post_type_name ),
			8 => sprintf( __('%s submitted. <a target="_blank" href="%s">Preview %s</a>'), $this->post_type_name, esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ), strtolower( $this->post_type_name ) ),
			9 => sprintf( __('%s scheduled for: <strong>%s</strong>. <a target="_blank" href="%s">Preview %s</a>'), $this->post_type_name,
			 // translators: Publish box date format, see http://php.net/date
			 date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ), strtolower( $this->post_type_name ) ),
			10 => sprintf( __('%s draft updated. <a target="_blank" href="%s">Preview %s</a>'), $this->post_type_name, esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ), strtolower( $this->post_type_name ) ),
	  ));
	
	  return $this->messages;

	}

}

// --------------------------------------------------------
// Post Type Utilities
//

class PostTypeUtil{
	
	public static function pluralize( $string ){

    $plural = array(
			array( '/(quiz)$/i',               "$1zes"   ),
			array( '/^(ox)$/i',                "$1en"    ),
			array( '/([m|l])ouse$/i',          "$1ice"   ),
			array( '/(matr|vert|ind)ix|ex$/i', "$1ices"  ),
			array( '/(x|ch|ss|sh)$/i',         "$1es"    ),
			array( '/([^aeiouy]|qu)y$/i',      "$1ies"   ),
			array( '/([^aeiouy]|qu)ies$/i',    "$1y"     ),
			array( '/(hive)$/i',               "$1s"     ),
			array( '/(?:([^f])fe|([lr])f)$/i', "$1$2ves" ),
			array( '/sis$/i',                  "ses"     ),
			array( '/([ti])um$/i',             "$1a"     ),
			array( '/(buffal|tomat)o$/i',      "$1oes"   ),
			array( '/(bu)s$/i',                "$1ses"   ),
			array( '/(alias|status)$/i',       "$1es"    ),
			array( '/(octop|vir)us$/i',        "$1i"     ),
			array( '/(ax|test)is$/i',          "$1es"    ),
			array( '/s$/i',                    "s"       ),
			array( '/$/',                      "s"       )
		);

		$irregular = array(
			array( 'move',   'moves'    ),
			array( 'sex',    'sexes'    ),
			array( 'child',  'children' ),
			array( 'man',    'men'      ),
			array( 'person', 'people'   )
		);

		$uncountable = array( 
			'sheep', 
			'fish',
			'series',
			'species',
			'money',
			'rice',
			'information',
			'equipment',
			'featured',
			'software',
		);

		// save some time in the case that singular and plural are the same
		if ( in_array( strtolower( $string ), $uncountable ) )
			return $string;
		
		// check for irregular singular forms
		foreach ( $irregular as $noun ){
			if ( strtolower( $string ) == $noun[0] )
			return $noun[1];
		}
		
		// check for matches using regular expressions
		foreach ( $plural as $pattern ){
			if ( preg_match( $pattern[0], $string ) )
			return preg_replace( $pattern[0], $pattern[1], $string );
		}
		
		return $string;
  }
}