<?php

namespace App\Repository;

use App\Entity\Contact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    public function searchByContact(int $contactId): array
    {
        $qb = $this->createQueryBuilder('c')
        ->select('l.amoId')
        ->innerJoin('App\Entity\ContactInLead', 'cl', Join::WITH, 'c.id = cl.contact')
        ->innerJoin('App\Entity\Lead', 'l', Join::WITH, 'l.id = cl.lead')
        ->andWhere('c.amoId = :id')
        ->setParameter('id', $contactId);

        return $qb->getQuery()
            ->execute();
    }
}
