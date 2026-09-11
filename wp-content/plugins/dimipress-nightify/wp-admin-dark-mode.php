<?php
/**
 * Plugin Name: DimiPress Nightify
 * Description: A per-user dark, light, or system-aware color mode for WordPress administration.
 * Version: 0.7.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Aleksa Dimitrijević
 * Author URI: https://dimitrium.org/en/dimipedia/aleksa-dimitrijevic
 * Plugin URI: https://dimitrium.org/en/software/dimipress-nightify
 * License: AGPL-3.0-or-later
 * Text Domain: dimipress-nightify
 * Update URI: https://dimitrium.org/en/software/dimipress-nightify
 */

defined( 'ABSPATH' ) || exit;

final class ADM_Admin_Dark_Mode {
	const VERSION = '0.7.0';
	const META_KEY = 'adm_color_mode';
	const OPTION_KEY = 'adm_default_color_mode';
	const COOKIE_KEY = 'dimipress_nightify_resolved_mode';

	public function __construct() {
		add_action( 'admin_xml_ns', array( $this, 'print_html_attributes' ), 0 );
		add_action( 'admin_head', array( $this, 'print_early_color_mode' ), 0 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_toolbar_control' ), 100 );
		add_action( 'wp_ajax_adm_set_color_mode', array( $this, 'ajax_set_color_mode' ) );
		add_action( 'show_user_profile', array( $this, 'profile_field' ) );
		add_action( 'edit_user_profile', array( $this, 'profile_field' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_field' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function color_mode( $user_id = 0 ) {
		$user_id = $user_id ?: get_current_user_id();
		$mode    = get_user_meta( $user_id, self::META_KEY, true );
		if ( ! in_array( $mode, array( 'light', 'dark', 'system' ), true ) ) {
			$mode = get_option( self::OPTION_KEY, 'system' );
		}
		return in_array( $mode, array( 'light', 'dark', 'system' ), true ) ? $mode : 'system';
	}

	/**
	 * Print dark foundations on the opening HTML tag when a prior page has
	 * already resolved the OS preference in the browser.
	 */
	public function print_html_attributes() {
		if ( ! current_user_can( 'read' ) ) {
			return;
		}
		$preference = $this->color_mode();
		$resolved   = in_array( $preference, array( 'dark', 'light' ), true ) ? $preference : '';
		if ( 'system' === $preference && isset( $_COOKIE[ self::COOKIE_KEY ] ) ) {
			$cookie_mode = sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE_KEY ] ) );
			$resolved    = in_array( $cookie_mode, array( 'dark', 'light' ), true ) ? $cookie_mode : '';
		}
		if ( ! $resolved ) {
			return;
		}
		printf(
			' data-adm-preference="%1$s" data-adm-mode="%2$s" style="background-color:%3$s;color-scheme:%2$s"',
			esc_attr( $preference ),
			esc_attr( $resolved ),
			'dark' === $resolved ? '#0D1117' : '#f0f0f1'
		);
	}

	public function enqueue_assets() {
		if ( ! current_user_can( 'read' ) ) {
			return;
		}
		/*
		 * Keep Nightify's design rules in their own stylesheet. The adaptive
		 * admin engine deliberately ignores this URL, so Midnight remains our
		 * authored system while unknown core and plugin styles are adapted.
		 */
		wp_enqueue_style( 'dimipress-nightify', plugin_dir_url( __FILE__ ) . 'assets/admin-dark-mode.css', array(), self::VERSION );
		wp_enqueue_script( 'dimipress-nightify-darkreader', plugin_dir_url( __FILE__ ) . 'assets/vendor/darkreader-4.9.130.js', array(), '4.9.130', false );
		wp_enqueue_script( 'admin-dark-mode', plugin_dir_url( __FILE__ ) . 'assets/admin-dark-mode.js', array( 'dimipress-nightify-darkreader' ), self::VERSION, false );
		wp_localize_script( 'admin-dark-mode', 'admDarkMode', array(
			'mode'  => $this->color_mode(),
			'nonce' => wp_create_nonce( 'adm_set_color_mode' ),
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'darkReaderUrl' => plugin_dir_url( __FILE__ ) . 'assets/vendor/darkreader-4.9.130.js',
			'ignoredCssUrl' => plugin_dir_url( __FILE__ ) . 'assets/admin-dark-mode.css',
		) );
	}

	/**
	 * Set the mode before the admin body is painted, avoiding a light-theme flash.
	 */
	public function print_early_color_mode() {
		if ( ! current_user_can( 'read' ) ) {
			return;
		}
		$mode = wp_json_encode( $this->color_mode() );
		$meta_color_scheme = 'dark light';
		if ( 'dark' === $this->color_mode() ) {
			$meta_color_scheme = 'dark';
		} elseif ( 'light' === $this->color_mode() ) {
			$meta_color_scheme = 'light';
		}
		printf(
			'<meta name="color-scheme" content="%1$s"><style>html[data-adm-mode="dark"]{background:#0D1117;color-scheme:dark}html[data-adm-mode="dark"] body{background:#0D1117;color:#E6EDF3}</style><script>(function(d,m){var r=d.documentElement,p=m==="system"?(matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"):m;r.setAttribute("data-adm-preference",m);r.setAttribute("data-adm-mode",p);if(p==="dark"){r.style.backgroundColor="#0D1117";r.style.colorScheme="dark";}})(document,%2$s);</script>',
			esc_attr( $meta_color_scheme ),
			$mode
		);
	}

	public function add_toolbar_control( $bar ) {
		if ( ! current_user_can( 'read' ) ) {
			return;
		}
		$bar->add_node( array(
			'id'    => 'adm-color-mode',
			'title' => '<span class="ab-icon dashicons dashicons-lightbulb" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Appearance', 'dimipress-nightify' ) . '</span>',
			'href'  => '#',
			'meta'  => array( 'title' => esc_attr__( 'Change admin color mode', 'dimipress-nightify' ) ),
		) );
	}

	public function ajax_set_color_mode() {
		check_ajax_referer( 'adm_set_color_mode', 'nonce' );
		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dimipress-nightify' ) ), 403 );
		}
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		if ( ! in_array( $mode, array( 'light', 'dark', 'system' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid color mode.', 'dimipress-nightify' ) ), 400 );
		}
		update_user_meta( get_current_user_id(), self::META_KEY, $mode );
		wp_send_json_success( array( 'mode' => $mode ) );
	}

	public function profile_field( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'Admin appearance', 'dimipress-nightify' ); ?></h2>
		<table class="form-table" role="presentation"><tr>
			<th><label for="adm_color_mode"><?php esc_html_e( 'Color mode', 'dimipress-nightify' ); ?></label></th>
			<td><select id="adm_color_mode" name="adm_color_mode">
				<?php foreach ( array( 'system' => __( 'System', 'dimipress-nightify' ), 'dark' => __( 'Midnight', 'dimipress-nightify' ), 'light' => __( 'WordPress Light', 'dimipress-nightify' ) ) as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $this->color_mode( $user->ID ), $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select><p class="description"><?php esc_html_e( 'This preference applies only to your account.', 'dimipress-nightify' ); ?></p></td>
		</tr></table>
		<?php
	}

	public function save_profile_field( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['adm_color_mode'] ) ) {
			return;
		}
		$mode = sanitize_key( wp_unslash( $_POST['adm_color_mode'] ) );
		if ( in_array( $mode, array( 'light', 'dark', 'system' ), true ) ) {
			update_user_meta( $user_id, self::META_KEY, $mode );
		}
	}

	public function register_settings_page() {
		add_options_page( __( 'DimiPress Nightify', 'dimipress-nightify' ), __( 'DimiPress Nightify', 'dimipress-nightify' ), 'manage_options', 'dimipress-nightify', array( $this, 'settings_page' ) );
	}

	public function register_settings() {
		register_setting( 'adm_settings', self::OPTION_KEY, array( 'sanitize_callback' => array( $this, 'sanitize_default' ) ) );
	}

	public function sanitize_default( $mode ) {
		return in_array( $mode, array( 'light', 'dark', 'system' ), true ) ? $mode : 'system';
	}

	public function settings_page() {
		?>
		<div class="wrap"><h1><?php esc_html_e( 'DimiPress Nightify', 'dimipress-nightify' ); ?></h1><form action="options.php" method="post">
			<?php settings_fields( 'adm_settings' ); ?>
			<table class="form-table" role="presentation"><tr><th><?php esc_html_e( 'Default color mode', 'dimipress-nightify' ); ?></th><td><select name="<?php echo esc_attr( self::OPTION_KEY ); ?>">
				<?php foreach ( array( 'system' => __( 'System', 'dimipress-nightify' ), 'dark' => __( 'Midnight', 'dimipress-nightify' ), 'light' => __( 'WordPress Light', 'dimipress-nightify' ) ) as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( get_option( self::OPTION_KEY, 'system' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select></td></tr></table>
			<?php submit_button(); ?>
		</form></div>
		<?php
	}
}

new ADM_Admin_Dark_Mode();
