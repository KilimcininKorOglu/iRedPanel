<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Settings;

class GeoIpService
{
    private static ?self $instance = null;
    private ?object $reader = null;
    private bool $available = false;

    private function __construct()
    {
        $dbPath = Settings::getInstance()->geoIpDbPath;

        if ($dbPath !== '' && file_exists($dbPath) && class_exists('\GeoIp2\Database\Reader')) {
            try {
                $this->reader = new \GeoIp2\Database\Reader($dbPath);
                $this->available = true;
            } catch (\Exception $e) {
                error_log("GeoIP initialization failed: " . $e->getMessage());
            }
        }
    }

    public static function getInstance(): self
    {
        self::$instance ??= new self();
        return self::$instance;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * @return array{country: ?string, city: ?string, countryCode: ?string}
     */
    public function lookup(string $ip): array
    {
        $result = ['country' => null, 'city' => null, 'countryCode' => null];

        if (!$this->available || $this->reader === null) {
            return $result;
        }

        try {
            $record = $this->reader->city($ip);
            $result['country'] = $record->country->name;
            $result['city'] = $record->city->name;
            $result['countryCode'] = $record->country->isoCode;
        } catch (\Exception) {
            // IP not found in database or invalid — return empty
        }

        return $result;
    }
}
