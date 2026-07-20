<?php

declare(strict_types=1);

namespace DoctrineMigrations\Logistic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Baseline schema for logistic module from s.controleonline.com";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('CREATE TABLE IF NOT EXISTS `car_manufacturer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `car_type_id` int(11) NOT NULL,
  `car_type_ref` int(11) NOT NULL,
  `label` varchar(255) CHARACTER SET utf8 NOT NULL,
  `value` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=220 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `car_model` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `car_manufacturer_id` int(11) NOT NULL,
  `label` varchar(255) CHARACTER SET utf8 NOT NULL,
  `value` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `car_manufacturer_id` (`car_manufacturer_id`),
  CONSTRAINT `car_model_ibfk_1` FOREIGN KEY (`car_manufacturer_id`) REFERENCES `car_manufacturer` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10793 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `car_year_price` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `car_type_id` int(11) NOT NULL,
  `car_type_ref` int(11) DEFAULT NULL,
  `fuel_type_code` int(11) DEFAULT NULL,
  `car_manufacturer_id` int(11) NOT NULL,
  `car_model_id` int(11) NOT NULL,
  `label` varchar(255) CHARACTER SET utf8 NOT NULL,
  `value` varchar(255) CHARACTER SET utf8 NOT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `car_manufacturer_id` (`car_manufacturer_id`),
  KEY `car_model_id` (`car_model_id`),
  CONSTRAINT `car_year_price_ibfk_1` FOREIGN KEY (`car_manufacturer_id`) REFERENCES `car_manufacturer` (`id`),
  CONSTRAINT `car_year_price_ibfk_2` FOREIGN KEY (`car_model_id`) REFERENCES `car_model` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=49394 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `delivery_courier_company_presence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `courier_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `availability_mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'automatic\',
  `is_online` tinyint(1) NOT NULL DEFAULT \'0\',
  `manual_reason` longtext COLLATE utf8mb4_unicode_ci,
  `last_online_at` datetime DEFAULT NULL,
  `last_offline_at` datetime DEFAULT NULL,
  `creation_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `alter_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_courier_company_presence_unique` (`courier_id`,`company_id`),
  KEY `delivery_courier_company_presence_courier_idx` (`courier_id`),
  KEY `delivery_courier_company_presence_company_idx` (`company_id`),
  KEY `delivery_courier_company_presence_mode_idx` (`availability_mode`),
  KEY `delivery_courier_company_presence_online_idx` (`is_online`),
  CONSTRAINT `FK_DELIVERY_COURIER_PRESENCE_COMPANY` FOREIGN KEY (`company_id`) REFERENCES `people` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_DELIVERY_COURIER_PRESENCE_COURIER` FOREIGN KEY (`courier_id`) REFERENCES `people` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `delivery_courier_company_presence_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `presence_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT \'1\',
  `creation_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `alter_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_courier_company_presence_schedule_unique` (`presence_id`,`schedule_id`),
  KEY `delivery_courier_company_presence_schedule_presence_idx` (`presence_id`),
  KEY `delivery_courier_company_presence_schedule_schedule_idx` (`schedule_id`),
  CONSTRAINT `FK_DELIVERY_COURIER_PRESENCE_SCHEDULE_DEFINITION` FOREIGN KEY (`schedule_id`) REFERENCES `delivery_courier_schedule` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_DELIVERY_COURIER_PRESENCE_SCHEDULE_PRESENCE` FOREIGN KEY (`presence_id`) REFERENCES `delivery_courier_company_presence` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `delivery_courier_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `courier_id` int(11) NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `weekday` smallint(6) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT \'1\',
  `creation_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `alter_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `delivery_courier_schedule_courier_idx` (`courier_id`),
  KEY `delivery_courier_schedule_weekday_idx` (`weekday`),
  KEY `delivery_courier_schedule_active_idx` (`active`),
  CONSTRAINT `FK_DELIVERY_COURIER_SCHEDULE_COURIER` FOREIGN KEY (`courier_id`) REFERENCES `people` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `delivery_courier_vehicle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `courier_id` int(11) NOT NULL,
  `vehicle_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creation_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `alter_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `brand` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `color` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_courier_vehicle_unique` (`courier_id`),
  KEY `delivery_courier_vehicle_courier_idx` (`courier_id`),
  CONSTRAINT `FK_DELIVERY_COURIER_VEHICLE_COURIER` FOREIGN KEY (`courier_id`) REFERENCES `people` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `delivery_region` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `region` varchar(255) CHARACTER SET utf8 NOT NULL,
  `people_id` int(11) NOT NULL,
  `deadline` int(3) NOT NULL,
  `retrieve_tax` decimal(15,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `region` (`region`,`people_id`),
  KEY `people_id` (`people_id`),
  CONSTRAINT `delivery_region_ibfk_1` FOREIGN KEY (`people_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1090 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `delivery_region_city` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_region_id` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_region_id` (`delivery_region_id`,`city_id`) USING BTREE,
  KEY `city_id` (`city_id`) USING BTREE,
  CONSTRAINT `delivery_region_city_ibfk_1` FOREIGN KEY (`delivery_region_id`) REFERENCES `delivery_region` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `delivery_region_city_ibfk_2` FOREIGN KEY (`city_id`) REFERENCES `city` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `delivery_restriction_material` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `people_id` int(11) NOT NULL,
  `product_material_id` int(11) NOT NULL,
  `restriction_type` enum(\'delivery_denied\',\'delivery_restricted\') CHARACTER SET utf8 NOT NULL DEFAULT \'delivery_denied\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `people_id` (`people_id`,`product_material_id`),
  KEY `product_material_id` (`product_material_id`),
  CONSTRAINT `delivery_restriction_material_ibfk_1` FOREIGN KEY (`people_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `delivery_restriction_material_ibfk_2` FOREIGN KEY (`product_material_id`) REFERENCES `product_material` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `delivery_tax` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `tax_description` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `tax_type` enum(\'fixed\',\'percentage\') CHARACTER SET utf8 NOT NULL,
  `tax_subtype` enum(\'invoice\',\'kg\',\'order\',\'km\') CHARACTER SET utf8 DEFAULT NULL,
  `people_id` int(11) DEFAULT NULL,
  `final_weight` decimal(10,3) DEFAULT NULL,
  `region_origin_id` int(11) DEFAULT NULL,
  `region_destination_id` int(11) DEFAULT NULL,
  `tax_order` int(11) NOT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `minimum_price` decimal(15,2) DEFAULT NULL,
  `optional` tinyint(1) NOT NULL,
  `delivery_tax_group_id` int(11) NOT NULL DEFAULT \'1\',
  `creation_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `alter_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deadline` int(11) NOT NULL DEFAULT \'0\',
  `km_from` decimal(10,2) DEFAULT NULL,
  `km_to` decimal(10,2) DEFAULT NULL,
  `price_per_km` decimal(15,2) DEFAULT NULL,
  `minimum_trip_value` decimal(15,2) DEFAULT NULL,
  `minimum_daily_value` decimal(15,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `people_id` (`people_id`),
  KEY `region_destination_id` (`region_destination_id`) USING BTREE,
  KEY `region_origin_id` (`region_origin_id`) USING BTREE,
  KEY `delivery_tax_group_id` (`delivery_tax_group_id`),
  CONSTRAINT `delivery_tax_ibfk_1` FOREIGN KEY (`region_origin_id`) REFERENCES `delivery_region` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `delivery_tax_ibfk_2` FOREIGN KEY (`region_destination_id`) REFERENCES `delivery_region` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `delivery_tax_ibfk_3` FOREIGN KEY (`delivery_tax_group_id`) REFERENCES `delivery_tax_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=434515 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `delivery_tax_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `carrier_id` int(11) NOT NULL,
  `code` varchar(50) CHARACTER SET utf8 DEFAULT NULL,
  `group_name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `cubage` int(11) NOT NULL DEFAULT \'300\',
  `max_height` decimal(10,2) DEFAULT NULL,
  `max_width` decimal(10,2) DEFAULT NULL,
  `max_depth` decimal(10,2) DEFAULT NULL,
  `min_cubage` decimal(12,4) DEFAULT NULL,
  `max_cubage` decimal(12,4) DEFAULT NULL,
  `marketplace` tinyint(1) NOT NULL DEFAULT \'1\',
  `remote` tinyint(1) NOT NULL DEFAULT \'0\',
  `website` tinyint(1) NOT NULL DEFAULT \'1\',
  `vehicle_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_number` int(11) NOT NULL DEFAULT \'1\',
  `previous_group_id` int(11) DEFAULT NULL,
  `creation_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `alter_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_tax_group_code_version_unique` (`code`,`version_number`),
  KEY `carrier_id` (`carrier_id`),
  KEY `delivery_tax_group_vehicle_type_idx` (`vehicle_type`),
  KEY `delivery_tax_group_version_idx` (`version_number`),
  KEY `delivery_tax_group_previous_group_idx` (`previous_group_id`),
  CONSTRAINT `delivery_tax_group_previous_group_fk` FOREIGN KEY (`previous_group_id`) REFERENCES `delivery_tax_group` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `delivery_tax_group_company` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_tax_group_id` int(11) NOT NULL,
  `people_id` int(11) NOT NULL,
  `activated_by` int(11) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT \'0\',
  `activated_at` datetime DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL,
  `creation_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `alter_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_tax_group_company_unique` (`delivery_tax_group_id`,`people_id`),
  KEY `delivery_tax_group_company_group_idx` (`delivery_tax_group_id`),
  KEY `delivery_tax_group_company_company_idx` (`people_id`),
  KEY `delivery_tax_group_company_activated_by_idx` (`activated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `discount_coupon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) CHARACTER SET utf8 NOT NULL,
  `type` enum(\'percentage\',\'amount\') CHARACTER SET utf8 NOT NULL DEFAULT \'percentage\',
  `company_id` int(11) DEFAULT NULL,
  `creator_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `discount_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `discount_start_date` date NOT NULL,
  `discount_end_date` date NOT NULL,
  `config` longtext CHARACTER SET utf8 NOT NULL,
  `value` decimal(15,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `creator_id` (`creator_id`),
  KEY `client_id` (`client_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `discount_coupon_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `discount_coupon_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `discount_coupon_ibfk_3` FOREIGN KEY (`company_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `quote` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(15) CHARACTER SET utf8 DEFAULT NULL,
  `internal_ip` varchar(15) CHARACTER SET utf8 DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `provider_id` int(11) NOT NULL,
  `carrier_id` int(11) NOT NULL,
  `city_origin_id` int(11) NOT NULL,
  `city_destination_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `deadline` int(11) NOT NULL,
  `total` decimal(15,2) DEFAULT NULL,
  `denied` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `client_id` (`client_id`),
  KEY `provider_id` (`provider_id`),
  KEY `city_origin_id` (`city_origin_id`),
  KEY `city_destination_id` (`city_destination_id`),
  KEY `carrier_id` (`carrier_id`),
  CONSTRAINT `quote_ibfk_1` FOREIGN KEY (`city_origin_id`) REFERENCES `city` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `quote_ibfk_2` FOREIGN KEY (`city_destination_id`) REFERENCES `city` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `quote_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `quote_ibfk_4` FOREIGN KEY (`provider_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `quote_ibfk_5` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `quote_ibfk_6` FOREIGN KEY (`carrier_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=58912 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `quote_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_id` int(11) NOT NULL,
  `delivery_tax_id` int(11) DEFAULT NULL,
  `tax_name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `tax_description` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `tax_type` enum(\'fixed\',\'percentage\') CHARACTER SET utf8 NOT NULL,
  `tax_subtype` enum(\'invoice\',\'kg\',\'order\',\'km\') CHARACTER SET utf8 DEFAULT NULL,
  `final_weight` decimal(10,3) DEFAULT NULL,
  `region_origin_id` int(11) DEFAULT NULL,
  `region_destination_id` int(11) DEFAULT NULL,
  `tax_order` int(11) NOT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `minimum_price` decimal(15,2) DEFAULT NULL,
  `optional` tinyint(1) NOT NULL,
  `price_calculated` double NOT NULL,
  PRIMARY KEY (`id`),
  KEY `region_destination_id` (`region_destination_id`) USING BTREE,
  KEY `region_origin_id` (`region_origin_id`) USING BTREE,
  KEY `delivery_tax_id` (`delivery_tax_id`),
  KEY `quote` (`quote_id`),
  CONSTRAINT `quote_detail_ibfk_1` FOREIGN KEY (`quote_id`) REFERENCES `quote` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `quote_detail_ibfk_3` FOREIGN KEY (`region_origin_id`) REFERENCES `delivery_region` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `quote_detail_ibfk_4` FOREIGN KEY (`region_destination_id`) REFERENCES `delivery_region` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `quote_detail_ibfk_5` FOREIGN KEY (`delivery_tax_id`) REFERENCES `delivery_tax` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=398252 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `tax` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `tax_type` enum(\'fixed\',\'percentage\') CHARACTER SET utf8 NOT NULL,
  `tax_subtype` enum(\'invoice\',\'kg\',\'order\') CHARACTER SET utf8 DEFAULT NULL,
  `people_id` int(11) NOT NULL,
  `state_origin_id` int(11) NOT NULL,
  `state_destination_id` int(11) NOT NULL,
  `tax_order` int(11) NOT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `minimum_price` decimal(15,2) DEFAULT NULL,
  `optional` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `people_id` (`people_id`),
  KEY `region_destination_id` (`state_destination_id`) USING BTREE,
  KEY `region_origin_id` (`state_origin_id`) USING BTREE,
  CONSTRAINT `tax_ibfk_1` FOREIGN KEY (`people_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tax_ibfk_2` FOREIGN KEY (`state_origin_id`) REFERENCES `state` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tax_ibfk_3` FOREIGN KEY (`state_destination_id`) REFERENCES `state` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(Schema $schema): void
    {
        return;
    }
}
