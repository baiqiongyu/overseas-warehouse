-- ============================================================
-- 020：恢复「仓库管理」独立顶栏模块（后续挂库区/库位管理）
--   * 页面/按钮 menu id 不变，仅改 pid，角色授权不受影响
--   * 超管补上模块本身的授权
-- ============================================================

-- 顶部模块：仓库管理
INSERT INTO `hw_menu` (`pid`, `type`, `title`, `path`, `icon`, `sort`, `create_time`)
VALUES (0, 1, '仓库管理', '', 'el-icon-office-building', 4, UNIX_TIMESTAMP());
SET @m_wh := LAST_INSERT_ID();

-- 页面挂回模块下（第一位，后续库区/库位排后面）
UPDATE `hw_menu` SET `pid` = @m_wh, `sort` = 1
WHERE `type` = 2 AND `path` = 'warehouse';

-- 超管角色补模块授权
INSERT IGNORE INTO `hw_role_menu` (`role_id`, `menu_id`) VALUES (1, @m_wh);
