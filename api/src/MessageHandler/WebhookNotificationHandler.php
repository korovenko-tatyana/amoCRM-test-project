<?php

namespace App\MessageHandler;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Client\LongLivedAccessToken;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\NoteType\CommonNote;
use App\Entity\Contact;
use App\Entity\ContactInLead;
use App\Entity\Lead;
use App\Message\WebhookNotification;
use App\Repository\ContactInLeadRepository;
use App\Repository\ContactRepository;
use App\Repository\LeadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Serializer\SerializerInterface;

#[AsMessageHandler]
class WebhookNotificationHandler
{
    public function __construct(
        protected EntityManagerInterface $em,
        protected SerializerInterface $serializer,
        protected ContactRepository $contactRepository,
        protected LeadRepository $leadRepository,
        protected ContactInLeadRepository $contactInLeadRepository,
    ) {
    }

    public function __invoke(WebhookNotification $message)
    {
        $isCreate = $message->getIsCreate();
        $leadId = $message->getLeadId();
        $contactId = $message->getContactId();

        /* @var AmoCRMApiClient $apiClient */
        $apiClient = new AmoCRMApiClient();
        $longLivedAccessToken = new LongLivedAccessToken($_ENV['AMO_TOKEN']);
        $apiClient->setAccessToken($longLivedAccessToken)
            ->setAccountBaseDomain($_ENV['AMO_URL']);

        switch (true) {
            case $isCreate && $leadId:
                $this->createLead($leadId, $apiClient);
                break;
            case $isCreate && $contactId:
                $this->createContact($contactId, $apiClient);
                break;
            case !$isCreate && $leadId:
                $this->updateLead($leadId, $apiClient);
                break;
            case !$isCreate && $contactId:
                $this->updateContact($contactId, $apiClient);
                break;
        }
    }

    private function createLead(int $leadId, AmoCRMApiClient $apiClient)
    {
        $lead = $apiClient->leads()->getOne($leadId, [EntityTypesInterface::CATALOG_ELEMENTS_FULL, EntityTypesInterface::CUSTOM_FIELDS, LeadModel::CONTACTS, LeadModel::CATALOG_ELEMENTS]);

        // Сохранить в бд новую сделку
        $bdLead = new Lead();
        $bdLead->setAmoId($leadId);
        $data = $this->serializer->serialize($lead, 'json');
        $bdLead->setData($data);
        $this->em->persist($bdLead);

        if ($lead?->getContacts()) {
            foreach ($lead->getContacts() as $value) {
                // найти контакт к сделке или сохранить новый, которого нет в бд
                $contact = $this->contactRepository->findOneBy(['amoId' => $value->getId()]) ?? $this->contactSave($value->getId(), $apiClient);

                // сохранить связь между сделкой и контактом
                $contactInLead = new ContactInLead();
                $contactInLead->setLead($bdLead)
                    ->setContact($contact);

                $this->em->persist($contactInLead);
            }
        }

        $this->em->flush();

        // Оставить примечание к сделке
        $name = $lead?->getName();
        $respUserName = $apiClient->users()->getOne($lead?->getResponsibleUserId())->getName();
        $time = date('Y-m-d H:i:s', $lead?->getCreatedAt());
        $text = "Создана сделка $name, ответственный: $respUserName, время создания: $time";
        $this->addNote($text, $leadId, $apiClient);
    }

    private function createContact(int $contactId, AmoCRMApiClient $apiClient)
    {
        // Если контакт уже создан - ничего не делать (это значит, что контакт был обработан на этапе сделки)
        if ($this->contactRepository->findOneBy(['amoId' => $contactId])) {
            return;
        }

        $this->contactSave($contactId, $apiClient);
        $this->em->flush();
    }

    private function updateLead(int $leadId, AmoCRMApiClient $apiClient)
    {
        $lead = $apiClient->leads()->getOne($leadId, [EntityTypesInterface::CATALOG_ELEMENTS_FULL, EntityTypesInterface::CUSTOM_FIELDS, LeadModel::CONTACTS, LeadModel::CATALOG_ELEMENTS]);
        $findLead = $this->leadRepository->findOneBy(['amoId' => $leadId]);

        if (!$findLead) {
            return;
        }

        $findData = json_decode($findLead->getData(), true);
        $count = 0;
        $text = 'Изменена сделка: ';

        // Начинаем собирать возможные поля, которые изменились в сделке. В данном задании для упрощения
        // отслеживаем поля name, price, responsibleUserId, statusId и добавление/удаление контакта.
        // В решении НЕТ отслеживания изменения компании, доп полей сделки и т.д.
        if ($lead->getName() !== $findData['name']) {
            ++$count;
            $name = $lead->getName();
            $text .= "- Название сделки: $name; ";
        }

        if ($lead->getPrice() !== $findData['price']) {
            ++$count;
            $name = $lead->getPrice();
            $text .= "- Бюджет сделки: $name; ";
        }

        if ($lead->getResponsibleUserId() !== $findData['responsibleUserId']) {
            ++$count;
            $name = $apiClient->users()->getOne($lead->getResponsibleUserId())->getName();
            $text .= "- Пользователь, ответственный за сделку: $name; ";
        }

        if ($lead->getStatusId() !== $findData['statusId']) {
            ++$count;
            $name = $apiClient->statuses($lead->getPipelineId())->getOne($lead->getStatusId())->getName();
            $text .= "- Статус, в который добавляется сделка: $name; ";
        }

        $this->checkContacts($lead, $text, $count, $findLead, $apiClient);

        if ($count) {
            // изменить в бд данные сделки
            $data = $this->serializer->serialize($lead, 'json');
            $findLead->setData($data);
            $this->em->persist($findLead);
            $this->em->flush();

            $time = date('Y-m-d H:i:s', $lead->getUpdatedAt());
            $text .= "время изменения: $time";

            // добавить примечание к сделке
            $this->addNote($text, $leadId, $apiClient);
        }
    }

    private function updateContact(int $contactId, AmoCRMApiClient $apiClient)
    {
        $amoContact = $apiClient->contacts()->getOne($contactId);
        $data = $this->serializer->serialize($amoContact, 'json');

        $findContact = $this->contactRepository->findOneBy(['amoId' => $contactId]);

        if (!$findContact) {
            return;
        }

        $findData = json_decode($findContact->getData(), true);
        $count = 0;
        $text = 'Изменен контакт пользователя: ';

        if (!array_key_exists('name', $findData) || $amoContact->getName() !== $findData['name']) {
            ++$count;
            $name = $amoContact->getName();
            $text .= "- Название: $name; ";
        }

        $amoCustomFields = $this->getAmoCustomFields($amoContact->getCustomFieldsValues());
        $findCustomFields = array_key_exists('customFieldsValues', $findData)
            ? $this->getDBCustomFields($findData['customFieldsValues'])
            : [];

        // сравнение доп полей контакта
        $this->diffCustomFields($amoCustomFields, $findCustomFields, $text, $count);

        $this->updateDbContact($findContact, $data);

        if ($count) {
            $time = date('Y-m-d H:i:s', $amoContact->getUpdatedAt());
            $text .= "время изменения: $time";

            $this->addNote($text, $contactId, $apiClient, false);

            $leadIds = $this->contactRepository->searchByContact($contactId);

            // добавить заметку также к каждой сделке контакта
            foreach ($leadIds as $value) {
                $this->addNote($text, $value['amoId'], $apiClient);
            }
        }
    }

    private function contactSave(int $id, AmoCRMApiClient $apiClient)
    {
        $amoContact = $apiClient->contacts()->getOne($id);
        $data = $this->serializer->serialize($amoContact, 'json');

        $dbContact = new Contact();
        $dbContact->setAmoId($id)
            ->setData($data);

        $this->em->persist($dbContact);

        // оставить примечание к карточке контакта
        $contact = $apiClient->contacts()->getOne($id);
        $name = $contact->getName();
        $respUserName = $apiClient->users()->getOne($contact->getResponsibleUserId())->getName();
        $time = date('Y-m-d H:i:s', $contact->getCreatedAt());
        $text = "Создан контакт $name, ответственный: $respUserName, время создания: $time";
        $this->addNote($text, $id, $apiClient, false);

        return $dbContact;
    }

    private function checkContacts(LeadModel $lead, string &$text, int &$count, Lead $dbLead, AmoCRMApiClient $apiClient)
    {
        $amoContactIds = [];

        if ($lead->getContacts()) {
            foreach ($lead->getContacts() as $value) {
                array_push($amoContactIds, $value->getId());
            }
        }

        $currentContact = [];
        $contactIds = $this->leadRepository->searchByLead($lead->getId());

        foreach ($contactIds as $value) {
            array_push($currentContact, $value['amoId']);
        }

        foreach ($amoContactIds as $value) {
            if (!in_array($value, $currentContact)) {
                $amoContact = $apiClient->contacts()->getOne($value)?->getName();

                if (!$amoContact) {
                    continue;
                }

                $this->saveEmptyContact($dbLead, $value);
                $text .= "- Добавлен пользователь $amoContact; ";
                ++$count;
            }
        }

        foreach ($currentContact as $value) {
            if (!in_array($value, $amoContactIds)) {
                $amoContact = $apiClient->contacts()->getOne($value)?->getName();

                if (!$amoContact) {
                    continue;
                }

                $arr = $this->leadRepository->searchByLeadAndContact($lead->getId(), $value);
                $this->contactInLeadRepository->deleteByLeadAndContact($arr[0]['id']);
                $text .= "- Удален пользователь $amoContact; ";
                ++$count;
            }
        }
    }

    private function updateDbContact(Contact $findContact, string $data)
    {
        $findContact->setData($data);
        $this->em->persist($findContact);
        $this->em->flush();
    }

    private function addNote(string $text, int $id, AmoCRMApiClient $apiClient, bool $isLead = true)
    {
        $note = new CommonNote();
        $note->setText($text)
            ->setEntityId($id)
            ->setCreatedBy(0);

        $type = $isLead ? EntityTypesInterface::LEADS : EntityTypesInterface::CONTACTS;
        $apiClient->notes($type)->addOne($note);
    }

    private function getAmoCustomFields(?CustomFieldsValuesCollection $amoCustomFields)
    {
        if (!$amoCustomFields) {
            return [];
        }

        $fields = [];
        foreach ($amoCustomFields as $value) {
            $fields[$value->getFieldName()] = $value->getValues()[0]->getValue();
        }

        return $fields;
    }

    private function getDBCustomFields(array $dbFields)
    {
        if (!$dbFields) {
            return [];
        }

        $fields = [];
        foreach ($dbFields as $value) {
            $fields[$value['field_name']] = $value['values'][0]['value'];
        }

        return $fields;
    }

    private function diffCustomFields(array $amoCustomFields, array $dbCustomFields, string &$text, int &$count)
    {
        foreach ($amoCustomFields as $key => $value) {
            if (!array_key_exists($key, $dbCustomFields)) {
                $text .= "Добавлено поле $key = $value; ";
                ++$count;
                continue;
            }

            if ($dbCustomFields[$key] !== $value) {
                $text .= "Изменено поле $key = $value; ";
                ++$count;
            }
        }

        foreach ($dbCustomFields as $key => $value) {
            if (!array_key_exists($key, $amoCustomFields)) {
                $text .= "Удалено поле $key = $value; ";
                ++$count;
                continue;
            }
        }
    }

    private function saveEmptyContact(Lead $dbLead, int $amoId)
    {
        $findContact = $this->contactRepository->findOneBy(['amoId' => $amoId]);
        $leadC = new ContactInLead();
        $leadC->setLead($dbLead);

        if (!$findContact) {
            $contact = new Contact();
            $contact->setAmoId($amoId)
                ->setData('');

            $this->em->persist($contact);
            $leadC->setContact($contact);
        } else {
            $leadC->setContact($findContact);
        }

        $this->em->persist($leadC);

        $this->em->flush();
    }
}
