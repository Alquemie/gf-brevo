<?php
// don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Gravity Forms Brevo Add-On.
 *
 * @since     1.0.0
 * @package   Brevo
 * @author    Rocketgenius
 * @copyright Copyright (c) 2019, Rocketgenius
 */

// Include the Gravity Forms Feed Add-On Framework.
GFForms::include_feed_addon_framework();

/**
 * Initialize Brevo feeds and API.
 */
class GF_Brevo extends GFFeedAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  1.0
	 * @var    GF_Brevo $_instance If available, contains an instance of this class
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Gravity Forms Brevo Add-On.
	 *
	 * @since  1.0
	 * @var    string $_version Contains the version.
	 */
	protected $_version = GF_BREVO_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  1.0
	 * @var    string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = GF_BREVO_MIN_GF_VERSION;

	/**
	 * Defines the plugin slug.
	 *
	 * @since  1.0
	 * @var    string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gf-brevo';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  1.0
	 * @var    string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gf-brevo/brevo.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  1.0
	 * @var    string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this add-on can be found.
	 *
	 * @since  1.0
	 * @var    string The URL of the Add-On.
	 */
	protected $_url = 'https://alquemie.net';

	/**
	 * Defines the title of this add-on.
	 *
	 * @since  1.0
	 * @var    string $_title The title of the add-on.
	 */
	protected $_title = 'Gravity Forms Brevo Add-On';

	/**
	 * Defines the short title of the add-on.
	 *
	 * @since  1.0
	 * @var    string $_short_title The short title.
	 */
	protected $_short_title = 'Brevo';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    bool
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines the capabilities needed for the Brevo Add-On
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_brevo', 'gravityforms_brevo_uninstall' );

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_brevo';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_brevo';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_brevo_uninstall';

	/**
	 * Holds the object for the Brevo API helper.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    GF_Brevo_API $api The API instance for Brevo.
	 */
	protected $api = null;

	/**
	 * Enabling background feed processing to prevent performance issues delaying form submission completion.
	 *
	 * @since 1.3
	 *
	 * @var bool
	 */
	protected $_async_feed_processing = true;

	/**
	 * Returns an instance of this class, and stores it in the $_instance property.
	 *
	 * @since  1.0
	 *
	 * @return GF_Brevo $_instance An instance of the GF_Brevo class
	 */
	public static function get_instance() {

		if ( self::$_instance === null ) {
			self::$_instance = new GF_Brevo();
		}

		return self::$_instance;
	}

	/**
	 * Feed starting point.
	 *
	 * @since  1.0
	 * @access public
	 */
	public function init() {

		parent::init();

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Subscribe contact to Brevo only when payment is received.', 'gravityformsbrevo' ),
			)
		);
	}

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @since  1.0.0
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				/* Translators: %s is the website address of Brevo */
				'description' => '<p>' . esc_html__( 'Brevo is an affordable, easy-to-use marketing automation platform.', 'gravityformsbrevo' ) . ' ' . sprintf( esc_html__( 'Go to %s to sign up.', 'gravityformsbrevo' ), sprintf( '<a href="%s" target="_blank">%s</a>', 'https://brevo.com', esc_html__( 'Brevo.com', 'gravityformsbrevo' ) ) ) . '</p>',
				'fields'      => array(
					array(
						'name'              => 'api_key',
						'tooltip'           => esc_html__( 'Enter your Brevo API Key, which can be retrieved when you login to Brevo.com.', 'gravityformsbrevo' ),
						'label'             => esc_html__( 'Brevo API Key', 'gravityformsbrevo' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' ),
					),
				),
			),
		);
	}

	/**
	 * Saves the plugin settings if the submit button was pressed
	 *
	 * @since 1.0.0
	 */
	public function maybe_save_plugin_settings() {
		if ( isset( $_POST['_gaddon_setting_api_key'] ) ) {
			$_POST['_gaddon_setting_api_key'] = sanitize_text_field( $_POST['_gaddon_setting_api_key'] ); // remove space in front and end of string.
		}
		parent::maybe_save_plugin_settings();
	}

	/**
	 * Initializes the Brevo API if API credentials are valid.
	 *
	 * @since  1.0
	 *
	 * @return bool|null API initialization state. Returns null if no API key is provided.
	 */
	public function initialize_api() {

		// If the API is already initializes, return true.
		if ( ! is_null( $this->api ) ) {
			return true;
		}

		// Initialize Brevo API library.
		if ( ! class_exists( 'GF_Brevo_API' ) ) {
			require_once 'includes/class-gf-brevo-api.php';
		}

		// Get the API key.
		$api_key = $this->get_plugin_setting( 'api_key' );

		// If the API key is not set, return null.
		if ( rgblank( $api_key ) ) {
			return null;
		}

		// Initialize a new Brevo API instance.
		$Brevo = new GF_Brevo_API( $api_key );

		// Check API Key.
		$response = $Brevo->get_lists();

		if ( is_wp_error( $response ) ) {
			$this->log_debug( __METHOD__ . '(): Brevo API key could not be validated. ' . $response->get_error_message() );

			return false;
		}

		$this->log_debug( __METHOD__ . '(): Brevo API key is valid.' );
		$this->api = $Brevo;

		return true;
	}

	/**
	 * Return an array of Brevo list fields which can be mapped to the Form fields/entry meta.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array Field map or empty array on failure.
	 */
	public function merge_vars_field_map() {
		if ( ! $this->initialize_api() ) {
			$this->log_debug( __METHOD__ . '(): Brevo API could not be initialized.' );
			return array();
		}

		// Initialize field map array.
		$field_map = array(
			'EmailAddress' => array(
				'name'       => 'EmailAddress',
				'label'      => esc_html__( 'Email Address', 'gravityformsbrevo' ),
				'required'   => true,
				'field_type' => array( 'email', 'hidden' ),
			),
		);

		// Get current list ID.
		$list_id = $this->get_setting( 'brevolist' );
		if ( empty( $list_id ) ) {
			return array();
		}

		// Get API List.
		/*
		$lists = $this->api->get_lists();
		if ( is_wp_error( $lists ) ) {
			$this->log_debug( __METHOD__ . '(): Could not retrieve Brevo lists from API. ' . $lists->get_error_message() );
			return array();
		}
		$selected_list = false;
		foreach ( $lists['lists'] as $list ) {
			if ( $list_id === $list['id'] ) {
				$selected_list = $list;
				break;
			}
		}

		if ( empty( $selected_list ) ) {
			$this->log_error( __METHOD__ . "(): Selected list ({$list_id}) not found." );
			return array();
		}

		$selected_list_fields = $selected_list['fields'];

		foreach ( $selected_list_fields as $field ) {
			*/
		$attributes = $this->api->get_attributes();
		foreach ( $attributes as $field ) {

			// Define required field type.
			$field_type = null;

			/*
			if ( 'EmailAddress' === $field['tag'] ) {
				$field_type = array( 'email', 'hidden' );
			}
			*/

			if ($field->category == 'normal') {
				if ( 'float' === $field->type ) {
					$field_type = array( 'number' );
				}

				if ( 'date' === $field->type ) {
					$field_type = array( 'date' );
				}

				$field_map[ $field->name ] = array(
					'name'       => $field->name,
				  'label'      => $field->name,
					'required'   => false,
					'field_type' => $field_type,
				);
			}
			
		}

		return $field_map;
	}

	/**
	 * Form settings page title
	 *
	 * @since 1.0.0
	 * @return string Form Settings Title
	 */
	public function feed_settings_title() {
		return esc_html__( 'Feed Settings', 'gravityformsbrevo' );
	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 2.5
	 *
	 * @return string
	 */
	public function get_menu_icon() {

		return file_get_contents( $this->get_base_path() . '/images/menu-icon.svg' );

	}

	/**
	 * Enable feed duplication.
	 *
	 * @since  1.0.0
	 *
	 * @param int $id Feed ID requesting duplication.
	 *
	 * @return bool
	 */
	public function can_duplicate_feed( $id ) {
		return true;
	}

	/**
	 * Feed settings
	 *
	 * @since 1.0.0
	 * @return array feed settings
	 */
	public function feed_settings_fields() {

		return array(
			array(
				'fields' => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Name', 'gravityformsbrevo' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
						'tooltip'  => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Name', 'gravityformsbrevo' ),
							esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformsbrevo' )
						),
					),
					array(
						'name'       => 'brevolist',
						'label'      => esc_html__( 'Brevo List', 'gravityformsbrevo' ),
						'type'       => 'select',
						'choices'    => $this->get_Brevo_lists(),
						'required'   => true,
						'no_choices' => esc_html__( 'Please create a Brevo list to continue setup.', 'gravityformsbrevo' ),
						'tooltip'    => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Brevo List', 'gravityformsbrevo' ),
							esc_html__( 'Select the Brevo list you would like to add your contacts to.', 'gravityformsbrevo' )
						),
						'onchange'   => 'jQuery(this).parents("form").submit();',
					),
				),
			),
			array(
				// 'dependency' => 'brevolist',
				'fields'     => array(
					array(
						'name'      => 'mappedFields',
						'label'     => esc_html__( 'Map Fields', 'gravityformsbrevo' ),
						'type'      => 'field_map',
						'field_map' => $this->merge_vars_field_map(),
						'tooltip'   => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Map Fields', 'gravityformsbrevo' ),
							esc_html__( 'Associate your Brevo fields to the appropriate Gravity Form fields by selecting the appropriate form field from the list.', 'gravityformsbrevo' )
						),
					),
					array(
						'name'    => 'optinCondition',
						'label'   => esc_html__( 'Conditional Logic', 'gravityformsBrevo' ),
						'type'    => 'feed_condition',
						'tooltip' => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Conditional Logic', 'gravityformsBrevo' ),
							esc_html__( 'When conditional logic is enabled, form submissions will only be exported to Brevo when the conditions are met. When disabled all form submissions will be exported.', 'gravityformsbrevo' )
						),
					),
					array( 'type' => 'save' ),
				),
			),
		);
	}

	/**
	 * Determines if a user has double-opt-in available.
	 *
	 * @since  1.0.0
	 *
	 * @param string $list_id The list ID to retrieve double-opt-in status for.
	 *
	 * @return bool true if double-opt-in enabled, false if not
	 */
	private function is_double_opt_in( $list_id ) {
		$found                      = true;
		$Brevo_double_opt_in = GFCache::get( 'Brevo_double_opt_in_' . $list_id, $found );
		if ( $found ) {
			return filter_var( $Brevo_double_opt_in, FILTER_VALIDATE_BOOLEAN );
		}
		if ( ! $this->initialize_api() ) {
			$this->log_debug( __METHOD__ . '(): Brevo API could not be initialized.' );
			return false;
		}

		$list = $this->api->get_list( $list_id );
		if ( is_wp_error( $list ) ) {
			$this->log_debug( __METHOD__ . '(): Could not retrieve Brevo list from API. ' . $lists->get_error_message() );
			return false;
		}
		$double_opt_in = filter_var( $list['double_opt_in'], FILTER_VALIDATE_BOOLEAN );
		GFCache::set( 'Brevo_double_opt_in_' . $list_id, $double_opt_in, true, DAY_IN_SECONDS );
		return $double_opt_in;
	}

	/**
	 * Process the feed, subscribe the user to the list.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param array $feed  The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form  The form object currently being processed.
	 */
	public function process_feed( $feed, $entry, $form ) {

		// Get field map values.
		$field_map = $this->get_field_map_fields( $feed, 'mappedFields' );

		// Get mapped email address.
		$email = $this->get_field_value( $form, $entry, $field_map['EmailAddress'] );

		// If email address is invalid, log error and return.
		if ( GFCommon::is_invalid_or_empty_email( $email ) ) {
			$this->add_feed_error( esc_html__( 'A valid Email address must be provided.', 'gravityformsbrevo' ), $feed, $entry, $form );
			return;
		}

		// Initialize array to store merge vars.
		$merge_vars = array();

		// Loop through field map.
		foreach ( $field_map as $name => $field_id ) {

			// If no field is mapped, skip it.
			if ( rgblank( $field_id ) ) {
				continue;
			}

			if ( 'EmailAddress' === $name ) {
				continue;
			}

			// Set merge var name to current field map name.
			$this->merge_var_name = $name;

			// Get field object.
			$field = GFFormsModel::get_field( $form, $field_id );

			// Get field value.
			$field_value = $this->get_field_value( $form, $entry, $field_id );

			if ( empty( $field_value ) ) {
				continue;
			}

			$merge_vars[ $name ] = $field_value;
		}

		// Make API call with feed and merge tag data.
		if ( ! $this->initialize_api() ) {
			$this->add_feed_error( esc_html__( 'Unable to connect to the Brevo API', 'gravityformsbrevo' ), $feed, $entry, $form );
			return;
		}
		$list_id  = rgars( $feed, 'meta/brevolist' );
		$response = $this->api->create_contact( $list_id, $email, $merge_vars );

		// Check response.
		if ( is_wp_error( $response ) ) {
			$this->add_feed_error( esc_html__( 'Unable to add subscriber to Brevo: ', 'gravityformsbrevo' ) . $response->get_error_message(), $feed, $entry, $form );
		} else {
			if ( $this->is_double_opt_in( $list_id ) ) {
				$this->add_note( $entry['id'], __( 'The user email has been sent a notification to subscribe.', 'gravityformsbrevo' ), 'success' );
			} else {
				$this->add_note( $entry['id'], __( 'The user email has been added to your Brevo list.', 'gravityformsbrevo' ), 'success' );
			}
		}
	}

	/**
	 * Return list options when creating a field.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array List choices. Empty array on failure.
	 */
	public function get_Brevo_lists() {
		if ( ! $this->initialize_api() ) {
			$this->log_debug( __METHOD__ . '(): Brevo API could not be initialized.' );
			return array();
		}
		// Get API List.
		$lists = $this->api->get_lists();
		if ( is_wp_error( $lists ) ) {
			$this->log_debug( __METHOD__ . '(): Could not retrieve Brevo lists from API. ' . $lists->get_error_message() );
			return array();
		}
		if ( empty( $lists ) || ( isset( $lists['data'] ) && empty( $lists['data'] ) ) ) {
			$this->log_debug( __METHOD__ . '(): Could not retrieve any Brevo lists.' );
			return array();
		}

		if ( ! empty( $lists ) ) {
			// Initialize select options.
			$options = array(
				array(
					'label' => esc_html__( 'Select an Brevo List', 'gravityformsbrevo' ),
					'value' => '',
				),
			);

			// Loop through Brevo lists.
			foreach ( $lists['lists'] as $list ) {

				// Add list to select options.
				$options[] = array(
					'label' => esc_html( $list['name'] ),
					'value' => esc_attr( $list['id'] ),
				);
			}

			return $options;
		}
		return array();
	}

	/**
	 * Prevent feeds being listed or created if the API key isn't valid.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return bool
	 */
	public function can_create_feed() {
		return $this->initialize_api();
	}

	/**
	 * Returns the value to be displayed in the Brevo List column.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string Brevo List Name. Empty string on failure.
	 */
	public function get_column_value_Brevo_list_name( $feed ) {
		if ( ! $this->initialize_api() ) {
			$this->log_debug( __METHOD__ . '(): Brevo API could not be initialized.' );
			return '';
		}

		// Get API List.
		$list_id = isset( $feed['meta']['brevolist'] ) ? $feed['meta']['brevolist'] : '';
		if ( empty( $list_id ) ) {
			$this->log_debug( __METHOD__ . '(): The Brevo list is not set in the feed settings.' );
			return;
		}

		// Get API List.
		$list = $this->api->get_list( $list_id );

		if ( is_wp_error( $list ) ) {
			$this->log_debug( __METHOD__ . '(): Could not retrieve Brevo list name from API. ' . $list->get_error_message() );
			return '';
		}
		if ( empty( $list ) ) {
			$this->log_debug( __METHOD__ . '(): Could not retrieve the Brevo list.' );
			return '';
		}

		if ( isset( $list['name'] ) ) {
			return $list['name'];
		};

		$this->log_debug( __METHOD__ . '(): Could not match any Brevo list with feed settings.' );
		return '';
	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName'               => esc_html__( 'Name', 'gravityformsbrevo' ),
			'Brevo_list_name' => esc_html__( 'Brevo List', 'gravityformsbrevo' ),
		);
	}
}
