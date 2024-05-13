<?php

namespace App\Repository;

use App\Entity\ContactInLead;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactInLead>
 */
class ContactInLeadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactInLead::class);
    }

    public function deleteByLeadAndContact(int $id): void
    {
        $this->createQueryBuilder('cl')
        ->delete()
        ->andWhere('cl.id = :id')
        ->setParameter('id', $id)
        ->getQuery()
        ->execute();
    }
}
