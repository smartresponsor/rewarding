<?php

declare(strict_types=1);

namespace App\Rewarding\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922195700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Rewarding account and immutable points ledger tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reward_account (id VARCHAR(64) NOT NULL, member_reference VARCHAR(191) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX reward_account_member_reference_uidx ON reward_account (member_reference)');
        $this->addSql('CREATE TABLE reward_transaction (id VARCHAR(64) NOT NULL, account_id VARCHAR(64) NOT NULL, ledger_version INT NOT NULL, type VARCHAR(32) NOT NULL, points INT NOT NULL, idempotency_key VARCHAR(191) NOT NULL, occurred_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, reference VARCHAR(191) DEFAULT NULL, reverses_transaction_id VARCHAR(64) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX reward_transaction_account_version_uidx ON reward_transaction (account_id, ledger_version)');
        $this->addSql('CREATE UNIQUE INDEX reward_transaction_idempotency_uidx ON reward_transaction (account_id, idempotency_key)');
        $this->addSql('CREATE INDEX reward_transaction_account_occurred_idx ON reward_transaction (account_id, occurred_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reward_transaction');
        $this->addSql('DROP TABLE reward_account');
    }
}
