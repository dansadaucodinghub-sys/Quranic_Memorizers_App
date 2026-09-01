<?php
declare(strict_types=1);
namespace Qmdb\Modules\Organizations\Infrastructure\Security;
use Qmdb\Modules\Organizations\Domain\OrganizationRegistryCodeGenerator;
final readonly class SecureOrganizationRegistryCodeGenerator implements OrganizationRegistryCodeGenerator
{
 private const string ALPHABET='ABCDEFGHJKMNPQRSTVWXYZ23456789'; public function organization():string{return $this->generate('QMO-');} public function unit():string{return $this->generate('QMU-');} private function generate(string $prefix):string{$value='';for($i=0;$i<16;$i++){$value.=self::ALPHABET[random_int(0,strlen(self::ALPHABET)-1)];}return $prefix.$value;}
}
