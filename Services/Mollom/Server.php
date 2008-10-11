<?php
/**
 * Services_Mollom_Common
 */
require_once 'Services/Mollom/Common.php';

class Services_Mollom_Server extends Services_Mollom_Common
{
    /**
	 * Obtains a list of valid servers
	 *
	 * @return array
	 */
	public function getList($counter = 0)
	{
		// do the call
		$responseString = $this->doCall(
            'getServerList',
            array(),
            $this->serverHost,
            $counter
        );

		// validate
		if (!isset($responseString->params->param->value->array->data->value)) {
		    throw new Exception('Invalid response in getServerList.');
        }

		// loop servers and add them
		foreach ($responseString->params->param->value->array->data->value as $server) {
		    $this->serverList[] = (string) $server->string;
        }

		if (count($this->serverList) == 0) {
		    $this->serverList = array('http://xmlrpc3.mollom.com', 'http://xmlrpc2.mollom.com', 'http://xmlrpc1.mollom.com');
		}

		// return
		return $this->serverList;
	}
}