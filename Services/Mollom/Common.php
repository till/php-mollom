<?php
/**
 * 
 */
class Services_Mollom_Common
{
    /**
     * cURL resource
     * 
     * @var resource
     * @see self::getClient()
     */
    protected $curl;

    /**
     * buildRequest XML.
     * 
     * @return string
     * 
     * @param string $method     Name of the method.
     * @param mixed  $parameters Parameters, mixed.
     * 
     * @uses self::buildValue()
     * @todo Use SimpleXML?
     */
    protected function buildRequest($method, $parameters)
    {
		$requestBody  = '<?xml version="1.0"?>' ."\n";
		$requestBody .= '<methodCall>' ."\n";
		$requestBody .= '	<methodName>mollom.'. $method .'</methodName>' ."\n";
		$requestBody .= '	<params>' ."\n";
		$requestBody .= '		<param>'."\n";
		$requestBody .= '			'. $this->buildValue($parameters) ."\n";
		$requestBody .= '		</param>'."\n";
		$requestBody .= '	</params>' ."\n";
		$requestBody .= '</methodCall>' ."\n";
        
        return $requestBody;
    }

	/**
	 * Build the value so we can use it in XML-RPC requests
	 *
	 * @return string
	 * 
	 * @param mixed $value
	 */
	protected function buildValue($value)
	{
		// get type
		$type = gettype($value);

		// build value
		switch ($type) {
		case 'string':
			// escape it, cause Mollom can't handle CDATA (no pun intended)
			$value = htmlspecialchars($value, ENT_QUOTES, 'ISO-8859-15');
			return '<value><string>'. $value .'</string></value>'."\n";

		case 'array':
			// init struct
			$struct = '<value>'."\n";
			$struct .= '	<struct>'."\n";

			// loop array
			foreach ($value as $key => $value) {

			    $str  = '<member>'. "\n";
                $str .= '<name>' . $key . '</name>' . $this->buildValue($value);
                $str .= '</member>';
                
			    $struct .= str_replace(
                    "\n",
                    '',
                    $str
                ) . "\n";
            }

			$struct .= '	</struct>'."\n";
			$struct .= '</value>'."\n";

			// return
			return $struct;

		default:
			return '<value>'. $value .'</value>'."\n";
		}
	}
    
    /**
     * Get cURL resource.
     * 
     * @return resource
     * 
     * @param  string $requestBody The request body.
     * 
     * @uses self::$curl
     * @see  self::doCall()
     * @todo Implement {@link self::setClient()}
     */
    protected function getClient($server, $requestBody)
    {
        if (is_resource($this->curl)) {
            return $this->curl;
        }

		// create curl
		$this->curl = @curl_init();

		// set useragent
		@curl_setopt($this->curl, CURLOPT_USERAGENT, $this->userAgent);
		
		// set options
		@curl_setopt($this->curl, CURLOPT_HTTP_VERSION, $this->httpVersion);
		@curl_setopt($this->curl, CURLOPT_POST, true);
		@curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
		@curl_setopt($this->curl, CURLOPT_HEADER, true);
		@curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		@curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $this->timeout);
		@curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeout);
		
		// set url
		@curl_setopt($this->curl, CURLOPT_URL, $server .'/'. $this->version);
		
		// set body
		@curl_setopt($this->curl, CURLOPT_POSTFIELDS, $requestBody);
        
        return $this->curl;
    }

    /**
     * Attempt to parse the error returned from Mollom's API, and throw an exception.
     * 
     * If there is no error, we just skip back to the previous.
     * 
     * @return void
     * 
     * @param SimpleXml $responseXml SimpleXML'd response
     * 
     * @todo Move errors into class constants.
     */
    protected function parseError($responseXml)
    {
		if (!isset($responseXML->fault)) {
		    return;
        }
		$code = (isset($responseXML->fault->value->struct->member[0]->value->int)) ? (int) $responseXML->fault->value->struct->member[0]->value->int : 'unknown';
		$message = (isset($responseXML->fault->value->struct->member[1]->value->string)) ? (string) $responseXML->fault->value->struct->member[1]->value->string : 'unknown';

		// handle errors
		switch ($code) {
		// code 1000 (Parse error or internal problem)
		case 1000:
			throw new Exception('[error '.$code .'] '. $message, $code);

		// code 1100 (Serverlist outdated)
		case 1100:
			throw new Exception('[error '.$code .'] '. $message, $code);

		// code 1200 (Server too busy)
		case 1200:
			if ($this->serverList === null) {
			    $this->getServerList();
            }

			// do call again
			return $this->doCall(
                $method,
                $parameters,
                $this->serverList[$counter],
                $counter++
            );
		break;

		default:
			throw new Exception('[error '.$code .'] '. $message, $code);
		}
    }
    
    /**
     * Parse the spam response.
     * 
     * @return string
     * 
     * @param  string $value An ID string, which we turn into something 'readable'.
     */
    protected function parseSpam($value)
    {
		switch($value) {
		case '0':
			return 'unknown';

		case '1':
			return 'ham';
		    break;

		case '2':
			return 'spam';
			break;

		case '3':
			return 'unsure';
    		break;
	    }
    }

    /**
     * Doublecheck if the method is valid.
     * 
     * @return boolean
     * 
     * @param string $method The name of the XMLRPC method.
     */
    protected function validateMethod($method)
    {
        // possible methods
		static $aPossibleMethods = array(
            'checkCaptcha',
            'checkContent',
            'getAudioCaptcha',
            'getImageCaptcha',
            'getServerList',
            'getStatistics',
            'sendFeedback',
            'verifyKey'
        ); // FIXME: This should be static indeed!

		// check if method is valid
		if (!in_array($method, $aPossibleMethods)) {
		    throw new Exception('Invalid method. Only '. implode(', ', $aPossibleMethods) .' are possible methods.');
        }
        return true;
    }
    
	/**
	 * Make the call
	 *
	 * @return	SimpleXMLElement
	 * @param	string $method
	 * @param	array[optional] $parameters
	 */
	protected function doCall(
        $method,
        $parameters = array(),
        $server = null,
        $counter = 0)
	{
		// count available servers
		$countServerList = count($self->serverList);

		if ($server === null && $countServerList == 0) {
		    throw new Exception('No servers found, populate the serverlist. See setServerList().');
        }

		// redefine var
		$method     = (string) $method;
		$parameters = (array) $parameters;

        $this->validateMethod($method);

		// still null
		if ($server === null) {
		    $server = $this->serverList[$counter];
        }
        
		// cleanup server string
        // FIXME: do once, not on each doCall()
		$server = str_replace(array('http://', 'https://'), '', $server);

		// create timestamp
		$time = gmdate("Y-m-d\TH:i:s.\\0\\0\\0O", time());

		// create nonce
		$nonce = md5(time());

		// create has
		$hash = base64_encode(
			pack("H*", sha1((str_pad($this->privateKey, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) .
			pack("H*", sha1((str_pad($this->privateKey, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))) .
			$time . ':'. $nonce .':'. $this->privateKey))))
		);

		// add parameters
		$parameters['public_key'] = $this->publicKey;
		$parameters['time']       = $time;
		$parameters['hash']       = $hash;
		$parameters['nonce']      = $nonce;

		// build request
        $requestBody = $this->buildRequest($method, $parameters);

        $curl = $this->createClient($server, $requestBody);
		
		// get response
		$response = @curl_exec($curl);
		
		// get errors
		$errorNumber = (int) @curl_errno($curl);
		$errorString = @curl_error($curl);

        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		// close
		@curl_close($curl);
		
		// validate response
		if ($response === false || $errorNumber != 0) {			
			// increment counter
			$counter++;
			
			// no servers left
			if ($errorNumber == 28 && !isset($this->serverList[$counter]) && $countServerList != 0) {
			    throw new Exception(
                    'No more servers available, try to increase the timeout.',
                    $errorNumber
                );
            }
			if($errorNumber == 28 && isset($this->serverList[$counter])) { // timeout
			    return $this->doCall(
                    $method,
                    $parameters,
                    $this->serverList[$counter],
                    $counter);
			} else { // other error
			    throw new Exception(
                    'Something went wrong. Maybe the following message can be handy.<br />'. $errorString,
                    $errorNumber
                );
            }
		} 

		// process response
		$parts = explode("\r\n\r\n", $response);

		// validate
		if (!isset($parts[0]) || !isset($parts[1])) {
		    throw new Exception('Invalid response in doCall.');
        }

		// get headers
		$headers = $parts[0];

		// rebuild body
		array_shift($parts);
		$body = implode('', $parts);

        // validate response header
        if ($responseCode != 200) { // FIXME: maybe a var
            throw new Exception("Invalid status: {$responseCode}");
        }

		// do some validation
        // FIXME: Don't silence the notice. :-)
		$responseXML = @simplexml_load_string($body);
		if ($responseXML === false) {
		    throw new Exception('Invalid body.');
        }

        // validate
        $this->parseError($responseXML);

		// return
		return $responseXML;
	}
    
	/**
	 * Get the real IP-address
	 *
	 * @return mixed
	 */
	public function getIpAddress()
	{
		// pre check
		if (!isset($_SERVER['REMOTE_ADDR'])) {
		    return null;
		}
        
		// get ip
		$ipAddress = $_SERVER['REMOTE_ADDR'];
		
		if ($this->reverseProxy) {
			if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))	{
				if(
                    !empty(self::$allowedReverseProxyAddresses)
                    && in_array($ipAddress, self::$allowedProxyAddresses, true)
                ) {
					return array_pop(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
				}
   			}

   			// running in a cluster environment
			if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
			    return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
            }
		}
		
		// fallback
		return $ipAddress;
	}

	/**
	 * Verifies your key
	 *
	 * Returns information about the status of your key. Mollom will respond with a boolean value (true/false).
	 * False means that your keys is disabled or doesn't exists. True means the key is enabled and working properly.
	 *
	 * @return	bool
	 */
	public function verifyKey()
	{
		// do the call
		$responseString = $this->doCall('verifyKey');

		// validate
		if (!isset($responseString->params->param->value->boolean)) {
		    throw new Exception('Invalid response in verifyKey.');
        }

		// return
		if ((string) $responseString->params->param->value->boolean == '1') {
		    return true;
        }

		// fallback
		return false;
	}
}