<?php
/**
 * Deny direct web access
 */
http_response_code(403);
exit('Forbidden');
