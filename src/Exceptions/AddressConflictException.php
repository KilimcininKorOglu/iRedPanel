<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * A valid request that the stored accounts refuse: the address is in use, or the
 * directory owns the account. The REST API answers 409.
 */
class AddressConflictException extends InvalidInputException
{
}
