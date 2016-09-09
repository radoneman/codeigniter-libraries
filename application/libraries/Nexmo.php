<?php
(! defined('BASEPATH')) and exit('No direct script access allowed');

/*
 * Nexmo
 * Send text messages or voice messages using nexmo api
 *
 * @author radone@gmail.com, 2016
 */
class Nexmo
{

    /**
     *
     * @var CI
     */
    protected $CI;

    /**
     *
     * @var string
     */
    protected $api_key;

    /**
     *
     * @var string
     */
    protected $api_secret;

    /**
     * For some accounts you can send SMS only from a $nexmo_virtual_number
     * Set this param into your configuration file
     *
     * @var string
     */
    protected $nexmo_virtual_number;

    /**
     *
     * @var string
     */
    protected $api_url = '';

    /**
     *
     * @var string
     */
    protected $error = '';

    /**
     *
     * @var string
     */
    protected $response_type = 'json';

    /**
     *
     * @var string
     */
    protected $last_response = '';

    /**
     *
     * @throws Exception
     */
    public function __construct()
    {
        $this->CI = & get_instance();
        $this->CI->load->config('nexmo');

        if (! $this->CI->config->item('api_key')) {
            throw new Exception('nexmo: invalid api_key');
        }

        if (! $this->CI->config->item('api_secret')) {
            throw new Exception('nexmo: invalid api_secret');
        }

        $this->api_key = $this->CI->config->item('api_key');
        $this->api_secret = $this->CI->config->item('api_secret');
        $this->nexmo_virtual_number = $this->CI->config->item('nexmo_virtual_number');
    }

    /**
     *
     * @return mixed|boolean
     */
    public function get_balance()
    {
        $this->api_url = 'https://rest.nexmo.com/';
        return $this->get('account', 'get-balance');
    }

    /**
     *
     * @param string $number
     * @return boolean|array
     */
    public function get_phone_type($number)
    {
        $number = $this->prepare_number($number);

        $this->api_url = 'https://api.nexmo.com/';
        $response = $this->get('ni/standard', 'json', [
            'number' => $number
        ]);

        // allow code 44 (partial response) to go thru
        if (isset($response['status']) && ($response['status'] == 0 || $response['status'] == 44)) {
            return $response;
        } else {
            $this->error = json_encode($response);
            return false;
        }
    }

    /**
     * Check if a number is from a mobile carrier
     * Nexmo network_type is mobile or virtual
     *
     * @param string $number
     * @return boolean
     */
    public function is_mobile_phone($number)
    {
        $phone_type = $this->get_phone_type($number);
        if ($phone_type === false) {
            return false;
        }

        // match network type looking at current and original carrier
        $network_type = null;
        if (isset($phone_type['current_carrier']['network_type'])) {
            $network_type = $phone_type['current_carrier']['network_type'];
        } elseif (isset($phone_type['original_carrier']['network_type'])) {
            $network_type = $phone_type['original_carrier']['network_type'];
        }

        if (empty($network_type) ||  ! isset($phone_type['international_format_number'])) {
            echo $this->error = json_encode('Error: Could not get phone type');
            return false;
        }

        // network_type = mobile, landline, virtual (Skype, Google), premium, toll-free
        if (in_array($network_type, ['mobile', 'virtual'])) {
            return true;
        }

        return false;
    }

    /**
     * Check is phone number has at least 10 digits (US number)
     *
     * @param string $number
     * @return boolean
     */
    public function is_valid_phone($number)
    {
        $number = $this->prepare_number($number);
        return (strlen($number) >= 10);
    }

    /**
     *
     * @param array $params
     *            [
     *            to - A single phone number
     *            text - The SMS body. Messages where type is text (the default)
     *            are in UTF-8 with URL encoding.
     *            from - (optional, if nexmo_virtual_number is set into config file)
     *                A Nexmo virtual number or a string for sender name, e.g from=MyCompany20
     *               (the string does not work for every Nexmo account)
     *            ]
     * @return boolean|array
     */
    public function send_sms($params)
    {
        if (empty($params['from']) && ! empty($this->nexmo_virtual_number)) {
            $params['from'] = $this->nexmo_virtual_number;
        }

        $params['to'] = $this->prepare_number($params['to']);

        $this->api_url = 'https://rest.nexmo.com/';
        $response = $this->get('sms', 'json', [
            'from' => $params['from'],
            'to' => $params['to'],
            'text' => $params['text'],
            'type' => 'text'
        ]);

        if (isset($response['messages'][0]['status']) && $response['messages'][0]['status'] == 0) {
            return $response;
        } else {
            $this->error = json_encode($response);
            return false;
        }
    }

    /**
     *
     * @param array $params
     *            [
     *            from - A voice-enabled virtual number associated with your Nexmo account
     *            to - The single phone number to call for each request
     *            text - A UTF-8 and URL encoded message that is sent to your user.
     *            repeat - How many time a message should be repeated
     *            ]
     * @return boolean|array
     */
    public function send_text_to_speech($params)
    {
        $params['from'] = $this->prepare_number($params['from']);
        $params['to'] = $this->prepare_number($params['to']);

        $this->api_url = 'https://api.nexmo.com/';
        $response = $this->get('tts', 'json', [
            'from' => $params['from'],
            'to' => $params['to'],
            'text' => $params['text'],
            'repeat' => $params['repeat']
        ]);

        if (isset($response['status']) && $response['status'] == 0) {
            return $response;
        } else {
            $this->error = json_encode($response);
            return false;
        }
    }

    /**
     *
     * @param string $value
     *            Type of response object, json or xml
     */
    public function set_response_type($value)
    {
        if ($value == 'json' || $value == 'xml') {
            $this->response_type = $value;
        }
    }

    /**
     *
     * @param unknown $endpoint
     * @param unknown $cmd
     * @param array $data
     * @return mixed|boolean
     */
    protected function get($endpoint, $cmd, array $data = [])
    {
        return $this->exec($endpoint, $cmd, 'get', $data);
    }

    /**
     *
     * @param unknown $enpoint
     * @param unknown $cmd
     * @param array $data
     */
    protected function post($enpoint, $cmd, array $data = [])
    {
        return $this->exec($enpoint, $cmd, 'post', $data);
    }

    /**
     *
     * @param string $endpoint
     * @param string $cmd
     * @param string $method
     * @param array $data
     * @return mixed array|false
     */
    protected function exec($endpoint, $cmd, $method = 'get', array $data = [])
    {
        $this->error = '';

        $ch = curl_init();

        $data['api_key'] = $this->api_key;
        $data['api_secret'] = $this->api_secret;

        $url = $this->api_url . $endpoint . '/' . $cmd;

        if ($method == 'get') {
            $url .= '?' . http_build_query($data);
        }

        $options = array(
            // CURLOPT_HTTPHEADER => '',
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        );

        if ($method == 'post') {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);

        $this->last_response = $response;

        if ($errno = curl_errno($ch)) {
            $error_message = curl_error($ch);
            $this->error = 'Error: ' . $errno . ': ' . $error_message;
            return false;
        }

        if (! $response) {
            $this->error = ('Error: Invalid response from API');
        }

        curl_close($ch);

        if ($this->response_type == 'json') {
            $response = json_decode($response, true);
        }

        if ($response === null) {
            $this->error = 'Error: Could not parse response from API';
            return false;
        }

        return $response;
    }

    /**
     * Force to US international phone number (+1 prefix) if the number has only 10 digits
     * (modify this to suit your needs if sending outside US)
     *
     * @param string $number
     * @return number (should be E.164 format)
     */
    protected function prepare_number($number)
    {
        $number = preg_replace('/\D+/i', '', $number);

        $country_calling_codes = [
            'US' => 1
        ];

        // add country calling codes
        if (strlen($number) >= 1 && strlen($number) <= 10) {
            if ($number[0] != $country_calling_codes['US']) {
                $number = $country_calling_codes['US'] . $number;
            }
        }

        return $number;
    }

    /**
     *
     * @return string
     */
    public function get_error()
    {
        return $this->error;
    }

    /**
     *
     * @return string
     */
    public function last_response()
    {
        return $this->last_response;
    }
}