-- ============================================================
-- 010：入库单（一期：正向入库，货主端做到「已提交」）
-- 状态机：10 草稿 → 20 已提交 → 30 已预约（仓库确认，二期 admin）
--                     → 90 已取消（草稿/已提交可取消）
-- 退货（source_type=2）与质检为三期，字段结构沿用 docs/03 设计
-- ============================================================

CREATE TABLE IF NOT EXISTS `hw_inbound_order` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`     VARCHAR(32)  NOT NULL COMMENT '入库单号 IN{Ymd}-{seq}（UK）',
  `customer_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '货主（租户隔离键）',
  `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '目标仓库',
  `biz_type`     TINYINT      NOT NULL DEFAULT 1 COMMENT '业务线：1 中转入库 2 代发入库（决定批次库存线）',
  `source_type`  TINYINT      NOT NULL DEFAULT 1 COMMENT '来源：1 头程（一期固定）2 退货 3 调拨入',
  `expect_time`  DATE         NULL COMMENT '预约到仓日期',
  `remark`       VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
  `state`        TINYINT      NOT NULL DEFAULT 10 COMMENT '10 草稿 20 已提交 30 已预约 40 到仓 50 收货中 60 收货完成 70 上架完成 90 已取消',
  `submit_time`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '提交时间',
  `cancel_time`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '取消时间',
  `cancel_reason` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '取消原因',
  `is_deleted`   TINYINT      NOT NULL DEFAULT 0 COMMENT '软删（仅草稿可删）',
  `create_time`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `creator_id`   BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建人（货主账号）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_customer_state` (`customer_id`, `state`),
  KEY `idx_warehouse` (`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='入库单';

CREATE TABLE IF NOT EXISTS `hw_inbound_item` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inbound_id`   BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '入库单',
  `sku_id`       BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU',
  `expect_qty`   INT NOT NULL DEFAULT 0 COMMENT '应收数量',
  `received_qty` INT NOT NULL DEFAULT 0 COMMENT '实收数量（二期收货写）',
  `shelved_qty`  INT NOT NULL DEFAULT 0 COMMENT '已上架数量（二期上架写）',
  `batch_no`     VARCHAR(32) NOT NULL DEFAULT '' COMMENT '批次号（默认取入库单号）',
  `state`        TINYINT NOT NULL DEFAULT 10 COMMENT '10 待收 20 部分收 30 收齐 40 上架完成',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_inbound_sku` (`inbound_id`, `sku_id`),
  KEY `idx_sku` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='入库明细';

CREATE TABLE IF NOT EXISTS `hw_inbound_attachment` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inbound_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '入库单',
  `file_url`    VARCHAR(255) NOT NULL COMMENT '文件 URL（/storage/inbound/...）',
  `file_name`   VARCHAR(128) NOT NULL DEFAULT '' COMMENT '原始文件名',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上传时间',
  PRIMARY KEY (`id`),
  KEY `idx_inbound` (`inbound_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='入库单附件';
