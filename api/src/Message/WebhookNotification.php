<?php

namespace App\Message;

class WebhookNotification
{
    public function __construct(
        private bool $isCreate,
        private ?int $leadId,
        private ?int $contactId,
    ) {
    }

    public function getIsCreate(): bool
    {
        return $this->isCreate;
    }

    public function getLeadId(): ?int
    {
        return $this->leadId;
    }

    public function getContactId(): ?int
    {
        return $this->contactId;
    }
}
