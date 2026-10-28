-- ============================================================
-- 012：入库扩展（箱型档案 / 到仓形态 / 柜型 / 客户单号 / 箱尺寸重量 / 单号序列）
-- 层级不变：入库单 → 箱(可多箱型) → 箱内 SKU 明细
-- 新增箱型主数据后，箱的尺寸重量可继承箱型，只需填毛重
-- ============================================================

-- 1) 箱型档案（带编号，可复用；customer_id=0 为公共箱型）
CREATE TABLE IF NOT EXISTS `hw_box_type` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=公共箱型（全货主可用）',
  `code`        VARCHAR(16)  NOT NULL COMMENT '箱型编号，如 BX-A01',
  `name`        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '箱型名称',
  `length`      DECIMAL(6,1) NOT NULL DEFAULT 0 COMMENT '外箱长 cm',
  `width`       DECIMAL(6,1) NOT NULL DEFAULT 0 COMMENT '外箱宽 cm',
  `height`      DECIMAL(6,1) NOT NULL DEFAULT 0 COMMENT '外箱高 cm',
  `tare_weight` DECIMAL(8,2) NOT NULL DEFAULT 0 COMMENT '空箱自重 kg（用于算净货重）',
  `max_weight`  DECIMAL(8,2) NOT NULL DEFAULT 0 COMMENT '承重 kg',
  `status`      TINYINT      NOT NULL DEFAULT 1 COMMENT '1 启用 0 停用',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cust_code` (`customer_id`, `code`),
  KEY `idx_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='箱型档案';

-- 预置公共箱型（customer_id=0）
INSERT INTO `hw_box_type` (`customer_id`,`code`,`name`,`length`,`width`,`height`,`tare_weight`,`max_weight`,`status`,`create_time`,`update_time`) VALUES
(0, 'BX-A01', '标准箱', 60.0, 40.0, 50.0, 1.20, 25.00, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 'BX-B01', '小箱',   40.0, 30.0, 30.0, 0.80, 15.00, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(0, 'BX-C01', '大箱',   80.0, 60.0, 50.0, 2.00, 35.00, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 2) 单号序列（原子自增，替换「查 max + 1」）
CREATE TABLE IF NOT EXISTS `hw_sequence` (
  `seq_key`     VARCHAR(32) NOT NULL COMMENT '序列键，如 inbound_20261022',
  `seq_val`     BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前值',
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`seq_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='单号序列';

-- 3) 单头扩展
ALTER TABLE `hw_inbound_order`
  ADD COLUMN `ref_no`         VARCHAR(64) NOT NULL DEFAULT '' COMMENT '客户单号（货主自己的单号，选填可搜）' AFTER `order_no`,
  ADD COLUMN `handling_type`  TINYINT     NOT NULL DEFAULT 3  COMMENT '到仓形态：1 整柜直送仓 2 托盘 3 散箱' AFTER `transport_type`,
  ADD COLUMN `container_type` TINYINT     NOT NULL DEFAULT 0  COMMENT '柜型：0 未填 1 20GP 2 40GP 3 40HQ 4 45HQ 5 特种柜' AFTER `handling_type`,
  ADD COLUMN `eta_date`       DATE        NULL COMMENT '预计到仓日（货主/货代给的 ETA）' AFTER `expect_time`,
  ADD COLUMN `forwarder`      VARCHAR(64) NOT NULL DEFAULT '' COMMENT '头程货代 / 渠道' AFTER `eta_date`;

-- 4) 箱扩展（箱型 + 实际尺寸毛重 + 托盘号）
ALTER TABLE `hw_inbound_box`
  ADD COLUMN `box_type_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '箱型（hw_box_type.id，0=未选）' AFTER `inbound_id`,
  ADD COLUMN `length`      DECIMAL(6,1) NOT NULL DEFAULT 0 COMMENT '实际外箱长 cm（继承箱型，可覆盖）' AFTER `box_no`,
  ADD COLUMN `width`       DECIMAL(6,1) NOT NULL DEFAULT 0 COMMENT '实际外箱宽 cm' AFTER `length`,
  ADD COLUMN `height`      DECIMAL(6,1) NOT NULL DEFAULT 0 COMMENT '实际外箱高 cm' AFTER `width`,
  ADD COLUMN `weight`      DECIMAL(8,2) NOT NULL DEFAULT 0 COMMENT '毛重 kg（含空箱与内包装）' AFTER `height`,
  ADD COLUMN `pallet_no`   VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '托盘号（同托的箱共用，用于按托统计）' AFTER `tracking_no`;

-- 5) SKU 箱规
ALTER TABLE `hw_sku`
  ADD COLUMN `case_qty` INT NOT NULL DEFAULT 0 COMMENT '箱规：一箱标准装几件（0=未设）' AFTER `barcode`;
