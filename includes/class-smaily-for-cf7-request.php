<?php
/**
 * The file that defines the request functions used by the plugin.
 *
 * @link       https://github.com/sendsmaily/smaily-cf7-plugin
 * @since      1.0.0
 *
 * @package    Smaily_For_CF7
 * @subpackage Smaily_For_CF7/includes
 * @author     Smaily
 */

/**
 * The request functions used by the plugin.
 *
 * @since      1.0.0
 * @package    Smaily_For_CF7
 * @subpackage Smaily_For_CF7/includes
 */
class Smaily_For_CF7_Request {

	/**
	 * The URL endpoint for the request.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $_url    The URL endpoint for the request.
	 */
	protected $_url = NULL;

	/**
	 * The data which is sent with the request.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $_data    The data which is sent with the request.
	 */
	protected $_data = array();

	/**
	 * The Smaily API username.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $_username    The Smaily API username.
	 */
	private $_username = NULL;

	/**
	 * The Smaily API password.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $_password    The Smaily API password.
	 */
	private $_password = NULL;

	/**
	 * Set the username & password for authentication.
	 *
	 * @param string $username Smaily API username.
	 * @param string $password Smaily API password.
	 */
	public function auth( $username, $password ) {
		$this->_username = $username;
		$this->_password = $password;
		return $this;
	}

	/**
	 * Set the URL endpoint for the request.
	 *
	 * @param string $url The URL endpoint.
	 */
	public function setUrl( $url ) {
		$this->_url = $url;
		return $this;
	}

	/**
	 * Set the data which is sent with the request.
	 *
	 * @param array $data The data which is sent with the request.
	 */
	public function setData( array $data ) {
		$this->_data = $data;
		return $this;
	}

	/**
	 * Execute get request.
	 */
	public function get() {
		$response = array();
		$args     = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->_username . ':' . $this->_password ),
			),
			'user-agent' => $this->get_version_info_for_useragent(),
		);
		$api_call = wp_remote_get( $this->_url, $args );
		// Response code from Smaily API.
		if ( is_wp_error( $api_call ) ) {
			$response = array( 'error' => $api_call->get_error_message() );
		}
		$response['body'] = json_decode( wp_remote_retrieve_body( $api_call ), true );
		$response['code'] = wp_remote_retrieve_response_code( $api_call );
		return $response;
	}

	/**
	 * Execute post request.
	 *
	 * @return array
	 */
	public function post() {
		$response   = array();
		$args       = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->_username . ':' . $this->_password ),
			),
			'user-agent' => $this->get_version_info_for_useragent(),
		);
		$body       = array( 'body' => $this->_data );
		$query_data = array_merge( $args, $body );
		$subscription_post = wp_remote_post( $this->_url, $query_data );
		// Response code from Smaily API.
		if ( is_wp_error( $subscription_post ) ) {
			$response = array( 'error' => $subscription_post->get_error_message() );
		} else {
			$response = json_decode( wp_remote_retrieve_body( $subscription_post ), true );
		}
		return $response;
	}

	/**
	 * Find WordPress, Contact Form and Smaily plugin version info and return as string for user-agent.
	 *
	 * @return string
	 */
	private function get_version_info_for_useragent() {
		$wp_useragent = 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' );
		$version_info = '; ContactForm7/' . WPCF7_VERSION . '; smaily-for-contact-form-7/' . SMAILY_FOR_CF7_VERSION;
		return $wp_useragent . $version_info;
	}
}
