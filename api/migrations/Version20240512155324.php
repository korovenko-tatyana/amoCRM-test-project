<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240512155324 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE contact_in_lead_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "contacts_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "leads_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE contact_in_lead (id INT NOT NULL, lead_id INT NOT NULL, contact_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_98057F2E55458D ON contact_in_lead (lead_id)');
        $this->addSql('CREATE INDEX IDX_98057F2EE7A1254A ON contact_in_lead (contact_id)');
        $this->addSql('CREATE TABLE "contacts" (id INT NOT NULL, data text NOT NULL, amo_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX C_AMO_IDX ON "contacts" (amo_id)');
        $this->addSql('CREATE TABLE "leads" (id INT NOT NULL, data text NOT NULL, amo_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX L_AMO_IDX ON "leads" (amo_id)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
        $this->addSql('ALTER TABLE contact_in_lead ADD CONSTRAINT FK_98057F2E55458D FOREIGN KEY (lead_id) REFERENCES "leads" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE contact_in_lead ADD CONSTRAINT FK_98057F2EE7A1254A FOREIGN KEY (contact_id) REFERENCES "contacts" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE contact_in_lead_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE "contacts_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "leads_id_seq" CASCADE');
        $this->addSql('ALTER TABLE contact_in_lead DROP CONSTRAINT FK_98057F2E55458D');
        $this->addSql('ALTER TABLE contact_in_lead DROP CONSTRAINT FK_98057F2EE7A1254A');
        $this->addSql('DROP TABLE contact_in_lead');
        $this->addSql('DROP TABLE "contacts"');
        $this->addSql('DROP TABLE "leads"');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
