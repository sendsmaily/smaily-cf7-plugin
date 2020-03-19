<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/sendsmaily/smaily-cf7-plugin
 * @since      1.0.0
 *
 * @package    Smaily_For_CF7
 * @subpackage Smaily_For_CF7/admin
 * @author     Smaily
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Smaily_For_CF7
 * @subpackage Smaily_For_CF7/admin
 */
class Smaily_For_CF7_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since      1.0.0
	 * @param      string $plugin_name  The name of this plugin.
	 * @param      string $version         The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			plugins_url( 'js/smaily-for-cf7-admin.js', __FILE__ ),
			array( 'jquery', 'wpcf7-admin' ),
			$this->version,
			true,
		);
		wp_localize_script(
			$this->plugin_name,
			$this->plugin_name,
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'smailyforcf7-verify-credentials' ),
			)
		);
	}

	/**
	 * Save the connected form ID (name of the option),
	 * Smaily credentials & autoresponder data to database.
	 *
	 * @param WPCF7_ContactForm $args Arguments of form.
	 */
	public function save( $args ) {
		$can_user_edit = current_user_can( 'wpcf7_edit_contact_form', $args->id() );

		if ( empty( $_POST ) || ! $can_user_edit ) {
			return;
		}
		// Validation and sanitization.
		$subdomain = isset( $_POST['smailyforcf7']['subdomain'] ) ? trim( $_POST['smailyforcf7']['subdomain'] ) : null;
		$username  = isset( $_POST['smailyforcf7']['username'] ) ? trim( $_POST['smailyforcf7']['username'] ) : null;
		$password  = isset( $_POST['smailyforcf7']['password'] ) ? trim( $_POST['smailyforcf7']['password'] ) : null;
		$subdomain = $this->normalize_subdomain( $subdomain );
		$sanitized = $this->sanitize_credentials( $subdomain, $username, $password );

		$autoresponder = isset( $_POST['smailyforcf7-autoresponder'] ) ? (int) $_POST['smailyforcf7-autoresponder'] : 0;
		// Delete option here for clearing and unlinking credentials.
		if ( empty( $sanitized['subdomain'] ) && empty( $sanitized['username'] ) && empty( $sanitized['password'] ) ) {
			delete_option( 'smailyforcf7_form_' . $args->id() );
			return;
		}

		$response = $this->fetch_autoresponders(
			$sanitized['subdomain'],
			$sanitized['username'],
			$sanitized['password'],
		);

		// Don't save invalid credentials.
		if ( 200 !== $response['code'] ) {
			return;
		}

		$data_to_save = array(
			'api-credentials' => array(
				'subdomain' => $sanitized['subdomain'],
				'username'  => $sanitized['username'],
				'password'  => $sanitized['password'],
			),
			'autoresponder'   => $autoresponder,
		);
		update_option( 'smailyforcf7_form_' . $args->id(), $data_to_save );
	}

	/**
	 * Content of 'Smaily for Contact Form 7' tab
	 *
	 * @param WPCF7_ContactForm $args Contact Form 7 tab arguments.
	 */
	public function panel_content( $args ) {
		$form_id = WPCF7_ContactForm::get_current()->id();

		// Fetch saved Smaily CF7 option here to pass data along to view.
		$smailyforcf7_option   = get_option( 'smailyforcf7_form_' . $form_id, array() );
		$smaily_credentials    = isset( $smailyforcf7_option['api-credentials'] ) ? $smailyforcf7_option['api-credentials'] : array();
		$default_autoresponder = isset( $smailyforcf7_option['autoresponder'] ) ? $smailyforcf7_option['autoresponder'] : 0;

		$subdomain = isset( $smaily_credentials['subdomain'] ) ? $smaily_credentials['subdomain'] : null;
		$username  = isset( $smaily_credentials['username'] ) ? $smaily_credentials['username'] : null;
		$password  = isset( $smaily_credentials['password'] ) ? $smaily_credentials['password'] : null;

		// Fetch autoresponder data here for view.
		$response           = $this->fetch_autoresponders( $subdomain, $username, $password );
		$autoresponder_list = $response['autoresponders'];
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/smaily-for-cf7-admin-display.php';
	}

	/**
	 * Add Smaily configuration panel to Contact Form 7 panels array.
	 *
	 * @param array $panels Contact Form 7's panels.
	 * @return array $merged_panels Array of CF7 tabs, including a Smaily tab.
	 */
	public function add_tab( $panels ) {
		$panel = array(
			'smailyforcf7' => array(
				'title'    => __( 'Smaily for Contact Form 7', 'smaily-for-cf7' ),
				'callback' => array( $this, 'panel_content' ),
			),
		);

		$merged_panels = array_merge( $panels, $panel );
		return $merged_panels;
	}

	/**
	 * Verify provided Smaily API credentials with GET request.
	 *
	 * @param string $subdomain Smaily API Subdomain.
	 * @param string $username Smaily API Username.
	 * @param string $password Smaily API Password.
	 * @return array $response
	 */
	private function fetch_autoresponders( $subdomain, $username, $password ) {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-smaily-for-cf7-request.php';
		$request = ( new Smaily_For_CF7_Request() )
					->auth( $username, $password )
					->setUrl( 'https://' . $subdomain . '.sendsmaily.net/api/workflows.php?trigger_type=form_submitted' )
					->get();

		$response = array();
		if ( empty( $request ) ) {
			$response['code']    = 500;
			$response['message'] = esc_html__( 'No response from Smaily', 'smaily-for-cf7' );
			return $response;
		}

		$response['code'] = isset( $request['code'] ) ? (int) $request['code'] : 0;
		switch ( $response['code'] ) {
			case 200:
				$response['message'] = esc_html__( 'Credentials valid', 'smaily-for-cf7' );
				break;
			case 401:
				$response['message'] = esc_html__( 'Wrong credentials', 'smaily-for-cf7' );
				break;
			case 404:
				$response['message'] = esc_html__( 'Error in subdomain', 'smaily-for-cf7' );
				break;
			default:
				$response['message'] = esc_html__( 'Something went wrong', 'smaily-for-cf7' );
				break;
		}
		$response['autoresponders'] = isset( $request['body'] ) ? $request['body'] : array();

		if ( empty( $response['autoresponders'] ) ) {
			return $response;
		}
		$autoresponder_list = array();
		foreach ( $response['autoresponders'] as $autoresponder ) {
			if ( ! empty( $autoresponder['id'] ) && ! empty( $autoresponder['title'] ) ) {
				$autoresponder_list[ $autoresponder['id'] ] = trim( $autoresponder['title'] );
			}
		}
		$response['autoresponders'] = $autoresponder_list;
		return $response;
	}

	/**
	 * Callback function for "verify credentials" button.
	 */
	public function verify_credentials_callback() {
		if (
			! isset( $_POST['nonce'] )
			|| ! wp_verify_nonce( $_POST['nonce'], 'smailyforcf7-verify-credentials' )
		) {
			wp_die( esc_html__( 'Your nonce did not verify!', 'smaily-for-cf7' ) );
		}
		$form_id = isset( $_POST['form_id'] ) ? (int) wp_unslash( $_POST['form_id'] ) : 0;
		if ( ! current_user_can( 'wpcf7_edit_contact_form', $form_id ) ) {
			$response['message'] = esc_html__( 'You do not have permission!', 'smaily-for-cf7' );
			$response['code']    = 403;
			wp_send_json( $response );
		}

		$subdomain     = isset( $_POST['subdomain'] ) ? trim( $_POST['subdomain'] ) : null;
		$username      = isset( $_POST['username'] ) ? trim( $_POST['username'] ) : null;
		$password      = isset( $_POST['password'] ) ? trim( $_POST['password'] ) : null;
		$autoresponder = isset( $_POST['autoresponder'] ) ? (int) $_POST['autoresponder'] : 0;

		$sanitized              = $this->sanitize_credentials( $subdomain, $username, $password );
		$sanitized['subdomain'] = $this->normalize_subdomain( $sanitized['subdomain'] );

		if (
			empty( $sanitized['subdomain'] )
			|| empty( $sanitized['username'] )
			|| empty( $sanitized['password'] )
		) {
			$response['message'] = esc_html__( 'Please fill out all fields!', 'smaily-for-cf7' );
			$response['code']    = 422;
			wp_send_json( $response );
		}

		$response = $this->fetch_autoresponders(
			$sanitized['subdomain'],
			$sanitized['username'],
			$sanitized['password'],
		);

		if ( 200 === $response['code'] && ! empty( $form_id ) ) {
			$data_to_save = array(
				'api-credentials' => array(
					'subdomain' => $sanitized['subdomain'],
					'username'  => $sanitized['username'],
					'password'  => $sanitized['password'],
				),
				'autoresponder'   => $autoresponder,
			);
			update_option( 'smailyforcf7_form_' . $form_id, $data_to_save );
		}
		wp_send_json( $response );
	}

	/**
	 * Remove saved Smaily API credentials and delete entry in wp_options database.
	 */
	public function remove_credentials_callback() {
		$form_id = isset( $_POST['form_id'] ) ? (int) wp_unslash( $_POST['form_id'] ) : 0;
		if ( ! current_user_can( 'wpcf7_delete_contact_form', $form_id ) ) {
			$response['message'] = esc_html__( 'You do not have permission!', 'smaily-for-cf7' );
			$response['code']    = 403;
			wp_send_json( $response );
		}
		if ( get_option( 'smailyforcf7_form_' . $form_id ) ) {
			delete_option( 'smailyforcf7_form_' . $form_id );
			$response['message'] = esc_html__( 'Credentials removed', 'smaily-for-cf7' );
			$response['code']    = 200;
		} else {
			$response['message'] = esc_html__( 'No credentials to remove', 'smaily-for-cf7' );
			$response['code']    = 404;
		}
		wp_send_json( $response );
	}

	/**
	 * Sanitize credentials
	 *
	 * @param string|null $subdomain Smaily API subdomain.
	 * @param string|null $username Smaily API username.
	 * @param string|null $password Smaily API password.
	 * @return array $cleaned Cleaned variables in an array.
	 */
	private function sanitize_credentials( $subdomain, $username, $password ) {
		$cleaned['subdomain'] = sanitize_text_field( wp_unslash( $subdomain ) );
		$cleaned['username']  = sanitize_text_field( wp_unslash( $username ) );
		$cleaned['password']  = sanitize_text_field( wp_unslash( $password ) );
		return $cleaned;
	}

	/**
	 * Normalize subdomain into the bare necessity.
	 *
	 * @param string $subdomain Messy subdomain.
	 *   http://demo.sendsmaily.net for example.
	 *
	 * @return string
	 *   demo from demo.sendsmaily.net
	 */
	private function normalize_subdomain( $subdomain ) {
		// First, try to parse as full URL.
		// If that fails, try to parse as subdomain.sendsmaily.net.
		// Last resort clean up subdomain and pass as is.
		if ( filter_var( $subdomain, FILTER_VALIDATE_URL ) ) {
			$url       = wp_parse_url( $subdomain );
			$parts     = explode( ' . ', $url['host'] );
			$subdomain = count( $parts ) >= 3 ? $parts[0] : '';
		} elseif ( preg_match( ' / ^ array( ^ \ . ) + \ . sendsmaily\ . net$ / ', $subdomain ) ) {
			$parts     = explode( ' . ', $subdomain );
			$subdomain = $parts[0];
		}
		$subdomain = preg_replace( ' / array( ^ a - zA - Z0 - 9 ) + / ', '', $subdomain );
		return $subdomain;
	}
}
