-- ============================================================
-- 002：客户资料扩展字段 + SKU 商品表
-- 依赖：001_init_auth.sql
-- ============================================================

-- 货主资料补全字段（注册只收基本信息，注册后补充）
ALTER TABLE `hw_customer`
  ADD COLUMN `tax_no`    VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '税号（EIN/VAT）' AFTER `country`,
  ADD COLUMN `website`   VARCHAR(128) NOT NULL DEFAULT '' COMMENT '公司网址' AFTER `tax_no`,
  ADD COLUMN `province`  VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '省/州' AFTER `website`,
  ADD COLUMN `city`      VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '城市' AFTER `province`,
  ADD COLUMN `address`   VARCHAR(255) NOT NULL DEFAULT '' COMMENT '地址行1' AFTER `city`,
  ADD COLUMN `address2`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT '地址行2' AFTER `address`,
  ADD COLUMN `postcode`  VARCHAR(20)  NOT NULL DEFAULT '' COMMENT '邮编' AFTER `address2`;

-- SKU 商品（货主维度建档；warehouse_id 暂置 0，仓库模块上线后启用按仓建档）
CREATE TABLE IF NOT EXISTS `hw_sku` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`   BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属货主（租户隔离键）',
  `sku_code`      VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '货主 SKU 编码',
  `barcode`       VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '默认条码（一品多码见二期 hw_sku_barcode）',
  `name`          VARCHAR(128) NOT NULL DEFAULT '' COMMENT '品名',
  `name_en`       VARCHAR(128) NOT NULL DEFAULT '' COMMENT '英文品名（报关用，可空）',
  `specs`         VARCHAR(128) NOT NULL DEFAULT '' COMMENT '规格（颜色/尺码等）',
  `unit`          VARCHAR(20)  NOT NULL DEFAULT '件' COMMENT '计量单位',
  `category`      VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '分类（自由文本，后期拆字典表）',
  `weight`        DECIMAL(10,3) NOT NULL DEFAULT 0 COMMENT '单件重量 kg',
  `length`        DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '长 cm（算体积重）',
  `width`         DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '宽 cm',
  `height`        DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '高 cm',
  `declare_value` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '申报价值 USD',
  `hs_code`       VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '海关编码（可空）',
  `warehouse_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '归属仓库（0=未指定，仓库模块上线后启用）',
  `remark`        VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `status`        TINYINT      NOT NULL DEFAULT 1 COMMENT '1 启用 0 停用（停用不可参与入库/出库）',
  `is_deleted`    TINYINT      NOT NULL DEFAULT 0 COMMENT '软删',
  `create_time`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `creator_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建人',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customer_sku` (`customer_id`, `sku_code`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_name` (`name`),
  KEY `idx_customer_status` (`customer_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SKU 商品';
