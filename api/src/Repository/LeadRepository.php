<?php

namespace App\Repository;

use App\Entity\Lead;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lead>
 */
class LeadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lead::class);
    }

    public function searchByLead(int $leadId): array
    {
        $qb = $this->createQueryBuilder('l')
        ->select('c.amoId')
        ->innerJoin('App\Entity\ContactInLead', 'cl', Join::WITH, 'l.id = cl.lead')
        ->innerJoin('App\Entity\Contact', 'c', Join::WITH, 'c.id = cl.contact')
        ->andWhere('l.amoId = :id')
        ->setParameter('id', $leadId);

        return $qb->getQuery()
            ->execute();
    }

    public function searchByLeadAndContact(int $leadId, int $contactId): array
    {
        $qb = $this->createQueryBuilder('l')
        ->select('cl.id')
        ->innerJoin('App\Entity\ContactInLead', 'cl', Join::WITH, 'l.id = cl.lead')
        ->innerJoin('App\Entity\Contact', 'c', Join::WITH, 'c.id = cl.contact')
        ->andWhere('l.amoId = :idl')
        ->setParameter('idl', $leadId)
        ->andWhere('c.amoId = :idc')
        ->setParameter('idc', $contactId);

        return $qb->getQuery()
            ->execute();
    }
}
