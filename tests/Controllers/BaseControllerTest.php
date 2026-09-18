<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\BaseController;
use App\I18n\Translator;
use PHPUnit\Framework\TestCase;

class BaseControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Translator::init('en_US');
    }

    /**
     * SQL and schema details from the driver must not reach the page.
     */
    public function testDatabaseErrorIsReplacedWithGenericMessage(): void
    {
        $e = new \PDOException('SQLSTATE[42703]: Undefined column: column "local_part" does not exist');

        $message = BaseController::errorMessage($e);

        $this->assertSame(Translator::translate('common.msg_operation_failed'), $message);
        $this->assertStringNotContainsString('SQLSTATE', $message);
    }

    public function testValidationMessageIsShownAsIs(): void
    {
        $e = new \RuntimeException('Domain mailbox limit reached (5/5)');

        $this->assertSame('Domain mailbox limit reached (5/5)', BaseController::errorMessage($e));
    }
}
