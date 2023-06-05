<?php
// don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

require_once(__DIR__ . '/lib/autoload.php');

/**
 * Gravity Forms Brevo Add-On.
 *
 * @since     1.0.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2019, Rocketgenius
 */

/**
 * Helper class for retrieving the Brevo API validation.
 */
class GF_Brevo_API {

	/**
	 * Brevo API URL.
	 *
	 * @since  1.0
	 * @var    string $api_url Brevo API URL.
	 */
	protected $api_url = 'https://emailoctopus.com/api/1.5/';

	/**
	 * Brevo API key.
	 *
	 * @since  1.0
	 * @var    string $api_key Brevo API Key.
	 */
	protected $api_key = null;

	/**
	 * Initialize API library.
	 *
	 * @since  1.0
	 *
	 * @param  string $api_key Brevo API key.
	 */
	public function __construct( $api_key = null ) {
		$this->api_key = $api_key;
	}

	/**
	 * Make API request.
	 *
	 * @since  1.0
	 *
	 * @param string    $path          Request path.
	 * @param array     $options       Request options.
	 * @param string    $method        Request method. Defaults to GET.
	 * @param string    $return_key    Array key from response to return. Defaults to null (return full response).
	 * @param int|array $response_code Expected HTTP response code.
	 *
	 * @return array|WP_Error
	 */
	private function make_request( $path = '', $options = array(), $method = 'GET', $return_key = null, $response_code = 200 ) {

		// Log API call succeed.
		gf_brevo()->log_debug( __METHOD__ . '(): Making request to: ' . $path );

		// Get API Key.
		$api_key = $this->api_key;

		$args = array( 'method' => $method );

		if ( 'GET' === $method ) {
			$request_url = add_query_arg(
				array(
					'api_key' => $api_key,
					'limit'   => 100,
					'page'    => 1,
				),
				$this->api_url . $path
			);
		}

		if ( 'POST' === $method ) {
			$args['body']            = $options['body'];
			$request_url             = $this->api_url . $path;
			$args['body']['api_key'] = $api_key;
		}

		/**
		 * Filters if SSL verification should occur.
		 *
		 * @since 1.0
		 * @since 1.3 Added the $request_url param.
		 *
		 * @param bool   $local_ssl_verify If the SSL certificate should be verified. Defaults to false.
		 * @param string $request_url      The request URL.
		 *
		 * @return bool
		 */
		$args['sslverify'] = apply_filters( 'https_local_ssl_verify', false, $request_url );

		/**
		 * Sets the HTTP timeout, in seconds, for the request.
		 *
		 * @since 1.0
		 * @since 1.3 Added the $request_url param.
		 *
		 * @param int    $request_timeout The timeout limit, in seconds. Defaults to 30.
		 * @param string $request_url     The request URL.
		 *
		 * @return int
		 */
		$args['timeout'] = apply_filters( 'http_request_timeout', 30, $request_url );

		// Execute request.
		$response = wp_remote_request(
			$request_url,
			$args
		);

		$credentials = SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $api_key);
		$apiInstance = new SendinBlue\Client\Api\ContactsApi(
				new GuzzleHttp\Client(),
				$credentials
		);

		$createContact = new \SendinBlue\Client\Model\CreateContact([
				'email' => 'new-contact@example.com',
				'updateEnabled' => true,
				'attributes' => [[ 'FIRSTNAME' => 'Max ', 'LASTNAME' => 'Mustermann', 'isVIP'=> 'true' ]],     
				'listIds' =>[[1,12]]
		]);

		try {
				$response = $apiInstance->createContact($createContact);
				// print_r($result);
		} catch (Exception $e) {
			return new WP_Error( 'brevo_invalid_call', esc_html__( 'The API path supplied is not supported by the EmailOctopus API.', 'gravityformsemailoctopus' ), array() );
			// echo 'Exception when calling ContactsApi->createContact: ', $e->getMessage(), PHP_EOL;
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response ) ) {
			return new WP_Error( 'brevo_invalid_call', esc_html__( 'The API path supplied is not supported by the EmailOctopus API.', 'gravityformsemailoctopus' ), array() );
		}
		// If an incorrect response code was returned, return WP_Error.
		$response_body = gf_brevo()->maybe_decode_json( wp_remote_retrieve_body( $response ) );
		if ( isset( $response_body['0'] ) ) {
			$retrieved_response_code = $response_body['0'];
		} else {
			$retrieved_response_code = $response['response']['code'];
		}

		if ( $retrieved_response_code !== $response_code ) {
			$error_message = "Expected response code: {$response_code}. Returned response code: {$retrieved_response_code}.";

			$error_data = array( 'status' => $retrieved_response_code );
			if ( ! rgempty( 'code', $response_body ) ) {
				$error_message = $response_body['code'];
			}
			$error_data['data'] = '';
			if ( rgars( $response_body, 'error/message' ) ) {
				$error_data['data'] = rgars( $response_body, 'error/message' );
			}
			gf_emailoctopus()->log_error( __METHOD__ . '(): Unable to validate with the EmailOctopus API: ' . $error_message . ' ' . $error_data['data'] );

			return new WP_Error( 'emailoctopus_api_error', $error_data['data'] );
		}

		return $response_body;
	}

	/**
	 * Gets a list from the Brevo API
	 *
	 * @since  1.0.0
	 *
	 * @param string $list_id The Brevo list ID to check.
	 *
	 * @return array|WP_Error List content if successful
	 */
	public function get_list( $list_id ) {
		// Log API call succeed.
		gf_brevo()->log_debug( __METHOD__ . '(): Making "Get List" request for: ' . $list_id);

		// return $this->make_request( 'lists/' . $list_id );
		$config = \SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->api_key);

		$apiInstance = new \SendinBlue\Client\Api\ContactsApi(
				new \GuzzleHttp\Client(),
				$config
		);
	
		try {
				$result = $apiInstance->getList($list_id);
				return $result;
		} catch (Exception $e) {
			return new WP_Error( 'brevo_api_error', $e->getMessage() );
				// echo 'Exception when calling ContactsApi->getList: ', $e->getMessage(), PHP_EOL;
		}
	}


	/**
	 * Gets lists from the Brevo API
	 *
	 * @since  1.0.0
	 *
	 * @return array|WP_Error List content if successful
	 */
	public function get_lists() {
		gf_brevo()->log_debug( __METHOD__ . '(): Making "Get Lists" request');
		// return $this->make_request( 'lists' );
		$config = \SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->api_key);

		$apiInstance = new \SendinBlue\Client\Api\ContactsApi(
				new \GuzzleHttp\Client(),
				$config
		);
		$limit = 50;
		$offset = 0;

		try {
				$result = $apiInstance->getLists($limit, $offset);
				return $result;
		} catch (Exception $e) {
			return new WP_Error( 'brevo_api_error', $e->getMessage() );
			// echo 'Exception when calling ContactsApi->getFolderLists: ', $e->getMessage(), PHP_EOL;
		}
	}

	/**
	 * Gets attributes from the Brevo API
	 *
	 * @since  1.0.0
	 *
	 * @return array|WP_Error List content if successful
	 */
	public function get_attributes() {
		gf_brevo()->log_debug( __METHOD__ . '(): Making "Get Attributes" request');
		// return $this->make_request( 'lists' );
		$config = \SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->api_key);

		$apiInstance = new \SendinBlue\Client\Api\ContactsApi(
				new \GuzzleHttp\Client(),
				$config
		);
		
		try {
			$result = json_decode($apiInstance->getAttributes());
			$attributes = $result->attributes;
			return $attributes;
		} catch (Exception $e) {
			return new WP_Error( 'brevo_api_error', $e->getMessage() );
				//echo 'Exception when calling AttributesApi->getAttributes: ', $e->getMessage(), PHP_EOL;
		}
	}

	/**
	 * Sends subscription data to Brevo.
	 *
	 * @since 1.0.0
	 *
	 * @param string $list_id        Brevo List ID.
	 * @param string $email_address The Email address to add as a contact.
	 * @param array  $merge_vars     Brevo merge variables.
	 *
	 * @return array|WP_Error Brevo Subscription Response.
	 */
	public function create_contact( $list_id, $email_address, $merge_vars ) {
		$list_id = (int)$list_id;
		$merge_vars = (object)$merge_vars;

		gf_brevo()->log_debug( __METHOD__ . '(): Making "Create Contact" request for:' . md5($email_address . 'gf-brevo'));
		gf_brevo()->log_debug( __METHOD__ . '(): ' . print_r($merge_vars, true));
		
		$list_id = (int)$list_id;
		$merge_vars = (object)$merge_vars;

		$config = \SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->api_key);

		$apiInstance = new \SendinBlue\Client\Api\ContactsApi(
				new \GuzzleHttp\Client(),
				$config
		);
		
		$createContact = new \SendinBlue\Client\Model\CreateContact([
			'email' => $email_address,
			'updateEnabled' => true,
			// 'attributes' => [[ 'FIRSTNAME' => 'Max ', 'LASTNAME' => 'Mustermann', 'isVIP'=> 'true' ]],   
			'attributes' => $merge_vars,  
    	'listIds' =>[$list_id]
		]);
		
		try {
				$result = $apiInstance->createContact($createContact);
				return $result;
		} catch (Exception $e) {
				// print_r($e);
				// echo 'Exception when calling ContactsApi->createContact: ', $e->getMessage(), PHP_EOL;
				return new WP_Error( 'brevo_api_error', $e->getMessage() );
		}
		/*
		return $this->make_request(
			sprintf( 'lists/%s/contacts', $list_id ),
			array(
				'body' => array(
					'fields'        => $merge_vars,
					'email_address' => $email_address,
				),
			),
			'POST'
		);*/

	}
}
