<?php

// --------------------------------------------------------
// Base for creating new post types.
//
class NewPostType{
	
	private static $_instance;
	
	public static $_registered_types = array();
	
	private static $_loc;
	
	public static function instance(){
	
		if(!isset(self::$_instance)){
			self::$_instance = new NewPostType();
		}
		
		return self::$_instance;
	}
	
	public function __construct(){
		add_action( 'admin_head', array( &$this, 'admin_head' ) );
		//$this->$_loc = dirname(__FILE__);
	}
	
	// Output post type icons.
	public static function admin_head( ){
	
		if( !is_array( self::$_registered_types ))
			return;
		
		?>
		<!-- custom post type icons -->
		<style type="text/css" media="screen">
		<?php
		foreach( self::$_registered_types as $type_obj ){
		
		$menu_icon = $type_obj->args['menu_icon'];
		if( empty( $menu_icon ) )
			continue;
?>
		#menu-posts-<?php echo $type_obj->post_type ?> .wp-menu-image {
		    background: url(<?php echo $menu_icon ?>) no-repeat 6px -17px !important;
		}
		#menu-posts-<?php echo $type_obj->post_type ?> .wp-menu-image img {
		    display: none;
		}
		#menu-posts-<?php echo $type_obj->post_type ?>:hover .wp-menu-image,
		#menu-posts-<?php echo $type_obj->post_type ?>.wp-has-current-submenu .wp-menu-image {
		    background-position:6px 7px!important;
		}

<?php	}	?>
		</style><?php
	}
		
	public static function add( $args ){
		
		$type = new post_type_template( $args );
		
		$instance = NewPostType::instance();
		
		array_push( $instance::$_registered_types, &$type );
		
		return $type;
	}

}

class post_type_template{

	public $post_type = false;

	public $post_type_name = false;
	
	public $post_type_plural = false;
	
	public $labels = array();
	
	public $args = array();
	
	public $messages = array();
	
	public $thumbs = false;
	
	function __construct( $type_args ){
		
		$type_args = array_intersect_key($type_args, array_flip( array(
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
    
    unset($_key, $_value);
		
		//if( !empty($this->post_type) )
		$this->post_type = (string) ( $this->post_type )
			? $this->post_type
			: get_class($this);
			
		$this->post_type_name = (string) ($this->post_type_name)
			? $this->post_type_name
			: ucfirst( $this->post_type );
			
		$this->post_type_plural = (string) ($this->post_type_plural)
			? $this->post_type_plural
			: ucfirst( self::pluralize( $this->post_type ) );
		
		add_action('init',									array ( &$this, 'thumbs' ) );
		add_action('init',									array ( &$this, 'register' ) );
		add_filter('post_updated_messages', array ( &$this, 'update_messages' ) );

	}
	
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

	public function thumbs(){
	
		// add support for thumbnails.
		
		//$this->args['supports'] = wp_parse_args( array('thumbnail'), $this->args['supports']);
			//print_r($this->args);
			
		//register thumbnail sizes
		if( is_array($this->thumbs) )
		foreach( $this->thumbs as $name => $vals){
			list( $width, $height, $crop ) = $vals;
			add_image_size( $name, $width, $height, $crop );
		}
		
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
	  ) );
	  
	  $this->args = wp_parse_args( $this->args, array(
	    'labels' => $this->labels,
	    'public' => true,
	    'publicly_queryable' => true,
	    'show_ui' => true, 
	    'show_in_menu' => true, 
	    'query_var' => true,
	    'rewrite' => true,
	    'capability_type' => 'post',
	    'has_archive' => true, 
	    'hierarchical' => false,
	    'menu_position' => null,
	    'supports' => array( 'title','editor','author','excerpt','comments' ),
	    'has_archive' => strtolower( $this->post_type_plural ),
	    'show_in_nav_menus' => true
	  )); 
	  
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