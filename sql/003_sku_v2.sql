-- ============================================================
-- 003：SKU 表重构 v2
-- 变更要点：
--   1. 去掉 barcode 手输 -> 系统生成 条码 = 货主编码 + "-" + SKU 编码（全局唯一）
--   2. 去掉 warehouse_id（SKU 跟货主走，不跟仓）
--   3. 颜色独立字段（可选）；specs/remark/category 移除
--   4. 新增 desc_cn / desc_en 中文英文描述（可选）
--   5. images JSON 必填（1-8 张图 URL）；attrs JSON 规格属性（带电/液体等）
--   6. 必填：sku_code / name_cn / name_en / weight / length / width / height / declare_value / images
-- 注：开发期直接重建（表内仅测试数据）
-- ============================================================

DROP TABLE IF EXISTS `hw_sku`;

CREATE TABLE `hw_sku` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`   BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属货主（租户隔离键）',
  `sku_code`      VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '货主 SKU 编码（货主内唯一，服务端转大写）',
  `barcode`       VARCHAR(128) NOT NULL DEFAULT '' COMMENT '系统条码 = 货主编码-SKU编码，全局唯一（UK）',
  `name_cn`       VARCHAR(128) NOT NULL COMMENT '中文品名',
  `name_en`       VARCHAR(128) NOT NULL COMMENT '英文品名',
  `color`         VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '颜色（可选）',
  `desc_cn`       TEXT         NULL COMMENT '中文描述（可选）',
  `desc_en`       TEXT         NULL COMMENT '英文描述（可选）',
  `weight`        DECIMAL(10,3) NOT NULL COMMENT '单件重量 kg',
  `length`        DECIMAL(10,2) NOT NULL COMMENT '长 cm',
  `width`         DECIMAL(10,2) NOT NULL COMMENT '宽 cm',
  `height`        DECIMAL(10,2) NOT NULL COMMENT '高 cm',
  `declare_value` DECIMAL(12,2) NOT NULL COMMENT '申报价值 USD',
  `hs_code`       VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '海关编码（可选）',
  `images`        JSON         NULL COMMENT '图片 URL 数组，1-8 张，第一张为主图',
  `attrs`         JSON         NULL COMMENT '规格属性 JSON：battery 带电 liquid 液体 powder 粉末 magnetic 磁性 fragile 易碎',
  `status`        TINYINT      NOT NULL DEFAULT 1 COMMENT '1 启用 0 停用',
  `is_deleted`    TINYINT      NOT NULL DEFAULT 0 COMMENT '软删',
  `create_time`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `creator_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建人',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customer_sku` (`customer_id`, `sku_code`),
  UNIQUE KEY `uk_barcode` (`barcode`),
  KEY `idx_name_cn` (`name_cn`),
  KEY `idx_customer_status` (`customer_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SKU 商品 v2';
