<?php
// 全局中间件定义文件
return [
    // 跨域（开发期放开）
    \app\common\middleware\Cors::class,
    // 全局请求缓存
    // \think\middleware\CheckRequestCache::class,
    // 多语言加载（注意：不启用，规避 TP6.1 多语言 LFI 漏洞面）
    // \think\middleware\LoadLangPack::class,
    // Session初始化
    // \think\middleware\SessionInit::class
];
