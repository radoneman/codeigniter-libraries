<?php
(! defined('BASEPATH')) and exit('No direct script access allowed');

/*
 * Nexmo
 * Send text messages or voice messages using nexmo api
 *
 * @author radone@gmail.com
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
     *
     * @param array $params [
     *      from - A string giving your sender address. For example, from=MyCompany20
     *      to -  A single phone number
     *      text - The SMS body. Messages where type is text (the default)
     *              are in UTF-8 with URL encoding.
     * ]
     * @return boolean|array
     */
    public function send_sms($params)
    {
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
     * @param array $params [
     *      from - A voice-enabled virtual number associated with your Nexmo account
     *      to - The single phone number to call for each request
     *      text - A UTF-8 and URL encoded message that is sent to your user.
     *      repeat - How many time a message should be repeated
     * ]
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
     *
     * @param string $number
     * @return number
     *          (should be E.164 format)
     */
    protected function prepare_number($number)
    {
        $number = preg_replace('/\D+/i', '', $number);

        $country_calling_codes = [
            'US' => 1
        ];

        // add country calling codes
        if (strlen($number) <= 10) {
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