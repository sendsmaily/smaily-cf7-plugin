<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://smaily.com
 * @since      1.0.0
 *
 * @package    smaily_for_cf7
 * @subpackage smaily_for_cf7/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and public hooks.
 *
 * @package    smaily_for_cf7
 * @subpackage smaily_for_cf7/public
 * @author     Tom <tom@smaily.com>
 */
class Smaily_For_CF7_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $smaily_for_cf7    The ID of this plugin.
	 */
	private $smaily_for_cf7;

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
	 * @since    1.0.0
	 * @param    string $smaily_for_cf7  The name of the plugin.
	 * @param    string $version         The version of this plugin.
	 */
	public function __construct( $smaily_for_cf7, $version ) {

		$this->smaily_for_cf7 = $smaily_for_cf7;
		$this->version        = $version;

	}

	/**
	 * Search provided tags for Really Simple Captcha tags.
	 *
	 * Loops through all lists of tags until it finds 'basetype' key with value
	 *  'captchac' or 'captchar'. If found, sets a var true and evaluates both for response.
	 *
	 * @param array $form_tags All Contact Form 7 tags in current form.
	 * @return bool $simple_captcha_enabled
	 */
	public function search_for_cf7_captcha( $form_tags ) {
		// Check if Really Simple Captcha is actually enabled.
		if ( ! class_exists( 'ReallySimpleCaptcha' ) ) {
			return false;
		}
		$has_captcha_image = false;
		$has_captcha_input = false;
		foreach ( (array) $form_tags as $tag ) {
			foreach ( $tag as $key => $value ) {
				// Search for ["basetype"] => "captchac" in tags, return true if found.
				if ( 'basetype' === $key && 'captchac' === $value ) {
					$has_captcha_image = true;
					continue;
				}
				// Search for ["basetype"] => "captchar" in tags, return true if found.
				if ( 'basetype' === $key && 'captchar' === $value ) {
					$has_captcha_input = true;
					continue;
				}
			}
		}
		$simple_captcha_enabled = ( $has_captcha_image && $has_captcha_input ) ? true : false;
		return $simple_captcha_enabled;
	}

	/**
	 * Callback for wpcf7_submit hook
	 * Is activated on submitting the form.
	 *
	 * @param WPCF7_ContactForm $instance Current instance.
	 * @param array             $result Result of submit.
	 */
	public function submit( $instance, $result ) {
		// Enforcing recaptcha/captcha.
		$isset_recaptcha = isset( get_option( 'wpcf7' )['recaptcha'] );
		$form_tags       = WPCF7_FormTagsManager::get_instance()->get_scanned_tags();
		$isset_captcha   = $this->search_for_cf7_captcha( $form_tags );
		if ( ! $isset_captcha && ! $isset_recaptcha ) {
			$error_message = esc_html__( 'No CAPTCHA detected.
				Please use reCAPTCHA integration or add a Really Simple Captcha to this form',
				'wp_smailyforcf7',
			);
			$this->set_wpcf7_error( $error_message );
			return;
		}
		$submission_instance = WPCF7_Submission::get_instance();
		// Check if Contact Form 7 validation has passed.
		if ( $submission_instance->get_status() !== 'mail_sent' ) {
			return;
		}
		$posted_data         = $submission_instance->get_posted_data();
		$smailyforcf7_option = get_option( 'smailyforcf7_' . $instance->id() );
		// Don't continue if no posted data or no saved credentials.
		if ( empty( $posted_data ) || false === $smailyforcf7_option ) {
			return;
		}
		// Contact Form 7 doesn't output unclicked tags in $posted_data.
		$form_tags     = $instance->scan_form_tags();
		$possible_tags = $this->get_only_flattened_form_tags( $form_tags );
		$posted_tags   = $this->flatten_posted_tags( $posted_data );
		// Union merging possible & posted tags. Posted tags overwrite possible tags.
		$merged_tags = $posted_tags + $possible_tags;
		$merged_tags = $this->remove_smaily_prefix( $merged_tags );
		// To prevent having a duplicate field of lang_estonia and lang_естония.
		foreach ( $merged_tags as $tag => $value ) {
			$formatted_tags[ $this->format_field( $tag ) ] = $value;
		}
		$this->subscribe_post( $formatted_tags, $smailyforcf7_option );
	}

	/**
	 * Remove smaily prefix from tags.
	 *
	 * @param array $posted_data All posted fields (e.g smaily-email).
	 * @return array $smaily_fields Smaily fields (e.g email).
	 */
	public function remove_smaily_prefix( $posted_data ) {
		foreach ( $posted_data as $key => $value ) {
			// Explode limit at 2 to prevent smaily-lang-choice from returning lang.
			$exploded_tag = explode( '-', $key, 2 );
			// Verify customfield has 'smaily' prefix, e.g "smaily-email".
			if ( 'smaily' === $exploded_tag[0] ) {
				// Save without prefix, e.g email.
				$smaily_fields[ $exploded_tag[1] ] = $value;
			}
		}
		return $smaily_fields;
	}

	/**
	 * Flatten multiple value elements.
	 * If tags are in post data, they are selected. Therefore set them as true.
	 * Return single value elements as they were.
	 *
	 * @param array $posted_tags Tags which may contain multiple values.
	 * @return array $converted_tags Tags with no multiple values.
	 */
	public function flatten_posted_tags( $posted_tags ) {
		foreach ( $posted_tags as $tag_name => $tag_values ) {
			// If value is one-dimensional, don't alter it. Return it as it is.
			if ( ! is_array( $tag_values ) ) {
				$converted_tags[ $tag_name ] = $tag_values;
				continue;
			}
			foreach ( $tag_values as $tag_value ) {
				// Contact Form 7 only posts selected (true) values.
				$converted_tags[ $tag_name . '_' . strtolower( $tag_value ) ] = '1';
			}
		}
		return $converted_tags;
	}

	/**
	 * Flatten only multiple value tags.
	 * Don't know if clicked so set their value as 0.
	 *
	 * @param array $form_tags All forms tags in the current form.
	 * @return array $flattened_tags Flattened multiple value tags.
	 */
	public function get_only_flattened_form_tags( $form_tags ) {
		foreach ( $form_tags as $tag ) {
			// Only want tags with multiple values (radio, checkbox).
			if ( 2 > count( $tag['values'] ) ) {
				continue;
			}
			foreach ( $tag['values'] as $tag_value ) {
				$flattened_tags[ $tag['name'] . '_' . strtolower( $tag_value ) ] = '0';
			}
		}
		return $flattened_tags;
	}

	/**
	 * Transliterate string to Latin and format field.
	 *
	 * @param string $unformatted_field "Лanгuaгe_Vene mõös" for example.
	 * @return string $formatted_field language_venemoos
	 */
	public function format_field( $unformatted_field ) {
		$translit        = Transliterator::create( 'Any-Latin; Latin-ASCII' );
		$formatted_field = $translit->transliterate( $unformatted_field );
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
	 * @return void
	 */
	public function subscribe_post( $smaily_fields, $smailyforcf7_option ) {
		// If subdomain is empty, function can't send a valid post.
		$subdomain = isset( $smailyforcf7_option['api-credentials']['subdomain'] )
		? $smailyforcf7_option['api-credentials']['subdomain'] : '';
		if ( empty( $subdomain ) ) {
			return;
		}
		$autoresponder = isset( $smailyforcf7_option['autoresponder'] )
			? $smailyforcf7_option['autoresponder'] : '';
		$current_url   = $this->current_url();

		$array = array(
			'remote'        => 1,
			'success_url'   => $current_url,
			'failure_url'   => $current_url,
			'autoresponder' => $autoresponder,
		);
		$array = array_merge( $array, $smaily_fields );
		require_once plugin_dir_path( dirname( __FILE__ ) ) . '/includes/class-smaily-for-cf7-request.php';
		$url    = 'https://' . $subdomain . '.sendsmaily.net/api/opt-in/';
		$result = ( new Smaily_For_CF7_Plugin_Request() )
			->setUrl( $url )
			->setData( $array )
			->post();
		if ( empty( $result ) ) {
			$error_message = esc_html__( 'Something went wrong', 'wp_smailyforcf7' );
		} elseif ( 101 !== (int) $result['code'] ) {
			switch ( $result['code'] ) {
				case 201:
					$error_message = esc_html__( 'Form was not submitted using POST method.', 'wp_smailyforcf7' );
					break;
				case 204:
					$error_message = esc_html__( 'Input does not contain a valid email address.', 'wp_smailyforcf7' );
					break;
				case 205:
					$error_message = esc_html__( 'Could not add to subscriber list for an unknown reason.', 'wp_smailyforcf7' );
					break;
				default:
					$error_message = esc_html__( 'Something went wrong', 'wp_smailyforcf7' );
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
	public function current_url() {
		$current_url = null;
		if ( isset( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			$current_url  = is_ssl() ? 'https://' : 'http://';
			$current_url .= wp_unslash( $_SERVER['HTTP_HOST'] ); // phpcs:ignore
			$current_url .= wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore
		}
		return $current_url;
	}

	/**
	 * Function to set wpcf7 error message
	 *
	 * @param string $error_message The error message.
	 */
	public function set_wpcf7_error( $error_message ) {
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
