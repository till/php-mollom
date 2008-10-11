<?php

/**
 * Services_Mollom_Common
 */
require_once 'Services/Mollom/Common.php';

class Services_Mollom_Stats extends Services_Mollom_Common
{
    public function getTodayAccepted()
    {
        return $this->get('today_accepted');
    }

    public function getTodayRejected()
    {
        return $this->get('today_rejected');
    }
    
    public function getTotalAccepted()
    {
        return $this->get('total_accepted');
    }
    
    public function getTotalDays()
    {
        return $this->get('total_days');
    }
    
    public function getTotalRejected()
    {
        return $this->get('total_rejected');
    }
    
    public function getYesterdayAccepted()
    {
        return $this->get('yesterday_accepted');
    }
    
    public function getYesterdayRejected()
    {
        return $this->get('yesterday_rejected');
    }
    
    /**
	 * Retrieve statistics from Mollom
	 *
	 * Allowed types are listed below:
	 * - total_days				Number of days Mollom has been used
	 * - total_accepted			Total of blocked spam
	 * - total_rejected			Total accepted posts (not working?)
	 * - yesterday_accepted		Amount of spam blocked yesterday
	 * - yesterday_rejected		Number of posts accepted yesterday (not working?)
	 * - today_accepted			Amount of spam blocked today
	 * - today_rejected			Number of posts accepted today (not working?)
	 *
	 * @return int
	 * @throws Excpetion In case of unknown reply.
	 * 
	 * @param string $type The type of statistics to query for.
	 */
	protected function get($type)
	{        
		// do the call
		$responseString = $this->doCall('getStatistics', array('type' => $type));

		// validate
		if (!isset($responseString->params->param->value->int)) {
		    throw new Exception('Invalid response in getStatistics.');
        }

		// return
		return (int) $responseString->params->param->value->int;
	}
}
