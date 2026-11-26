<?php

declare(strict_types=1);

namespace DoctrineMigrations\Logistic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903160100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add MDF-e vehicle data and primary driver to courier vehicles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE delivery_courier_vehicle ADD renavam VARCHAR(20) DEFAULT NULL AFTER plate");
        $this->addSql("ALTER TABLE delivery_courier_vehicle ADD tare DECIMAL(12,3) DEFAULT NULL AFTER renavam");
        $this->addSql("ALTER TABLE delivery_courier_vehicle ADD load_capacity DECIMAL(12,3) DEFAULT NULL AFTER tare");
        $this->addSql("ALTER TABLE delivery_courier_vehicle ADD axle_type VARCHAR(30) DEFAULT NULL AFTER load_capacity");
        $this->addSql("ALTER TABLE delivery_courier_vehicle ADD body_type VARCHAR(30) DEFAULT NULL AFTER axle_type");
        $this->addSql("ALTER TABLE delivery_courier_vehicle ADD main_driver_id INT(11) DEFAULT NULL AFTER body_type");
        $this->addSql("ALTER TABLE delivery_courier_vehicle ADD INDEX delivery_vehicle_driver_idx (main_driver_id)");
        $this->addSql("ALTER TABLE delivery_courier_vehicle ADD CONSTRAINT delivery_vehicle_driver_fk FOREIGN KEY (main_driver_id) REFERENCES people (id) ON DELETE SET NULL ON UPDATE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery_courier_vehicle DROP FOREIGN KEY delivery_vehicle_driver_fk');
        $this->addSql('ALTER TABLE delivery_courier_vehicle DROP INDEX delivery_vehicle_driver_idx');
        $this->addSql('ALTER TABLE delivery_courier_vehicle DROP main_driver_id, DROP body_type, DROP axle_type, DROP load_capacity, DROP tare, DROP renavam');
    }
}
