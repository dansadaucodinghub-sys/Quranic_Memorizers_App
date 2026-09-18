<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaModeration\Domain;

final readonly class MediaGovernanceRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $workspaceId,
        public int $creatorId,
        public string $status,
        public int $version,
        public bool $rights,
        public bool $consent,
        public bool $held,
        public bool $clean,
        public bool $processed,
    ) {
    }

    public function target(MediaGovernanceAction $action, int $actorId): string
    {
        if ($action === MediaGovernanceAction::WITHDRAW_CONSENT) {
            if ($actorId !== $this->creatorId) {
                throw new \DomainException('Only the recording owner may withdraw this consent.');
            }
            return $this->status === 'ARCHIVED' ? 'ARCHIVED' : 'WITHDRAWN';
        }
        if ($action === MediaGovernanceAction::APPROVE) {
            if ($actorId === $this->creatorId || !$this->rights || !$this->consent || $this->held || !$this->clean || !$this->processed || $this->status !== 'PENDING_MODERATION') {
                throw new \DomainException('Independent review, current consent, rights and successful processing are required.');
            }
            return 'APPROVED';
        }
        return match ($action) {
            MediaGovernanceAction::GRANT_CONSENT => $actorId !== $this->creatorId && !$this->held && $this->status === 'PENDING_MODERATION' ? $this->status : throw new \DomainException('Consent evidence requires independent review of pending media without a hold.'),
            MediaGovernanceAction::REJECT => $this->status === 'PENDING_MODERATION' && $actorId !== $this->creatorId ? 'REJECTED' : throw new \DomainException('Media cannot be rejected in this state.'),
            MediaGovernanceAction::HOLD => $this->status,
            MediaGovernanceAction::RELEASE_HOLD => $this->held ? $this->status : throw new \DomainException('No active hold exists.'),
            MediaGovernanceAction::REMOVE => $this->status !== 'ARCHIVED' ? 'WITHDRAWN' : throw new \DomainException('Archived evidence cannot be removed.'),
            MediaGovernanceAction::ARCHIVE => !$this->held && in_array($this->status, ['WITHDRAWN', 'REJECTED'], true) ? 'ARCHIVED' : throw new \DomainException('Only withdrawn or rejected evidence without a hold can be archived.'),
        };
    }
}
