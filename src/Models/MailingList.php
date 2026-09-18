<?php

declare(strict_types=1);

namespace App\Models;

class MailingList
{
    public function __construct(
        public readonly string $address,
        public readonly string $domain,
        public readonly string $name = '',
        public readonly string $accessPolicy = 'public',
        public readonly string $transport = '',
        public readonly int $maxMsgSize = 0,
        public readonly bool $active = true,
        public readonly ?string $created = null,
    ) {}
}
