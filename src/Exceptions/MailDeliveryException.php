<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * The SMTP server refused a message or could not be reached. The message
 * carries server details, so show a generic text to end users.
 */
class MailDeliveryException extends \RuntimeException {}
