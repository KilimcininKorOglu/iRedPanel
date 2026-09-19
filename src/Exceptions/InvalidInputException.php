<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * An invalid request field. The message stays English for API clients;
 * the web UI shows the translation key instead.
 */
class InvalidInputException extends \InvalidArgumentException
{
    /**
     * @param array<string, string|int> $params placeholders for the translation key
     * @param string $fieldKey translation key of the field label, passed as the :field placeholder
     */
    public function __construct(
        string $message,
        public readonly string $translationKey,
        public readonly array $params = [],
        public readonly string $fieldKey = '',
    ) {
        parent::__construct($message);
    }
}
