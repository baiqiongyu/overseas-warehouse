-- ============================================================
-- 017：后台仓库管理菜单（顶部模块「仓库管理」+ 页面 + 按钮权限）
--   * 仓库为基础资料（hw_warehouse），货主门户只读选择，后台维护
--   * 不用固定 ID，用 LAST_INSERT_ID 串接
-- ============================================================

-- 顶部模块：仓库管理
INSERT INTO `hw_menu` (`pid`, `type`, `title`, `path`, `icon`, `sort`, `create_time`)
VALUES (0, 1, '仓库管理', '', 'el-icon-office-building', 4, UNIX_TIMESTAMP());
SET @m_wh := LAST_INSERT_ID();

-- 页面：仓库列表
INSERT INTO `hw_menu` (`pid`, `type`, `title`, `path`, `icon`, `sort`, `create_time`)
VALUES (@m_wh, 2, '仓库列表', 'warehouse', '', 1, UNIX_TIMESTAMP());
SET @p_wh := LAST_INSERT_ID();

-- 按钮权限
INSERT INTO `hw_menu` (`pid`, `type`, `title`, `path`, `icon`, `sort`, `create_time`) VALUES
  (@p_wh, 3, '新增仓库', 'warehouse:create', '', 1, UNIX_TIMESTAMP()),
  (@p_wh, 3, '编辑仓库', 'warehouse:update', '', 2, UNIX_TIMESTAMP()),
  (@p_wh, 3, '启停仓库', 'warehouse:status', '', 3, UNIX_TIMESTAMP()),
  (@p_wh, 3, '删除仓库', 'warehouse:delete', '', 4, UNIX_TIMESTAMP());

-- 超管角色补齐新菜单
INSERT IGNORE INTO `hw_role_menu` (`role_id`, `menu_id`)
SELECT 1, `id` FROM `hw_menu`
WHERE `id` IN (@m_wh, @p_wh)
   OR `pid` IN (@m_wh, @p_wh);
