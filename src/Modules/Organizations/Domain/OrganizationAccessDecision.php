<?php
declare(strict_types=1);
namespace Qmdb\Modules\Organizations\Domain;
final readonly class OrganizationAccessDecision { private function __construct(public bool $allowed,public OrganizationAccessReason $reason){} public static function allow():self{return new self(true,OrganizationAccessReason::ALLOWED);} public static function deny(OrganizationAccessReason $reason):self{return new self(false,$reason);} }
