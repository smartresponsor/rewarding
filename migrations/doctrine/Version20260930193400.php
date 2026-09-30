<?php

declare(strict_types=1);

namespace App\Rewarding\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930193400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create canonical Rewarding root reward table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reward (id VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reward');
    }
}
