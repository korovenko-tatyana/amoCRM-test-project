<?php

namespace App\Entity;

use App\Repository\ContactRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\Table(name: '`contacts`')]
#[ORM\Index(name: 'C_AMO_IDX', columns: ['amo_id'])]
class Contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column]
    private string $data;

    #[ORM\Column]
    private int $amoId;

    /**
     * @var Collection<int, ContactInLead>
     */
    #[ORM\OneToMany(targetEntity: ContactInLead::class, mappedBy: 'contact')]
    private Collection $contactInLeads;

    public function __construct()
    {
        $this->contactInLeads = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return Collection<int, ContactInLead>
     */
    public function getContactInLeads(): Collection
    {
        return $this->contactInLeads;
    }

    public function addContactInLead(ContactInLead $contactInLead): static
    {
        if (!$this->contactInLeads->contains($contactInLead)) {
            $this->contactInLeads->add($contactInLead);
            $contactInLead->setContact($this);
        }

        return $this;
    }

    public function getAmoId(): int
    {
        return $this->amoId;
    }

    public function setAmoId(int $amoId): static
    {
        $this->amoId = $amoId;

        return $this;
    }
}
