<?php

declare(strict_types=1);

namespace App\Rewarding\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923002800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce one reversal per immutable Rewarding ledger transaction.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX reward_transaction_reversal_uidx ON reward_transaction (reverses_transaction_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX reward_transaction_reversal_uidx');
    }
}
