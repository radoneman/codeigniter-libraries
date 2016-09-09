# Codeigniter Libraries

Some useful Codeigniter libraries

##1. Nexmo##
Send text or voice messages using Nexmo API.

Get phone type (mobile, landline, virtual)

Check is a phone number is from a mobile carier

###How To###
Set your Nexmo credentials in config/nexmo.php

```
$this->load->library(['service/sms/nexmo']);

// send_sms
$response = $this->nexmo->send_sms([
    'to' => '1234567890', // International or US phone number (10 digits)
    'text' => 'hello world'
]);
if ($response === false) {
    echo '<h1>Error</h1>';
    var_dump($this->nexmo->get_error());
} else {
    echo '<h1>Success</h1>';
    var_dump($response);
}
```

##2.CSV##
Export data as CSV with columns headers
 
Allows the export of large data sets by calling the methods:
 open, put (in a loop), close

