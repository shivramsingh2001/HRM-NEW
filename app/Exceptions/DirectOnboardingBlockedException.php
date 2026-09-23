<?php

namespace App\Exceptions;

/**
 * Thrown by BiometricEmployeeProvisioningService when a device-side
 * enrollment can't be auto-onboarded into HRM (seat cap reached, or a
 * unique-email generation collision that couldn't be resolved). Distinct
 * from a plain \RuntimeException so BiometricV1Controller::reportEnrollments()
 * can catch this specific, expected business-rule failure and record it as
 * the enrollment row's last_error, letting any other unexpected exception
 * bubble/log normally.
 */
class DirectOnboardingBlockedException extends \RuntimeException
{
}
