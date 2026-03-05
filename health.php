<?php
/**
 * Lightweight health check endpoint
 * Returns quick OK status without loading database
 */
http_response_code(200);
header('Content-Type: text/plain');
echo "OK";