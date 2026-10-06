<?php
/**
 * Copy this file to config.php and fill it in. config.php is read by
 * contact.php and should never be committed to a public repository.
 */

// Where enquiries are delivered.
define('CONTACT_TO', 'hello@siddharthaparmar.com');

// Sender address. Must be on a domain you control.
define('CONTACT_FROM', 'website@siddharthaparmar.com');
define('CONTACT_FROM_NAME', 'siddharthaparmar.com');

// Optional. Leave empty to use PHP mail().
// A Resend key (resend.com) gives far better deliverability than mail().
define('RESEND_API_KEY', '');
