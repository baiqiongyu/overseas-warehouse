-- ============================================================
-- 011：入库单按箱装箱（方案 A：货主创建时逐箱录入装箱明细）
-- 层级：入库单(运输方式/柜号/封号/运单号) → 箱(箱号/箱运单号) → 箱内SKU明细
-- hw_inbound_item 保留为「整单 SKU 汇总」= 各箱明细自动聚合，用于对账与上架
-- 托盘不建实体（仅运输包装，不入系统）
-- ============================================================

ALTER TABLE `hw_inbound_order`
  ADD COLUMN `transport_type` TINYINT NOT NULL DEFAULT 4
    COMMENT '运输方式：1 海运整柜 2 海运拼箱 3 空运 4 快递' AFTER `source_type`,
  ADD COLUMN `container_no` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '集装箱柜号（海运整柜）' AFTER `transport_type`,
  ADD COLUMN `seal_no` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '封号（海运整柜）' AFTER `container_no`,
  ADD COLUMN `tracking_no` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '整票运单号（快递/空运）' AFTER `seal_no`;

CREATE TABLE IF NOT EXISTS `hw_inbound_box` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inbound_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '入库单',
  `box_no`      VARCHAR(32) NOT NULL DEFAULT '' COMMENT '箱号（留空由系统按 BOX-001 顺序生成）',
  `tracking_no` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '箱运单号（快递件每箱一单，可空）',
  `state`       TINYINT NOT NULL DEFAULT 10 COMMENT '10 待收 20 已收（二期收货写）',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_inbound` (`inbound_id`),
  KEY `idx_box_no` (`box_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='入库箱';

CREATE TABLE IF NOT EXISTS `hw_inbound_box_item` (
  `id`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `box_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '箱',
  `sku_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU',
  `qty`     INT NOT NULL DEFAULT 0 COMMENT '本箱该 SKU 数量',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_box_sku` (`box_id`, `sku_id`),
  KEY `idx_sku` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='箱内 SKU 装箱明细';
