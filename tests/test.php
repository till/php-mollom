<?php
// require Mollom-class
require_once dirname(__FILE__) . '/../Services/Mollom.php';

// set keys
Mollom::setPublicKey('<your-public-key>');
Mollom::setPrivateKey('<your-private-key>');

// populate serverlist (get them from your db, or file, or ...
Mollom::setServerList($servers);

// execute the method
try
{
    if(Mollom::checkCaptcha($mollomSessionId, $answerFromVisitor)) echo 'the answer is correct, you may proceed!';
    else echo 'incorrect!';
} catch (Exception $e) {
    // your errorhandling goes here
}
