<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/MockCourierDriver.php';
require_once APP_DIR . 'libraries/LalamoveCourierDriver.php';

class CourierProviderFactory
{
    public static function driverName(): string
    {
        $defaultDriver = function_exists('config_item') ? (config_item('courier_driver') ?: (getenv('COURIER_DRIVER') ?: 'mock')) : (getenv('COURIER_DRIVER') ?: 'mock');
        $driver = strtolower(trim($defaultDriver));
        if (!in_array($driver, ['mock', 'lalamove'], true)) {
            throw new RuntimeException('COURIER_DRIVER must be either mock or lalamove.');
        }
        return $driver;
    }

    public static function create(): CourierProviderInterface
    {
        return self::driverName() === 'mock' ? new MockCourierDriver() : new LalamoveCourierDriver();
    }
}
