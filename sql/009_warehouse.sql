-- ============================================================
-- 009：仓库基础资料（入库前置依赖）
-- 仓库由系统/管理端维护，货主门户只读选择
-- ============================================================

CREATE TABLE IF NOT EXISTS `hw_warehouse` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(32)  NOT NULL COMMENT '仓库编码（UK）',
  `name`        VARCHAR(64)  NOT NULL COMMENT '仓库名称',
  `country`     VARCHAR(2)   NOT NULL DEFAULT '' COMMENT '国家（ISO2）',
  `city`        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '城市',
  `address`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '详细地址',
  `status`      TINYINT      NOT NULL DEFAULT 1 COMMENT '1 启用 0 停用',
  `is_deleted`  TINYINT      NOT NULL DEFAULT 0 COMMENT '软删',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='仓库';

-- 预置测试仓库
INSERT INTO `hw_warehouse` (`code`, `name`, `country`, `city`, `address`, `status`, `create_time`, `update_time`) VALUES
('SZX01', '深圳中转仓', 'CN', '深圳', '宝安区福永街道', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('LAX01', '洛杉矶仓',   'US', '洛杉矶', 'Ontario, CA',   1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('EWR01', '新泽西仓',   'US', '纽瓦克', 'Elizabeth, NJ', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
