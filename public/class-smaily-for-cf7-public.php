<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/sendsmaily/smaily-cf7-plugin
 * @since      1.0.0
 *
 * @package    Smaily_For_CF7
 * @subpackage Smaily_For_CF7/public
 * @author     Smaily
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and public hooks.
 *
 * @package    Smaily_For_CF7
 * @subpackage Smaily_For_CF7/public
 */
class Smaily_For_CF7_Public {

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
	 * The transliterator instance.
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      Transliterator    $transliterator    The transliterator instance.
	 */
	private $transliterator;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string $plugin_name  The name of the plugin.
	 * @param    string $version         The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name    = $plugin_name;
		$this->version        = $version;
		if ( ! class_exists( 'Transliterator' ) ) {
			wp_die( esc_html__( 'Smaily for CF7 requires Transliterator extension. Please install php-intl package and try again.' ) );
		}
		$this->transliterator = Transliterator::create( 'Any-Latin; Latin-ASCII' );
	}

	/**
	 * Callback for wpcf7_submit hook
	 * Is activated on submitting the form.
	 *
	 * @param WPCF7_ContactForm $instance Current instance.
	 * @param array             $result Result of submit.
	 */
	public function submit( $instance, $result ) {
		// Check if Contact Form 7 validation has passed.
		$submission_instance = WPCF7_Submission::get_instance();
		if ( $submission_instance->get_status() !== 'mail_sent' ) {
			return;
		}

		// Don't continue if no posted data or no saved credentials.
		$posted_data         = $submission_instance->get_posted_data();
		$smailyforcf7_option = get_option( 'smailyforcf7_form_' . $instance->id() );
		if ( empty( $posted_data ) || false === $smailyforcf7_option ) {
			return;
		}

		$disallowed_tag_types = array( 'submit' );
		$payload              = array();
		foreach ( $instance->scan_form_tags() as $tag ) {
			$is_allowed_type = in_array( $tag->basetype, $disallowed_tag_types, true ) === false;
			$skip_smaily     = strtolower( $tag->get_option( 'skip_smaily', '', true ) ) === 'on';
			if ( ! $is_allowed_type || $skip_smaily ) {
				continue;
			}

			$posted_value = isset( $posted_data[ $tag->name ] ) ? $posted_data[ $tag->name ] : null;

			$is_single_option_menu  = $tag->basetype === 'select' && ! $tag->has_option( 'multiple' );
			$is_single_option_radio = $tag->basetype === 'radio' && count( $tag->values ) === 1;

			// Email field should always be named email.
			if ( $tag->basetype === 'email' ) {
				$payload['email'] = ! is_null( $posted_value ) ? $posted_value : '';
			}
			// Single option dropdown menu and radio button can only have one value.
			elseif ( $is_single_option_radio || $is_single_option_menu ) {
				$payload[ $this->format_field( $tag->name ) ] = $tag->values[0];
			}
			// Tags with multiple options need to have default values, because browsers do not send values of unchecked inputs.
			elseif ( $tag->basetype === 'select' || $tag->basetype === 'radio' || $tag->basetype === 'checkbox' ) {
				foreach ( $tag->values as $value ) {
					$is_selected = is_array( $posted_value ) ? in_array( $value, $posted_value, true ) : false;
					$payload[ $this->format_field( $tag->name . '_' . $value ) ] = $is_selected ? '1' : '0';
				}
			}
			// Pass rest of the tag values as is.
			else {
				$payload[ $this->format_field( $tag->name ) ] = ! is_null( $posted_value ) ? $posted_value : '';
			}
		}

		$this->subscribe_post( $payload, $smailyforcf7_option );
	}

	/**
	 * Transliterate string to Latin and format field.
	 *
	 * @param string $unformatted_field "Лanгuaгe_Vene mõös" for example.
	 * @return string $formatted_field language_venemoos
	 */
	private function format_field( $unformatted_field ) {
		$formatted_field = $this->transliterator->transliterate( $unformatted_field );
		$formatted_field = trim( $formatted_field );
		$formatted_field = strtolower( $formatted_field );
		$formatted_field = str_replace( array( '-', ' ' ), '_', $formatted_field );
		$formatted_field = str_replace( array( 'ä', 'ö', 'ü', 'õ' ), array( 'a', 'o', 'u', 'o' ), $formatted_field );
		$formatted_field = preg_replace( '/([^a-z0-9_]+)/', '', $formatted_field );
		return $formatted_field;
	}

	/**
	 * Subscribe customer to Smaily newsletter
	 *
	 * @param array $smaily_fields Fields to be sent to Smaily.
	 * @param array $smailyforcf7_option Smaily credentials and autoresponder data.
	 */
	private function subscribe_post( $smaily_fields, $smailyforcf7_option ) {
		// If subdomain is empty, function can't send a valid post.
		$subdomain = isset( $smailyforcf7_option['api-credentials']['subdomain'] )
			? $smailyforcf7_option['api-credentials']['subdomain'] : '';
		$username  = isset( $smailyforcf7_option['api-credentials']['username'] )
			? $smailyforcf7_option['api-credentials']['username'] : '';
		$password  = isset( $smailyforcf7_option['api-credentials']['password'] )
			? $smailyforcf7_option['api-credentials']['password'] : '';

		if ( empty( $subdomain ) || empty( $username ) || empty( $password ) ) {
			return;
		}

		$autoresponder = isset( $smailyforcf7_option['autoresponder'] )
			? $smailyforcf7_option['autoresponder'] : '';
		$current_url   = $this->current_url();

		$array = array(
			'autoresponder' => $autoresponder,
			'addresses'     => array( $smaily_fields ),
		);
		require_once plugin_dir_path( dirname( __FILE__ ) ) . '/includes/class-smaily-for-cf7-request.php';
		$url = 'https://' . $subdomain . '.sendsmaily.net/api/autoresponder.php';

		$result = ( new Smaily_For_CF7_Request() )
			->setUrl( $url )
			->auth( $username, $password )
			->setData( $array )
			->post();
		if ( empty( $result ) ) {
			$error_message = esc_html__( 'Something went wrong', 'smaily-for-contact-form-7' );
		} elseif ( 101 !== (int) $result['code'] ) {
			switch ( $result['code'] ) {
				case 201:
					$error_message = esc_html__( 'Form was not submitted using POST method.', 'smaily-for-contact-form-7' );
					break;
				case 204:
					$error_message = esc_html__( 'Input does not contain a valid email address.', 'smaily-for-contact-form-7' );
					break;
				case 205:
					$error_message = esc_html__( 'Could not add to subscriber list for an unknown reason.', 'smaily-for-contact-form-7' );
					break;
				default:
					$error_message = esc_html__( 'Something went wrong', 'smaily-for-contact-form-7' );
					break;
			}
		}
		// If error_message set, continue to replace Contact Form 7's response with Smaily's.
		if ( isset( $error_message ) ) {
			$this->set_wpcf7_error( $error_message );
		}
	}

	/**
	 * Returns current URL
	 */
	private function current_url() {
		$current_url = get_site_url( null, wp_unslash( $_SERVER['REQUEST_URI'] ) );
		return $current_url;
	}

	/**
	 * Function to set wpcf7 error message
	 *
	 * @param string $error_message The error message.
	 */
	private function set_wpcf7_error( $error_message ) {
		add_filter(
			'wpcf7_ajax_json_echo',
			function ( $response ) use ( $error_message ) {
				$response['status']  = 'validation_error';
				$response['message'] = $error_message;
				return $response;
			}
		);
	}

}
