<?php

/**
 * Services_Mollom_Common
 */
require_once 'Services/Mollom/Common.php';

class Services_Mollom_Content extends Services_Mollom_Common
{
    /**
	 * Check if the data is spam or not, and gets an assessment of the datas quality
	 *
	 * This function will be used most. The more data you submit the more accurate
	 * the claasification will be.
	 * 
	 * If the spamstatus is 'unsure', you could send the user an extra check
	 * (eg. a captcha).
	 *
	 * REMARK: the Mollom-sessionid is NOT related to HTTP-session, so don't send
	 * 'session_id'.
	 *
	 * The function will return an array with 3 elements:
	 * - spam			the spam-status (unknown/ham/spam/unsure)
	 * - quality		an assessment of the content's quality (between 0 and 1)
	 * - session_id		Molloms session_id
	 *
	 * @return	array
	 * 
	 * @param string[optional] $sessionId
	 * @param string[optional] $postTitle
	 * @param string[optional] $postBody
	 * @param string[optional] $authorName
	 * @param string[optional] $authorUrl
	 * @param string[optional] $authorEmail
	 * @param string[optional] $authorOpenId
	 * @param string[optional] $authorId
	 */
	public function checkContent($sessionId = null, $postTitle = null, $postBody = null, $authorName = null, $authorUrl = null, $authorEmail = null, $authorOpenId = null, $authorId = null)
	{
		// init var
		$parameters = array();
		$aReturn    = array();

		// add parameters
		if ($sessionId !== null) {
		    $parameters['session_id'] = (string) $sessionId;
        }
		if ($postTitle !== null) {
		    $parameters['post_title'] = (string) $postTitle;
        }
		if ($postBody !== null) {
		    $parameters['post_body'] = (string) $postBody;
        }
		if ($authorName !== null) {
		    $parameters['author_name'] = (string) $authorName;
        }
		if ($authorUrl !== null) {
		    $parameters['author_url'] = (string) $authorUrl;
        }
		if ($authorEmail !== null) {
		    $parameters['author_mail'] = (string) $authorEmail;
        }
		if ($authorOpenId != null) {
		    $parameters['author_openid'] = (string) $authorOpenId;
        }
		if ($authorId != null) {
		    $parameters['author_id'] = (string) $authorId;
        }
        
        // validate
		if (count($parameters) == 0) {
		    throw new Exception('Specify at least on argument');
		}

		// set autor ip
		$authorIp = self::getIpAddress();
		if ($authorIp != null) {
		    $parameters['author_ip'] = (string) $authorIp;
        }

		// do the call
		$responseString = $this->doCall('checkContent', $parameters);

		// validate
		if (!isset($responseString->params->param->value->struct->member)) {
		    throw new Exception('Invalid response in checkContent.');
        }

		// loop parts
		foreach ($responseString->params->param->value->struct->member as $part) {
			// get key
			$key = $part->name;

			// get value
			switch ($key) {
			case 'spam':
				$value           = (string) $part->value->int;
                $aReturn['spam'] = $this->parseSpam($value);
			    break;

			case 'quality':
				$aReturn['quality'] = (float) $part->value->double;
    			break;

			case 'session_id':
				$aReturn['session_id'] = (string) $part->value->string;
	    		break;
			}
		}

		// return
		return $aReturn;
	}

	/**
	 * Send feedback to Mollom.
	 *
	 * With this method you can help train Mollom. Implement this method if possible. The more feedback is provided the more accurate
	 * Mollom will be.
	 *
	 * Allowed feedback-strings are listed below:
	 * - spam			Spam or unsolicited advertising
	 * - profanity		Obscene, violent or profane content
	 * - low-quality	Low-quality content or writing
	 * - unwanted		Unwanted, taunting or off-topic content
	 *
	 * @return	bool
	 * @param	string $sessionId
	 * @param	string $feedback
	 */
	public static function sendFeedback($sessionId, $feedback)
	{
		// possible feedback
		$aPossibleFeedback = array('spam', 'profanity', 'low-quality', 'unwanted');

		// redefine
		$sessionId = (string) $sessionId;
		$feedback  = (string) $feedback;

		// validate
		if (!in_array($feedback, $aPossibleFeedback)) {
		    throw new Exception('Invalid feedback. Only '. implode(', ', $aPossibleFeedback) .' are possible feedback-strings.');
        }
        
		// build parameters
		$parameters['session_id'] = $sessionId;
		$parameters['feedback'] = $feedback;

		// do the call
		$responseString = self::doCall('sendFeedback', $parameters);

		// validate
		if (!isset($responseString->params->param->value->boolean)) {
		    throw new Exception('Invalid response in sendFeedback.');
        }

		// return
		if ((string) $responseString->params->param->value->boolean == 1) {
		    return true;
        }
        
		// fallback
		return false;
	}
}
