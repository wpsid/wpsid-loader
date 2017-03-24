<?php
/*
Plugin Name: WPSID
Plugin URI: https://github.com/wpsid/wpsid-main
Description: Main plguin for wpsid
Version: 1.0.0
Author: wpsid
Author URI: https://github.com
Text Domain: wpsid
*/

if ( ! defined( 'ABSPATH' ) ){
	exit; // Exit if accessed this file directly
} 

if ( is_admin() ) {


class WPSID {
	
	public $version = '1.0.0';
	private $options;
	
	function __construct() {
		add_action( 'admin_menu', array( &$this, 'add_set_page' ) );
		// add_action( 'admin_init', array( &$this, 'admin_init' ) );
		// add_filter( 'favorite_actions', array( &$this, 'action_favorite' ), 100 );
		// add_action( 'wp_before_admin_bar_render', array( &$this, 'link_admin_bar' ) );
		
      
      add_action( 'admin_init', array( $this, 'page_init' ) );
		
	}
	
	// Checks wp_reset post value and performs an installation.
	function admin_init() {
		global $current_user;

		$wp_reset = ( isset( $_POST['wp_reset'] ) && $_POST['wp_reset'] == 'true' ) ? true : false;
		$wp_reset_confirm = ( isset( $_POST['wp_reset_confirm'] ) && $_POST['wp_reset_confirm'] == 'wp-reset' ) ? true : false;
		$valid_nonce = ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wp_reset' ) ) ? true : false;

		if ( $wp_reset && $wp_reset_confirm && $valid_nonce ) {
			require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );

			$blogname = get_option( 'blogname' );
			$admin_email = get_option( 'admin_email' );
			$blog_public = get_option( 'blog_public' );

			if ( $current_user->user_login != 'admin' )
				$user = get_user_by( 'login', 'admin' );

			if ( empty( $user->user_level ) || $user->user_level < 10 )
				$user = $current_user;

			global $wpdb;

			$prefix = str_replace( '_', '\_', $wpdb->prefix );
			$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$prefix}%'" );
			foreach ( $tables as $table ) {
				$wpdb->query( "DROP TABLE $table" );
			}

			$result = wp_install( $blogname, $user->user_login, $user->user_email, $blog_public );
			extract( $result, EXTR_SKIP );

			$query = $wpdb->prepare( "UPDATE $wpdb->users SET user_pass = '".$user->user_pass."', user_activation_key = '' WHERE ID =  '".$user_id."' ");
			$wpdb->query( $query );

			$get_user_meta = function_exists( 'get_user_meta' ) ? 'get_user_meta' : 'get_usermeta';
			$update_user_meta = function_exists( 'update_user_meta' ) ? 'update_user_meta' : 'update_usermeta';

			if ( $get_user_meta( $user_id, 'default_password_nag' ) )
				$update_user_meta( $user_id, 'default_password_nag', false );

			if ( $get_user_meta( $user_id, $wpdb->prefix . 'default_password_nag' ) )
				$update_user_meta( $user_id, $wpdb->prefix . 'default_password_nag', false );

			
			if ( defined( 'REACTIVATE_THE_WP_RESET' ) && REACTIVATE_THE_WP_RESET === true )
				@activate_plugin( plugin_basename( __FILE__ ) );
			

			wp_clear_auth_cookie();
			
			wp_set_auth_cookie( $user_id );

			wp_redirect( admin_url()."?wp-reset=wp-reset" );
			exit();
		}

		if ( array_key_exists( 'wp-reset', $_GET ) && stristr( $_SERVER['HTTP_REFERER'], 'wp-reset' ) )
			add_action( 'admin_notices', array( &$this, 'my_wordpress_successfully_reset' ) );
	}
	
	// admin_menu action hook operations & Add the settings page
	function add_set_page() {
		/*
		if ( current_user_can( 'level_10' ) && function_exists( 'add_management_page' ) )
			$hook = add_management_page( 'WPSID', 'WPSID', 'level_10', 'wpsid', array( &$this, 'admin_page' ) );
		*/
		add_options_page('WPSID Config', 'WPSID', 'manage_options', 'wpsid', array( &$this, 'admin_page' ));
		// add_action( "admin_print_scripts-{$hook}", array( &$this, 'admin_script' ) );
		// add_action( "admin_footer-{$hook}", array( &$this, 'footer_script' ) );
		
		
	}
	
	function action_favorite( $actions ) {
		$reset['tools.php?page=wp-reset'] = array( 'WP Reset', 'level_10' );
		return array_merge( $reset, $actions );
	}

	function link_admin_bar() {
		global $wp_admin_bar;
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'site-name',
				'id'     => 'wp-reset',
				'title'  => 'WP Reset',
				'href'   => admin_url( 'tools.php?page=wp-reset' )
			)
		);
	}
	
	// Inform the user that WordPress has been successfully reset
	function my_wordpress_successfully_reset() {
		$user = get_user_by( 'id', 1 );
		echo '<div id="message" class="updated fade"><p><strong>WordPress has been reset back to defaults. The user "' . $user->user_login . '" was recreated with its previous password.</strong></p></div>';
		do_action( 'wordpress_reset_post', $user );
	}
	
	function admin_script() {
		wp_enqueue_script( 'jquery' );
	}

	function footer_script() {
	?>
	<script type="text/javascript">
		jQuery('#wp_reset_submit').click(function(){
			if ( jQuery('#wp_reset_confirm').val() == 'wp-reset' ) {
				var message = 'This action is not reversable.\n\nClicking "OK" will reset your database back to it\'s defaults. Click "Cancel" to abort.'
				var reset = confirm(message);
				if ( reset ) {
					jQuery('#wp_reset_form').submit();
				} else {
					jQuery('#wp_reset').val('false');
					return false;
				}
			} else {
				alert('Invalid confirmation. Please type \'wp-reset\' in the confirmation field.');
				return false;
			}
		});
	</script>	
	<?php
	}

	// add_option_page callback operations
	function admin_page() {
		global $current_user;
		/*
		if ( isset( $_POST['wp_reset_confirm'] ) && $_POST['wp_reset_confirm'] != 'wp-reset' )
			echo '<div class="error fade"><p><strong>Invalid confirmation. Please type \'wp-reset\' in the confirmation field.</strong></p></div>';
		elseif ( isset( $_POST['_wpnonce'] ) )
			echo '<div class="error fade"><p><strong>Invalid wpnonce. Please try again.</strong></p></div>';
		*/
			
	?>
	<div class="wrap">
		<div id="icon-tools" class="icon32"><br /></div>
		<h1>WPSID Config</h1>
		<form method="post" action="options.php">
		<?php
			/**
			 *
			 * @see: https://codex.wordpress.org/Creating_Options_Pages
			 */
			// Set class property
			$this->options = get_option( 'wpsid_config' );
			
			
			 // This prints out all hidden setting fields
			 settings_fields( 'wpsid_option_group' );
			 do_settings_sections( 'wpsid-setting-admin' );
			 submit_button();
		?>
		</form>
	</div>
	<?php
	}
	
    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'wpsid_option_group', // Option group
            'wpsid_config', // Option name
            array( $this, 'sanitize_func' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            'Opensid Config', // Title
            array( $this, 'print_section_info' ), // Callback
            'wpsid-setting-admin' // Page
        );  
		  
        // add_settings_field(
            // 'id_number', // ID
            // 'ID Number', // Title 
            // array( $this, 'id_number_callback' ), // Callback
            // 'wpsid-setting-admin', // Page
            // 'setting_section_id' // Section           
        // );
		  
        add_settings_field('db_name', 'DB Name', array( $this, 'db_name_callback' ), 'wpsid-setting-admin', 'setting_section_id');      
        add_settings_field('db_user', 'DB User', array( $this, 'db_user_callback' ), 'wpsid-setting-admin', 'setting_section_id');      
        add_settings_field('db_pass', 'DB Pass', array( $this, 'db_pass_callback' ), 'wpsid-setting-admin', 'setting_section_id');      
        add_settings_field('db_host', 'DB Host', array( $this, 'db_host_callback' ), 'wpsid-setting-admin', 'setting_section_id');      
        add_settings_field('path_sid', 'Path SID', array( $this, 'path_sid_callback' ), 'wpsid-setting-admin', 'setting_section_id');      
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize_func( $input ) {
        $new_input = array();
        if( isset( $input['id_number'] ) )
            $new_input['id_number'] = absint( $input['id_number'] );

        if( isset( $input['db_name'] ) ) 
            $new_input['db_name'] = sanitize_text_field( $input['db_name'] );
			
        if( isset( $input['db_user'] ) ) 
            $new_input['db_user'] = sanitize_text_field( $input['db_user'] );
			
        if( isset( $input['db_pass'] ) ) 
            $new_input['db_pass'] = sanitize_text_field( $input['db_pass'] );
			
        if( isset( $input['db_host'] ) ) 
            $new_input['db_host'] = sanitize_text_field( $input['db_host'] );
			
        if( isset( $input['path_sid'] ) ) 
            $new_input['path_sid'] = sanitize_text_field( $input['path_sid'] );

        return $new_input;
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function title_callback()
    {
        printf(
            '<input type="text" id="title" name="my_option_name[title]" value="%s" />',
            isset( $this->options['title'] ) ? esc_attr( $this->options['title']) : ''
        );
    }
    /** 
     * Print the Section text
     */
    public function print_section_info() {
        print 'Put here database connection for SID:';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function id_number_callback() {
        printf(
            '<input type="text" id="id_number" name="wpsid_config[id_number]" value="%s" />',
            isset( $this->options['id_number'] ) ? esc_attr( $this->options['id_number']) : ''
        );
    }
	 
    /** 
     * Get the settings option array and print one of its values
     */
    public function db_name_callback() {
        printf(
            '<input type="text" id="db_name" name="wpsid_config[db_name]" value="%s" />',
            isset( $this->options['db_name'] ) ? esc_attr( $this->options['db_name']) : DB_NAME
        );
    }
	 
    /** 
     * Get the settings option array and print one of its values
     */	 
    public function db_user_callback() {
        printf(
            '<input type="text" id="db_user" name="wpsid_config[db_user]" value="%s" />',
            isset( $this->options['db_user'] ) ? esc_attr( $this->options['db_user']) : DB_USER
        );
    }
    /** 
     * Get the settings option array and print one of its values
     */	 
    public function db_pass_callback() {
        printf(
            '<input type="text" id="db_pass" name="wpsid_config[db_pass]" value="%s" />',
            isset( $this->options['db_pass'] ) ? esc_attr( $this->options['db_pass']) : DB_PASSWORD
        );
    }
	 
    /** 
     * Get the settings option array and print one of its values
     */	 
    public function db_host_callback() {
        printf(
            '<input type="text" id="db_host" name="wpsid_config[db_host]" value="%s" />',
            isset( $this->options['db_host'] ) ? esc_attr( $this->options['db_host']) : DB_HOST
        );
    }
	 
    /** 
     * Get the settings option array and print one of its values
     */	 
    public function path_sid_callback() {
        printf(
            '<input type="text" id="path_sid" name="wpsid_config[path_sid]" value="%s" style="%s" />',
            isset( $this->options['path_sid'] ) ? esc_attr( $this->options['path_sid']) : ABSPATH . 'opensid', 'width: 60%'
        );
    }
}

$WPSID = new WPSID();

}
