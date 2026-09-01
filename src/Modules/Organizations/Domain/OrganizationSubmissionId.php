<?php
declare(strict_types=1);
namespace Qmdb\Modules\Organizations\Domain;
use Qmdb\Modules\IdentityAccess\Domain\IdempotencySubmissionId;
use Qmdb\Shared\Identifier\UuidV7;
final readonly class OrganizationSubmissionId implements IdempotencySubmissionId
{
 private function __construct(private UuidV7 $value){} public static function generate():self{return new self(UuidV7::generate());} public static function fromString(string $value):self{return new self(UuidV7::fromString($value));} public function toString():string{return $this->value->toString();} public function toBinary():string{return $this->value->toBinary();}
}
