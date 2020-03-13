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
		$this->smaily_for_cf7 = $plugin_name;
		$this->version        = $version;
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->smaily_for_cf7,
			plugins_url( 'js/smaily-for-cf7-admin.js', __FILE__ ),
			array( 'jquery', 'wpcf7-admin' ),
			$this->version,
			true,
		);
		wp_localize_script(
			$this->smaily_for_cf7,
			$this->smaily_for_cf7,
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
	 * @return void
	 */
	public function save( $args ) {

		if ( empty( $_POST ) ) {
			return;
		}

		// Validation and sanitization.
		$subdomain = isset( $_POST['smailyforcf7-subdomain'] ) ? trim( $_POST['smailyforcf7-subdomain'] ) : '';
		$username  = isset( $_POST['smailyforcf7-username'] ) ? trim( $_POST['smailyforcf7-username'] ) : '';
		$password  = isset( $_POST['smailyforcf7-password'] ) ? trim( $_POST['smailyforcf7-password'] ) : '';
		$subdomain = sanitize_text_field( wp_unslash( $subdomain ) );
		$subdomain = $this->normalize_subdomain( $subdomain );
		$username  = sanitize_text_field( wp_unslash( $username ) );
		$password  = sanitize_text_field( wp_unslash( $password ) );

		$autoresponder = isset( $_POST['smailyforcf7-autoresponder'] ) ? (int) $_POST['smailyforcf7-autoresponder'] : '';
		// Delete option here for clearing and unlinking credentials.
		if ( empty( $subdomain ) && empty( $username ) && empty( $password ) ) {
			delete_option( 'smailyforcf7_' . $args->id() );
			return;
		}
		$response = $this->verify_credentials( $subdomain, $username, $password );
		// Don't save invalid credentials.
		if ( 200 !== (int) $response['code'] ) {
			return;
		}

		$data_to_save = array(
			'api-credentials' => array(
				'subdomain' => $subdomain,
				'username'  => $username,
				'password'  => $password,
			),
			'autoresponder'   => $autoresponder,
		);
		update_option( 'smailyforcf7_' . $args->id(), $data_to_save );
	}

	/**
	 * Content of 'Smaily for Contact Form 7' tab
	 *
	 * @param WPCF7_ContactForm $args Contact Form 7 tab arguments.
	 */
	public function panel_content( $args ) {
		$form_id = WPCF7_ContactForm::get_current()->id();

		// Fetch saved Smaily CF7 option here to pass data along to view.
		$smailyforcf7_option   = get_option( 'smailyforcf7_' . $form_id, array() );
		$smaily_credentials    = isset( $smailyforcf7_option['api-credentials'] ) ? $smailyforcf7_option['api-credentials'] : array();
		$default_autoresponder = isset( $smailyforcf7_option['autoresponder'] ) ? $smailyforcf7_option['autoresponder'] : '';

		$subdomain = isset( $smaily_credentials['subdomain'] ) ? $smaily_credentials['subdomain'] : '';
		$username  = isset( $smaily_credentials['username'] ) ? $smaily_credentials['username'] : '';
		$password  = isset( $smaily_credentials['password'] ) ? $smaily_credentials['password'] : '';

		// Fetch autoresponder data here for view.
		$response       = $this->verify_credentials( $subdomain, $username, $password );
		$autoresponders = $response['autoresponders'];

		$autoresponder_list = array();
		if ( ! empty( $autoresponders ) ) {
			foreach ( $autoresponders as $autoresponder ) {
				if ( ! empty( $autoresponder['id'] ) && ! empty( $autoresponder['title'] ) ) {
					$autoresponder_list[ $autoresponder['id'] ] = trim( $autoresponder['title'] );
				}
			}
		}
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
				'title'    => __( 'Smaily for Contact Form 7', 'contact-form-7' ),
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
	public function verify_credentials( $subdomain, $username, $password ) {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-smaily-for-cf7-request.php';
		$request = ( new Smaily_For_CF7_Plugin_Request() )
					->auth( $username, $password )
					->setUrl( 'https://' . $subdomain . '.sendsmaily.net/api/workflows.php?trigger_type=form_submitted' )
					->get();
		if ( empty( $request ) ) {
			return;
		}
		$response['code']           = isset( $request['code'] ) ? $request['code'] : '';
		$response['autoresponders'] = isset( $request['body'] ) ? $request['body'] : '';
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
			wp_die( esc_html__( 'Your nonce did not verify!', 'wp_smailyforcf7' ) );
		}
		$form_id       = isset( $_POST['form_id'] ) ? (int) wp_unslash( $_POST['form_id'] ) : '';
		$subdomain     = isset( $_POST['subdomain'] ) ? trim( $_POST['subdomain'] ) : '';
		$username      = isset( $_POST['username'] ) ? trim( $_POST['username'] ) : '';
		$password      = isset( $_POST['password'] ) ? trim( $_POST['password'] ) : '';
		$autoresponder = isset( $_POST['autoresponder'] ) ? (int) $_POST['autoresponder'] : '';

		if ( empty( $subdomain ) || empty( $username ) || empty( $password ) ) {
			$response['message'] = esc_html__( 'Please fill out all fields!', 'wp_smailyforcf7' );
			$response['code']    = 204;
			wp_send_json( $response );
		}
		$subdomain = sanitize_text_field( wp_unslash( $subdomain ) );
		$subdomain = $this->normalize_subdomain( $subdomain );
		$username  = sanitize_text_field( wp_unslash( $username ) );
		$password  = sanitize_text_field( wp_unslash( $password ) );
		$response  = $this->verify_credentials( $subdomain, $username, $password );
		switch ( (int) $response['code'] ) {
			case 200:
				$data_to_save = array(
					'api-credentials' => array(
						'subdomain' => $subdomain,
						'username'  => $username,
						'password'  => $password,
					),
					'autoresponder'   => $autoresponder,
				);
				update_option( 'smailyforcf7_' . $form_id, $data_to_save );
				$response['message'] = esc_html__( 'Credentials valid', 'wp_smailyforcf7' );
				break;
			case 401:
				$response['message'] = esc_html__( 'Wrong credentials', 'wp_smailyforcf7' );
				break;
			case 404:
				$response['message'] = esc_html__( 'Error in subdomain', 'wp_smailyforcf7' );
				break;
			default:
				$response['message'] = esc_html__( 'Something went wrong', 'wp_smailyforcf7' );
				break;
		}
		wp_send_json( $response );
	}

	/**
	 * Remove saved Smaily API credentials and delete entry in wp_options database.
	 */
	public function remove_credentials_callback() {
		$form_id = isset( $_POST['form_id'] ) ? (int) wp_unslash( $_POST['form_id'] ) : '';
		if ( get_option( 'smailyforcf7_' . $form_id ) ) {
			delete_option( 'smailyforcf7_' . $form_id );
			wp_send_json( esc_html__( 'Credentials removed', 'wp_smailyforcf7' ) );
		}
		wp_send_json( esc_html__( 'No credentials to remove', 'wp_smailyforcf7' ) );
	}

	/**
	 * Replace Contact Form 7 default template with
	 * an example of a Smaily Newsletter form including all supported features.
	 *
	 * @param string $template Current Contact Form 7 template.
	 * @param string $prop tabs (e.g form, mail, mail_2, messages).
	 * @return string $template Smaily's replaced template.
	 */
	public function replace_template( $template, $prop ) {
		if ( 'form' === $prop ) {
			$template = (
			'
<label> Your Name
	[text smaily-name] </label>

<label> Your Email
	[email* smaily-email] </label>

[hidden smaily-wordpress "True"]
[checkbox smaily-language "Estonian" "English" "Russian"]
Consent to processing of personal data?
[radio smaily-gdpr default:1 "Yes" "No"]
[submit "Subscribe to newsletter"]
			'
			);
		}
		// Default to disable sending mail.
		if ( 'additional_settings' === $prop ) {
			$template = (
				'skip_mail: on'
			);
		}
		// Default template has ['your-email'] in email template.
		// This will cause configuration errors, even when mail is disabled.
		if ( 'mail' === $prop ) {
			$template = str_replace( '[your-email]', '[smaily-email]', $template );
		}
		return $template;
	}

	/**
	 * Don't send mail if current form has Smaily credentials.
	 *
	 * @param bool              $skip_mail Set as true for no mail sending.
	 * @param WPCF7_ContactForm $contact_form Submitted contact form.
	 * @return true
	 */
	public function disable_mail( $skip_mail, $contact_form ) {
		if ( get_option( 'smailyforcf7_' . $contact_form->id() ) ) {
			return true;
		}
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
	public function normalize_subdomain( $subdomain ) {
		// First, try to parse as full URL.
		// If that fails, try to parse as subdomain.sendsmaily.net.
		// Last resort clean up subdomain and pass as is.
		if ( filter_var( $subdomain, FILTER_VALIDATE_URL ) ) {
			$url       = wp_parse_url( $subdomain );
			$parts     = explode( '.', $url['host'] );
			$subdomain = count( $parts ) >= 3 ? $parts[0] : '';
		} elseif ( preg_match( '/^[^\.]+\.sendsmaily\.net$/', $subdomain ) ) {
			$parts     = explode( '.', $subdomain );
			$subdomain = $parts[0];
		}
		$subdomain = preg_replace( '/[^a-zA-Z0-9]+/', '', $subdomain );
		return $subdomain;
	}
}
