-- ============================================================
-- 通用海外仓系统 初始化 SQL（001：注册登录 / 客户账号体系）
-- MySQL 5.7+ / 8.0+ / 9.x  前缀 hw_  utf8mb4
-- ============================================================

-- 货主客户（租户主体）
CREATE TABLE IF NOT EXISTS `hw_customer` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`                 VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '货主编码 C000001（注册后按ID回填）',
  `name`                 VARCHAR(128) NOT NULL DEFAULT '' COMMENT '公司名称',
  `contact`              VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '联系人',
  `phone`                VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '联系电话',
  `email`                VARCHAR(128) NOT NULL DEFAULT '' COMMENT '联系邮箱（与主账号一致）',
  `country`              VARCHAR(2)   NOT NULL DEFAULT '' COMMENT '国家代码 ISO2，如 US',
  `default_warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '默认仓库',
  `audit_state`          TINYINT      NOT NULL DEFAULT 1 COMMENT '审核状态 0待审核 1通过（当前注册自动通过，预留人工审核）',
  `audit_time`           INT UNSIGNED NULL DEFAULT NULL COMMENT '审核时间',
  `remark`               VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `status`               TINYINT      NOT NULL DEFAULT 1 COMMENT '1 启用 0 停用',
  `is_deleted`           TINYINT      NOT NULL DEFAULT 0 COMMENT '软删',
  `create_time`          INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time`          INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `creator_id`           BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建人',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_email` (`email`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='货主客户';

-- 系统账号（货主账号 customer_id 非空；员工账号为 NULL）
CREATE TABLE IF NOT EXISTS `hw_admin` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`     BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '所属货主，NULL=平台员工',
  `email`           VARCHAR(128) NULL DEFAULT NULL COMMENT '登录邮箱（唯一）',
  `phone`           VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '手机号',
  `password`        VARCHAR(255) NOT NULL COMMENT '密码 bcrypt 哈希',
  `realname`        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '姓名',
  `is_main`         TINYINT      NOT NULL DEFAULT 0 COMMENT '1 货主主账号',
  `last_login_time` INT UNSIGNED NULL DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip`   VARCHAR(45)  NOT NULL DEFAULT '' COMMENT '最后登录IP',
  `status`          TINYINT      NOT NULL DEFAULT 1 COMMENT '1 启用 0 停用',
  `is_deleted`      TINYINT      NOT NULL DEFAULT 0 COMMENT '软删',
  `create_time`     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time`     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `creator_id`      BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建人',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统账号';
