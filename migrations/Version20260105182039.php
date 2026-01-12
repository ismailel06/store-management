<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260105182039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE supply_request (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, supplier_id INT NOT NULL, requested_qty INT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8720D3324584665A (product_id), INDEX IDX_8720D3322ADD6D8C (supplier_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE supply_request ADD CONSTRAINT FK_8720D3324584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE supply_request ADD CONSTRAINT FK_8720D3322ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE supply_request DROP FOREIGN KEY FK_8720D3324584665A');
        $this->addSql('ALTER TABLE supply_request DROP FOREIGN KEY FK_8720D3322ADD6D8C');
        $this->addSql('DROP TABLE supply_request');
    }
}
