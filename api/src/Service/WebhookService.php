<?php

namespace App\Service;

use App\Message\WebhookNotification;
use App\Repository\ContactInLeadRepository;
use App\Repository\ContactRepository;
use App\Repository\LeadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WebhookService
{
    public FilesystemAdapter $cache;

    public function __construct(
        protected EntityManagerInterface $em,
        protected SerializerInterface $serializer,
        protected ContactRepository $contactRepository,
        protected LeadRepository $leadRepository,
        protected ContactInLeadRepository $contactInLeadRepository,
        protected UserPasswordHasherInterface $passwordHasher,
        protected ValidatorInterface $validator,
    ) {
        $this->cache = new FilesystemAdapter();
    }

    public function setNotes(Request $request, MessageBusInterface $bus)
    {
        if ($request->get('leads')) {
            $isCreate = array_key_exists('add', $request->get('leads')) ? true : false;
            $id = $this->getId($isCreate, 'leads', $request);

            $bus->dispatch(new WebhookNotification(isCreate: $isCreate, leadId: $id, contactId: null));
        } else {
            $isCreate = array_key_exists('add', $request->get('contacts')) ? true : false;
            $id = $this->getId($isCreate, 'contacts', $request);

            $bus->dispatch(new WebhookNotification(isCreate: $isCreate, leadId: null, contactId: $id));
        }
    }

    public function getId(bool $isCreate, string $nameOfField, Request $request)
    {
        return $isCreate
            ? $request->get($nameOfField)['add'][0]['id']
            : $request->get($nameOfField)['update'][0]['id'];
    }
}
