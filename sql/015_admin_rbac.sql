-- ============================================================
-- 015：内部后台 RBAC（员工角色 / 菜单模块 / 角色菜单授权）
-- 设计要点：
--   * hw_admin（001 已建）本就支持员工（customer_id NULL），此处补 role_id
--   * 菜单树三级结构：1 模块目录 / 2 页面菜单 / 3 按钮权限
--   * 角色通过 hw_role_menu 绑定菜单与按钮；is_super=1 的角色拥有全部权限
-- ============================================================

-- 员工角色
CREATE TABLE IF NOT EXISTS `hw_role` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '角色名称',
  `remark`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `is_super`    TINYINT      NOT NULL DEFAULT 0 COMMENT '1 超管角色（拥有全部权限，不可改删）',
  `status`      TINYINT      NOT NULL DEFAULT 1 COMMENT '1 启用 0 停用',
  `is_deleted`  TINYINT      NOT NULL DEFAULT 0 COMMENT '软删',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `creator_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建人',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`, `is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='员工角色';

-- 菜单 / 模块 / 按钮权限
CREATE TABLE IF NOT EXISTS `hw_menu` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`         BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级 ID，0=顶级',
  `type`        TINYINT      NOT NULL DEFAULT 2 COMMENT '1 模块目录 2 页面菜单 3 按钮权限',
  `title`       VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '名称',
  `path`        VARCHAR(128) NOT NULL DEFAULT '' COMMENT '页面路由 key（type=2）/ 按钮权限标识（type=3，如 staff:create）',
  `icon`        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '图标（Element icon 类名）',
  `sort`        INT          NOT NULL DEFAULT 0 COMMENT '排序，越小越靠前',
  `status`      TINYINT      NOT NULL DEFAULT 1 COMMENT '1 启用 0 停用',
  `is_deleted`  TINYINT      NOT NULL DEFAULT 0 COMMENT '软删',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
  `creator_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建人',
  PRIMARY KEY (`id`),
  KEY `idx_pid` (`pid`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='菜单/模块/按钮权限';

-- 角色-菜单授权
CREATE TABLE IF NOT EXISTS `hw_role_menu` (
  `role_id` BIGINT UNSIGNED NOT NULL COMMENT '角色 ID',
  `menu_id` BIGINT UNSIGNED NOT NULL COMMENT '菜单 ID',
  PRIMARY KEY (`role_id`, `menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色-菜单授权';

-- 员工账号补角色字段
ALTER TABLE `hw_admin`
  ADD COLUMN `role_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '员工角色（货主账号为 NULL）' AFTER `realname`;

-- ---------- 种子数据 ----------
-- 超管角色
INSERT INTO `hw_role` (`id`, `name`, `remark`, `is_super`, `status`, `create_time`)
VALUES (1, '超级管理员', '拥有全部权限', 1, 1, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 收货员角色
INSERT INTO `hw_role` (`id`, `name`, `remark`, `is_super`, `status`, `create_time`)
VALUES (2, '收货员', '入库收货作业', 0, 1, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 菜单树（固定 ID，便于角色授权种子）
INSERT INTO `hw_menu` (`id`, `pid`, `type`, `title`, `path`, `icon`, `sort`, `create_time`) VALUES
  (1, 0, 1, '工作台',   '',            'el-icon-monitor',   1, UNIX_TIMESTAMP()),
  (2, 1, 2, '首页概览', 'dashboard',   '',                  1, UNIX_TIMESTAMP()),
  (3, 0, 1, '入库管理', '',            'el-icon-box',       2, UNIX_TIMESTAMP()),
  (4, 3, 2, '收货作业', 'receiving',   '',                  1, UNIX_TIMESTAMP()),
  (5, 4, 3, '收货确认', 'receiving:receive', '',            1, UNIX_TIMESTAMP()),
  (6, 0, 1, '库存管理', '',            'el-icon-office-building', 3, UNIX_TIMESTAMP()),
  (7, 6, 2, '库存查询', 'stock',       '',                  1, UNIX_TIMESTAMP()),
  (8, 0, 1, '系统设置', '',            'el-icon-setting',   9, UNIX_TIMESTAMP()),
  (9, 8, 2, '员工管理', 'staff',       '',                  1, UNIX_TIMESTAMP()),
  (10, 9, 3, '新增员工', 'staff:create',       '',           1, UNIX_TIMESTAMP()),
  (11, 9, 3, '编辑员工', 'staff:update',       '',           2, UNIX_TIMESTAMP()),
  (12, 9, 3, '启停员工', 'staff:status',       '',           3, UNIX_TIMESTAMP()),
  (13, 9, 3, '重置密码', 'staff:resetpwd',     '',           4, UNIX_TIMESTAMP()),
  (14, 9, 3, '删除员工', 'staff:delete',       '',           5, UNIX_TIMESTAMP()),
  (15, 8, 2, '角色管理', 'role',        '',                  2, UNIX_TIMESTAMP()),
  (16, 15, 3, '新增角色', 'role:create',       '',            1, UNIX_TIMESTAMP()),
  (17, 15, 3, '编辑角色', 'role:update',       '',            2, UNIX_TIMESTAMP()),
  (18, 15, 3, '启停角色', 'role:status',       '',            3, UNIX_TIMESTAMP()),
  (19, 15, 3, '删除角色', 'role:delete',       '',            4, UNIX_TIMESTAMP()),
  (20, 8, 2, '菜单管理', 'menu',        '',                  3, UNIX_TIMESTAMP()),
  (21, 20, 3, '新增菜单', 'menu:create',       '',            1, UNIX_TIMESTAMP()),
  (22, 20, 3, '编辑菜单', 'menu:update',       '',            2, UNIX_TIMESTAMP()),
  (23, 20, 3, '启停菜单', 'menu:status',       '',            3, UNIX_TIMESTAMP()),
  (24, 20, 3, '删除菜单', 'menu:delete',       '',            4, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 超管角色：全菜单（is_super 走旁路，此处仍写入一份便于配置页展示）
INSERT IGNORE INTO `hw_role_menu` (`role_id`, `menu_id`)
SELECT 1, `id` FROM `hw_menu`;

-- 收货员角色：工作台 + 入库管理
INSERT IGNORE INTO `hw_role_menu` (`role_id`, `menu_id`) VALUES
  (2, 1), (2, 2), (2, 3), (2, 4), (2, 5);

-- 初始管理员 admin@hw.com / admin123456（首登后请修改）
INSERT INTO `hw_admin` (`customer_id`, `email`, `password`, `realname`, `role_id`, `status`, `create_time`)
VALUES (NULL, 'admin@hw.com', '$2y$10$xpF8h/M5xb1hS27n1IdK5OTgM3p/JC/AZtjed5qEnBbm5OS4QFjFq', '系统管理员', 1, 1, UNIX_TIMESTAMP());
