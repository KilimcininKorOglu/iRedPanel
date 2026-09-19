<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * A stored secret cannot be decrypted, for example because IREDPANEL_SECRET_KEY changed.
 */
class SecretBoxException extends \RuntimeException {}
