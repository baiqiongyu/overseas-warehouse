-- ============================================================
-- 022：后台入库单列表
--   1) hw_inbound_order 增加审核字段（audit_time / audit_by）
--   2) 菜单：「入库管理」模块下新增页面「入库单列表」(path=inbound)
--      + 按钮权限「审核入库单」(inbound:approve)，超管自动补授权
--      「收货作业」排到入库单列表之后
-- ============================================================

ALTER TABLE `hw_inbound_order`
  ADD COLUMN `audit_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审核时间' AFTER `submit_time`,
  ADD COLUMN `audit_by` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审核人 admin id' AFTER `audit_time`;

SET @m_inbound := (SELECT `id` FROM `hw_menu` WHERE `type` = 1 AND `title` = '入库管理' LIMIT 1);

-- 页面：入库单列表（排第一，收货作业排第二）
INSERT INTO `hw_menu` (`pid`, `type`, `title`, `path`, `icon`, `sort`, `create_time`)
VALUES (@m_inbound, 2, '入库单列表', 'inbound', '', 1, UNIX_TIMESTAMP());
SET @p_inbound := LAST_INSERT_ID();

UPDATE `hw_menu` SET `sort` = 2 WHERE `type` = 2 AND `path` = 'receiving';

-- 按钮：审核入库单
INSERT INTO `hw_menu` (`pid`, `type`, `title`, `path`, `icon`, `sort`, `create_time`)
VALUES (@p_inbound, 3, '审核入库单', 'inbound:approve', '', 1, UNIX_TIMESTAMP());

-- 超管补授权（页面 + 按钮）
INSERT IGNORE INTO `hw_role_menu` (`role_id`, `menu_id`)
SELECT 1, `id` FROM `hw_menu`
WHERE (`id` = @p_inbound) OR (`pid` = @p_inbound AND `type` = 3);
