<?php

namespace App\Entity;

use App\Repository\ContactInLeadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactInLeadRepository::class)]
class ContactInLead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(inversedBy: 'contactInLeads')]
    #[ORM\JoinColumn(nullable: false)]
    private Lead $lead;

    #[ORM\ManyToOne(inversedBy: 'contactInLeads')]
    #[ORM\JoinColumn(nullable: false)]
    private Contact $contact;

    public function getId(): int
    {
        return $this->id;
    }

    public function getLead(): Lead
    {
        return $this->lead;
    }

    public function setLead(Lead $lead): static
    {
        $this->lead = $lead;

        return $this;
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function setContact(Contact $contact): static
    {
        $this->contact = $contact;

        return $this;
    }
}
