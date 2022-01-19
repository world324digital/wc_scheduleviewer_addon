<?php
/*
 * Plugin Name: Scheduleviewer addon for Woocommerce
 * Plugin URI: https://completecare-transport.com/
 * Description: Scheduleviewer addon for Woocommerce
 * Author: Andrew Asikaev.
 * Author URI: https://completecare-transport.com
 * Version: 1.0.0
 *
 */

class WC_ScheduleViewer_Addon {

    /* 
    Bootstraps the class and hooks required actions & filters.
     *
     */
    public static $baseurl = "https://external.mediroutesapi.com";
    public static $api_username = "";
    public static $api_password = "";

    public static $version = "1";
    public static $access_token = "";
    public static $refresh_token = "";
    public static $token_type = "";
    public static $api_key = "";
    public static $funding_source_id = 0;
    public static $funding_source_name = "";
    public static $trip_model = "";
    public static $trip_data = null;
    public static $funding_source_data = null;
    public static $user_info = null;
    public static $webhook_model = null;
    public static $space_types = null;

    public static $pickup_complete_address = "";
    public static $dropoff_complete_address = "";
    public static $dropoff_date_time = "";
    public static $trip_type = 0;
    public static $space_type = "";
    public static $message = "";
    public static $status = false;

    public static function init() {
        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
        add_action( 'woocommerce_settings_tabs_swagger_api', __CLASS__ . '::settings_tab' );
        add_action( 'woocommerce_update_options_swagger_api', __CLASS__ . '::update_settings' );
        // add_action( 'woocommerce_checkout_process', __CLASS__ . '::requestAPI' );
        add_action( 'woocommerce_checkout_order_processed',  __CLASS__ . '::callScheduleAPI', 10, 3);
        // add_filter( 'woocommerce_checkout_fields' ,  __CLASS__ . '::custom_override_checkout_fields' );
        add_action( 'woocommerce_before_order_notes', __CLASS__ . '::action_woocommerce_before_order_notes', 10, 1 );
        add_action( 'wp_enqueue_scripts',  __CLASS__ . '::load_plugin_css' );
        add_action('woocommerce_checkout_update_order_meta', __CLASS__ . '::customise_checkout_field_update_order_meta');
    }
    
    
    /* Add a new settings tab to the WooCommerce settings tabs array.
     *
     * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
     * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including the Subscription tab.
     */
    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['swagger_api'] = __( 'Scheduleviewer Configuration', 'woocommerce-settings-swagger' );
        return $settings_tabs;
    }


    /* Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
     *
     * @uses woocommerce_admin_fields()
     * @uses self::get_settings()
     */
    public static function settings_tab() {
        woocommerce_admin_fields( self::get_settings() );
    }


    /* Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
     *
     * @uses woocommerce_update_options()
     * @uses self::get_settings()
     */
    public static function update_settings() {
        woocommerce_update_options( self::get_settings() );
    }


    /* Get all the settings for this plugin for @see woocommerce_admin_fields() function.
     *
     * @return array Array of settings for @see woocommerce_admin_fields() function.
     */
    public static function get_settings() {

        $settings = array(
            'section_title' => array(
                'name'     => __( 'Scheduleviewer API Configuration', 'woocommerce-settings-swagger' ),
                'type'     => 'title',
                'desc'     => '',
                'id'       => 'wc_swagger_api_section_title'
            ),
            'username' => array(
                'name' => __( 'Mediroutes Username', 'woocommerce-settings-swagger' ),
                'type' => 'text',
                'desc' => __( 'This is meditroutes username field.', 'woocommerce-settings-swagger' ),
                'id'   => 'wc_swagger_api_username'
            ),
            'password' => array(
                'name' => __( 'Mediroutes Password', 'woocommerce-settings-swagger' ),
                'type' => 'password',
                'desc' => __( 'This is meditroutes password field.', 'woocommerce-settings-swagger' ),
                'id'   => 'wc_swagger_api_password'
            ),
            'version' => array(
                'name' => __( 'Swagger Version', 'woocommerce-settings-swagger' ),
                'type' => 'text',
                'desc' => __( 'This is Scheduleviewer API version field.', 'woocommerce-settings-swagger' ),
                'id'   => 'wc_swagger_api_version'
            ),
            'section_end' => array(
                'type' => 'sectionend',
                'id' => 'wc_swagger_api_section_end'
            )
        );

        return apply_filters( 'wc_swagger_api_settings', $settings );
    }

    public static function load_plugin_css() {
        $plugin_url = plugin_dir_url( __FILE__ );

        wp_enqueue_style( 'checkout_style', $plugin_url . 'css/style.css' );
        wp_enqueue_script( 'checkout_script', $plugin_url . 'js/custom.js' );
    }


    function action_woocommerce_before_order_notes( $checkout ) {
        echo '<h3>' . __( 'Booking Details', 'woocommerce' ) . '</h3>';
        
        // Pickup address field
        woocommerce_form_field( 'pickup_complete_address', array(
            'placeholder'  => 'Pickup address',
            'label'        => __( 'Pickup address', 'woocommerce' ), 
            'type'         => 'text', 
            'required'     => 0,
            'class'        => array("form-row_wide", "address-field"),
            'autocomplete' => "pickup_complete_address"
        ), $checkout->get_value( 'pickup_complete_address' ) );

        // Dropoff Complete Address field
        woocommerce_form_field( 'dropoff_complete_address', array(
            'placeholder'  => 'Dropoff address',
            'label'        => __( 'Dropoff address', 'woocommerce' ), 
            'type'         => 'text', 
            'required'     => 0,
            'class'        => array("form-row_wide", "address-field"),
            'autocomplete' => "dropoff_complete_address"
        ), $checkout->get_value( 'dropoff_complete_address' ) );

        // Dropoff date time field
        woocommerce_form_field( 'dropoff_date_time', array(
            'placeholder'  => 'Dropoff date time',
            'label'        => __( 'Dropoff date time', 'woocommerce' ), 
            'type'         => 'datetime-local', 
            'required'     => 0,
            'class'        => array("form-row_wide"),
            'autocomplete' => "dropoff_date_time"
        ), $checkout->get_value( 'dropoff_date_time' ) );

        // Trip Type Field
        woocommerce_form_field( 'trip_type', array(
            'type'          => 'select',
            'label'         => 'Trip type',
            'required'      => 1,
            'input_class'   => array('trip_select'),
            'autocomplete'  => "trip_type",
            'options'       => array(
                                '1' => 'Will Call',
                                '2' => 'Appointment',
                                '3' => 'Return'
                            )
        ), $checkout->get_value( 'trip_type' ) );


        self::requestAPI();
        self::getToken();
        self::getAccessValues();
        self::getAllSpaceTypes();

        if (self::$space_types != null && count(self::$space_types) > 0){
            $space_types = array();

            foreach(self::$space_types as $type){
                $space_types[$type->space_type] = $type->space_type_description;
            }

            if (count($space_types) > 0) {
                // Trip Type Field
                woocommerce_form_field( 'space_type', array(
                    'type'          => 'select',
                    'label'         => 'Space type',
                    'required'      => 1,
                    'input_class'   => array('trip_select'),
                    'autocomplete'  => "space_type",
                    'options'       => $space_types
                ), $checkout->get_value( 'space_type' ) );
            }

        }

    }


    public static function customise_checkout_field_update_order_meta($order_id)
    {
        if (!empty($_POST['pickup_complete_address'])) {
            self::$pickup_complete_address = $_POST['pickup_complete_address'];
            update_post_meta($order_id, 'Pickup complete address', sanitize_text_field($_POST['pickup_complete_address']));
        }
        if (!empty($_POST['dropoff_complete_address'])) {
            self::$dropoff_complete_address = $_POST['dropoff_complete_address'];
            update_post_meta($order_id, 'Dropoff complete address', sanitize_text_field($_POST['dropoff_complete_address']));
        }
        if (!empty($_POST['dropoff_date_time'])) {
            self::$dropoff_date_time = $_POST['dropoff_date_time'];
            update_post_meta($order_id, 'Dropoff date time', sanitize_text_field($_POST['dropoff_date_time']));
        }
        if (!empty($_POST['trip_type'])) {
            self::$trip_type = intval($_POST['trip_type']);
            update_post_meta($order_id, 'Trip type', sanitize_text_field($_POST['trip_type']));
        }
        if (!empty($_POST['space_type'])) {
            self::$space_type = $_POST['space_type'];
            update_post_meta($order_id, 'Space type', sanitize_text_field($_POST['space_type']));
        }
    }

    public static function custom_override_checkout_fields( $fields ) {
        $fields['order']['pickup_complete_address'] = array(
            'placeholder'   => 'Pickup address',
            'label'         => 'Pickup address',
            'required'      => 0,
            'class'         => array(["form-row_wide", "address-field"]),
            'autocomplete'  => "pickup_complete_address"
        );

        // Dropoff Complete Address Field
        $fields['order']['dropoff_complete_address'] = array(
            'placeholder'   => 'Dropoff address',
            'label'         => 'Dropoff address',
            'required'      => 0,
            'class'         => array(["form-row_wide", "address-field"]),
            'autocomplete'  => "dropoff_complete_address"
        );

        // Trip Type Field
        $fields['order']['trip_type'] = array(
            'type'          => 'select',
            'label'         => 'Trip type',
            'required'      => 1,
            'input_class'   => array('trip_select'),
            'autocomplete'  => "trip_type",
            'options'       => array(
                                '1' => 'Will Call',
                                '2' => 'Appointment',
                                '3' => 'Return'
                            )
        );


        self::requestAPI();
        self::getToken();
        self::getAccessValues();
        self::getAllSpaceTypes();

        if (self::$space_types != null && count(self::$space_types) > 0){
            $space_types = array();

            foreach(self::$space_types as $type){
                $space_types[$type->space_type] = $type->space_type_description;
            }

            if (count($space_types) > 0) {
                // Trip Type Field
                $fields['order']['space_type'] = array(
                    'type'          => 'select',
                    'label'         => 'Space type',
                    'required'      => 1,
                    'input_class'         => array('trip_select'),
                    'autocomplete'  => "space_type",
                    'options'       => $space_types
                );
            }

        }

        return $fields;
    }

    public static function callScheduleAPI( $order_id, $posted_data, $order ) {
        // Do something

        self::requestAPI();
        self::getToken();
        self::getAccessValues();
        // self::getSingleTrip($order_id);
        self::postSingleTrip($order_id);
        // var_dump(self::$message);
        // die;
        if (!self::$status)
            throw new Exception(self::$message);
        // self::getTripCostBreakdown();
        // self::getFundingSource();
        // self::setFundingSource();
        // self::getUser();
        // self::setUser();
        // self::patchUser();
        // self::getWebhookTripModel();
        // self::createSubscription();
    }


    public static function requestAPI () {
        self::$api_username = get_option( 'wc_swagger_api_username', true );

        self::$api_password = get_option( 'wc_swagger_api_password', true );
        self::$version = get_option( 'wc_swagger_api_version', true );

        if (self::$version == "")
            self::$version = "1";

        if (self::$api_username == "" || self::$api_password == "")
            throw new Exception('Swagger Configuration is not set.');
    }

    public static function getToken(){
        $url = self::$baseurl.'/token';
        $auth = '';
        $post_data = 'grant_type=password&username='.urlencode(self::$api_username).'&password='.urlencode(self::$api_password);

        $json = self::curlRequest($url, 'POST', $auth, $post_data);

        if (isset($json)) {
            self::$access_token = $json->access_token;
            self::$refresh_token = $json->refresh_token;
            self::$token_type = $json->token_type;
        }
    }

    public static function getAccessValues(){
        $url = self::$baseurl.'/api/v'.self::$version.'/access';
        $auth = self::$token_type.' '.self::$access_token;

        $json = self::curlRequest($url, 'GET', $auth, null);

        if (isset($json)) {
            self::$api_key = $json[0]->APIKey;
            self::$funding_source_id = $json[0]->FundingSources[0]->FundingSourceId;
            self::$funding_source_name = $json[0]->FundingSources[0]->FundingSourceName;
        }
    }

    public static function getSingleTrip($order_id){

        $trip_id = $order_id;
        $url = self::$baseurl.'/api/v'.self::$version.'/singletrip?api_key='.self::$api_key.'&trip_id='.$trip_id;
        $auth = self::$token_type.' '.self::$access_token;

        $json = self::curlRequest($url, 'GET', $auth, null);

        if (isset($json)) {
            self::$trip_model = $json->Data;
        }
    }

    public static function getAllSpaceTypes(){
        $url = self::$baseurl.'/api/v'.self::$version.'/spacetype/getAllSpaceTypes?api_key='.self::$api_key.'&includeInactive=true';
        $auth = self::$token_type.' '.self::$access_token;

        $json = self::curlRequest($url, 'GET', $auth, null);

        if (isset($json)) {
            self::$space_types = $json;
        } else {
            self::$space_types = [];
        }
    }

    public static function getRiderById($rider_id){
        $url = self::$baseurl.'/api/v'.self::$version.'/riders/getByRiderId?api_key='.self::$api_key.'&rider_id='.$rider_id;
        $auth = self::$token_type.' '.self::$access_token;

        $json = self::curlRequest($url, 'GET', $auth, null);

        if (isset($json)) {
            return $json;
        } else {
            return null;
        }
    }

    public static function createRider($user_id, $order_id){
        $url = self::$baseurl.'/api/v'.self::$version.'/riders';
        $auth = self::$token_type.' '.self::$access_token;

        $user_info = get_userdata($user_id);

        $order = wc_get_order( $order_id );
        $order_data = $order->get_data();

        $address_1 = $order_data['billing']['address_1'];
        $address_2 = $order_data['billing']['address_2'];
        $city = $order_data['billing']['city'];
        $state = $order_data['billing']['state'];
        $postcode = $order_data['billing']['postcode'];
        $country = $order_data['billing']['country'];
        $phone = $order_data['billing']['phone'];

        $address = array(
            "location_name" => "",
            "address1" => $address_1,
            "address2" => $address_2,
            "city" => $city,
            "state" => $state,
            "zip" => $postcode,
            "longitude" => 0,
            "latitude" => 0
        );

        $rider = array(
            "tp_api_key" => self::$api_key,
            "rider_id" => "user".$user_id,
            "first_name" => $user_info->first_name,
            "last_name" => $user_info->last_name,
            "middle_name" => "",
            "address" => $address,
            "phone" => $phone,
            "funding_source_name" => self::$funding_source_name,
            "space_type" => self::$space_type,
            "home_phone" => "",
            "mobile_phone" => "",
            "icd_10_codes" => "",
            "date_of_birth" => "",
            "is_male" => true,
            "is_Female" => true,
            "comments" => "comments",
            "private_comments" => "",
            "email" => $user_info->user_email
        );

        $post_data = json_encode($rider);

        $json = self::curlRequest($url, 'POST', $auth, $post_data);

        if (isset($json)) {
            return $json;
        } else {
            return null;
        }
    }

    public static function postSingleTrip($order_id){
        $url = self::$baseurl.'/api/v'.self::$version.'/singletrip/withRider';
        $auth = self::$token_type.' '.self::$access_token;

        $order = wc_get_order( $order_id );
        $order_data = $order->get_data();
        $user_id   = $order->get_user_id();
        $user      = $order->get_user();

        $date_created = $order_data['date_created']->date('Y-m-d H:i:s');

        ## BILLING INFORMATION:

        $billing_first_name = $order_data['billing']['first_name'];
        $billing_last_name = $order_data['billing']['last_name'];
        $billing_address_1 = $order_data['billing']['address_1'];
        $billing_address_2 = $order_data['billing']['address_2'];
        $billing_city = $order_data['billing']['city'];
        $billing_state = $order_data['billing']['state'];
        $billing_postcode = $order_data['billing']['postcode'];
        $billing_country = $order_data['billing']['country'];
        $billing_email = $order_data['billing']['email'];
        $billing_phone = $order_data['billing']['phone'];

        $pickup = array(
            "event_name" => $billing_first_name." ".$billing_last_name." - Pickup Appointment",
            "event_comment" => "",
            "complete_address" => self::$pickup_complete_address,
            "event_location" => array(
                "location_name" => "",
                "address1" => "",
                "address2" => "",
                "city" => "",
                "state" => "",
                "zip" => "",
                "longitude" => 0,
                "latitude" => 0
            ),
            "appt_time" => "2022-01-14T19:44:12.446Z",
            "pickup_time" => $date_created,
            "phone_number" => ""
        );

        $dropoff = array(
            "event_name" => $billing_first_name." ".$billing_last_name." - Dropoff Appointment",
            "event_comment" => "",
            "complete_address" => self::$dropoff_complete_address,
            "event_location" => array(
                "location_name" => "",
                "address1" => "",
                "address2" => "",
                "city" => "",
                "state" => "",
                "zip" => "",
                "longitude" => 0,
                "latitude" => 0
            ),
            "appt_time" => "2022-01-14T19:44:12.446Z",
            "pickup_time" => self::$dropoff_date_time,
            "phone_number" => ""
        );

        $rider = self::getRiderById("user".$user_id);

        if ($rider != null) {
            $rider_id = $rider->rider_id;
        } else {
            $rider = self::createRider($user_id, $order_id);
            $rider_id = $rider->rider_id;
        }

        $trip_model = array(
            "tp_api_key" => self::$api_key,
            "trip_id" => "TR".$order_id,
            // "rider_id" => $rider_id,
            "rider" => $rider,
            "pickup" => $pickup,
            "dropoff" => $dropoff,
            "funding_source_id" => self::$funding_source_id,
            "funding_source_name" => self::$funding_source_name,
            "space_type" => self::$space_type,
            "billable_distance" => 0,
            "phone" => $billing_phone,
            "trip_type" => self::$trip_type,
            "additional_passengers" => [ array(
                "space_type" => "AMB",
                "count" => 0
            )],
            "caller" => "string",
            "total_trip_charge" => 0
        );

        $post_data = json_encode($trip_model);

        $json = self::curlRequest($url, 'POST', $auth, $post_data, "application/json");

        if (isset($json)) {
            self::$trip_data = $json;
            self::$status = true;
        }
    }

    public static function getTripCostBreakdown(){
        $url = self::$baseurl.'/api/v'.self::$version.'/rides/getTripCostBreakdown?trip_guid='.self::$trip_data->trip_guid.'&api_key='.self::$api_key;
        $auth = self::$token_type.' '.self::$access_token;

        $json = self::curlRequest($url, 'GET', $auth, null);

        if (isset($json)) {
            // self::$trip_data = $json;
        }
    }

    public static function getFundingSource(){
        $url = self::$baseurl.'/api/v'.self::$version.'/fundingsources/getFundingSourceById?api_key='.self::$api_key.'&funding_source_id='.self::$funding_source_id;
        $auth = self::$token_type.' '.self::$access_token;

        $json = self::curlRequest($url, 'GET', $auth, null);

        if (isset($json)) {
            self::$funding_source_data = $json->Data;
        }
    }

    public static function setFundingSource(){
        $model = array(
            'api_key' => self::$api_key,
            'funding_source' => self::$funding_source_data
        );

        $url = self::$baseurl.'/api/v'.self::$version.'/fundingsources';
        $auth = self::$token_type.' '.self::$access_token;
        $post_data = 'model='.json_encode($model);

        $json = self::curlRequest($url, 'POST', $auth, $post_data);

        if (isset($json)) {
        }
    }

    public static function getUser(){
        $url = self::$baseurl.'/api/v'.self::$version.'/users?api_key='.self::$api_key.'&user_name='.$username.'&includeInactiveUsers='.$include_inactive_users;
        $auth = self::$token_type.' '.self::$access_token;

        $json = self::curlRequest($url, 'GET', $auth, null);

        if (isset($json)) {
            self::$user_info = $json->Data;
        }
    }

    public static function setUser(){
        $model = array(
            'api_key' => self::$api_key,
            'user' => self::$user_info
        );

        $url = self::$baseurl.'/api/v'.self::$version.'/users';
        $auth = self::$token_type.' '.self::$access_token;
        $post_data = 'model='.json_encode($model);

        $json = self::curlRequest($url, 'POST', $auth, $post_data);

        if (isset($json)) {
        }
    }

    public static function patchUser(){
        $model = array(
            'api_key' => self::$api_key,
            'user' => self::$user_info
        );

        $url = self::$baseurl.'/api/v'.self::$version.'/users';
        $auth = self::$token_type.' '.self::$access_token;
        $post_data = 'model='.json_encode($model);

        $json = self::curlRequest($url, 'PATCH', $auth, $post_data);

        if (isset($json)) {
        }
    }

    public static function getWebhookTripModel() {
        $url = self::$baseurl.'/api/v'.self::$version.'/webhook?api_key'.self::$api_key;
        $auth = self::$token_type.' '.self::$access_token;

        $json = self::curlRequest($url, 'GET', $auth, null);

        if (isset($json)) {
            self::$webhook_model = $json;
        }
    }

    public static function createSubscription(){
        $url = self::$baseurl.'/api/v'.self::$version.'/webhook/newTrip';
        $auth = self::$token_type.' '.self::$access_token;
        $post_data = 'webhookNewTripModel='.json_encode(self::$webhook_model).'&api_key='.self::$api_key;

        $json = self::curlRequest($url, 'POST', $auth, $post_data);

        if (isset($json)) {
        }

    }

    public static function curlRequest($url, $method, $auth, $post_data = null, $content_type = null){
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_ENCODING, '');
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

        if ($method == "POST" && $post_data != null)
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);

        if ($auth != '') {
            
            if($content_type != null){
                $header = array(
                    'Authorization: '.$auth,
                    'Accept: application/json',
                    'Content-Type: '.$content_type
                );
            } else {
                $header = array(
                    'Authorization: '.$auth,
                    'Accept: application/json',
                    'Content-Length: 0'
                );
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }

        $response = curl_exec($curl);

        $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $log_data = array(
            "url" => $url,
            'post_data' => $post_data,
            'status_code' => $status_code,
            "response" => $response
        );

        self::saveLog($log_data);

        if ($status_code == 401 || $status_code == 403 || $status_code == 500 || $status_code == 400) {
            $json = json_decode($response);
            self::$message = $json->Message;
            // throw new Exception($json->Message);
        }

        if ($status_code == 200) {
            $json = json_decode($response);
            return $json;
        }
    }

    public static function saveLog($data){
        $date_time = date("Y-m-d H:i:s"); 
        $file = plugin_dir_path( __FILE__ ) . '/schedule_addon_log.txt'; 
        $log_file = fopen($file, "a");
        $txt = "";
        $index = 1;
        $count = count($data);
        foreach ($data as $key => $value) {
            $txt .= "[".$date_time."] ".$key." => ".$value;
            if ($index < $count)
                $txt .= "\n";
            $index++;
        }
        fwrite($log_file, "\n". $txt);
        fclose($log_file);
    }

}

WC_ScheduleViewer_Addon::init();
