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
		$smaily_fields        = array();
		foreach ( $instance->scan_form_tags() as &$tag ) {
			$is_allowed_type = in_array( $tag->basetype, $disallowed_tag_types, true ) === false;
			$skip_smaily     = strtolower( $tag->get_option( 'skip_smaily', '', true ) ) === 'on';
			if ( ! $is_allowed_type || $skip_smaily ) {
				continue;
			}

			$is_email_field = ( 'email' === $tag->basetype );
			$is_posted_tag  = array_key_exists( $tag->name, $posted_data );
			// Replace email's name variations with just 'email'.
			// email-149 wont work. API needs pure 'email' field.
			if ( $is_email_field && $is_posted_tag ) {
				$posted_data['email'] = $posted_data[ $tag->name ];
				unset( $posted_data[ $tag->name ] );
				$tag->name = 'email';
			}
			$smaily_fields[ $tag->name ] = $tag;
		}

		// Contact Form 7 doesn't output unclicked fields in $posted_data.
		$possible_fields = $this->flatten_form_tags( $smaily_fields );
		$posted_fields   = $this->flatten_posted_fields( $posted_data );
		// Isolate elements of posted fields that are also in possible fields.
		// Replace possible fields' values with posted fields.
		$merged_fields = array_merge( $possible_fields, array_intersect_key( $posted_fields, $possible_fields ) );

		// To prevent having a duplicate field of lang_estonia and lang_естония.
		$formatted_fields = array();
		foreach ( $merged_fields as $key => $value ) {
			$formatted_fields[ $this->format_field( $key ) ] = $value;
		}
		$this->subscribe_post( $formatted_fields, $smailyforcf7_option );
	}

	/**
	 * Flatten posted fields.
	 * If non-empty fields are in post data, they are selected. Therefore set them as true.
	 * Return single value elements as they were.
	 * Return multiple value elements with key_value => 1
	 *
	 * @param array $posted_fields Fields which may contain multiple values.
	 * @return array $converted_fields Fields with no multiple values.
	 */
	private function flatten_posted_fields( $posted_fields ) {
		$converted_fields = array();
		foreach ( $posted_fields as $field_name => $field_values ) {
			// If value is one-dimensional, don't alter it. Return it as it is.
			if ( ! is_array( $field_values ) ) {
				$converted_fields[ $field_name ] = $field_values;
				continue;
			}
			foreach ( $field_values as $field_value ) {
				if ( empty( $field_value ) ) {
					continue;
				}
				// Contact Form 7 only posts selected (true) values.
				$converted_fields[ $field_name . '_' . strtolower( $field_value ) ] = '1';
			}
		}
		return $converted_fields;
	}

	/**
	 * Reduce WPCF7_FormTag object to single array with tags and their default or 0 values.
	 *
	 * Single value tags return [name => 0]
	 * Multiple value tags (checkbox, select menu, radio) return [name_value => 0]
	 *
	 * @param array $form_tags All forms tags in the current form.
	 * @return array $flattened_tags Flattened multiple value tags.
	 */
	private function flatten_form_tags( $form_tags ) {
		$flattened_tags = array();
		foreach ( $form_tags as $tag ) {
			$name   = $tag['name'];
			$values = $tag['values'];

			$has_multiple_options   = $tag->has_option( 'multiple' );
			$is_drop_down_menu      = 'select' === $tag->basetype && ! $has_multiple_options;
			$is_single_option_radio = 'radio' === $tag->basetype && count( $values ) === 1;

			// Drop down menu posts a single value, select menu posts an array.
			// If only one option prefer format 'radio = yes' over 'radio_yes = 1'.
			if ( $is_drop_down_menu || $is_single_option_radio ) {
				$flattened_tags[ $name ] = $values[0];
				continue;
			}

			if ( ! empty( $values ) ) {
				foreach ( $values as $tag_value ) {
					$flattened_tags[ $name . '_' . strtolower( $tag_value ) ] = '0';
				}
				continue;
			}
			$flattened_tags[ $name ] = '0';
		}
		return $flattened_tags;
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
			$error_message = esc_html__( 'Something went wrong', 'smaily-for-cf7' );
		} elseif ( 101 !== (int) $result['code'] ) {
			switch ( $result['code'] ) {
				case 201:
					$error_message = esc_html__( 'Form was not submitted using POST method.', 'smaily-for-cf7' );
					break;
				case 204:
					$error_message = esc_html__( 'Input does not contain a valid email address.', 'smaily-for-cf7' );
					break;
				case 205:
					$error_message = esc_html__( 'Could not add to subscriber list for an unknown reason.', 'smaily-for-cf7' );
					break;
				default:
					$error_message = esc_html__( 'Something went wrong', 'smaily-for-cf7' );
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
