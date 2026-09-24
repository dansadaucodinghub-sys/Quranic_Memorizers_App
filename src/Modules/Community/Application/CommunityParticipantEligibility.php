<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

/** Fail-closed adult participation boundary until governed child interaction consent exists. */
interface CommunityParticipantEligibility
{
    public function requireAdultSelfLinkedAccount(int $accountId): void;
}
