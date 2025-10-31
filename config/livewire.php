<?php

return [
    'temporary_file_upload' => [
        // 使用本地磁盘作为临时上传目录，避免浏览器端直传 S3 带来的预签名与网络要求
        'disk' => env('LIVEWIRE_TEMP_DISK', 'public'),

        // 临时目录（相对磁盘根目录）。Livewire 使用 `directory` 键。
        'directory' => env('LIVEWIRE_TEMP_DIR', 'livewire-tmp'),

        // 可选：追加校验规则（留空使用默认）。
        'rules' => null,

        // 中间件保留默认（Livewire 自带签名校验）。
        'middleware' => null,

        // 可选：开发环境避免过早清理临时文件
        'cleanup' => env('LIVEWIRE_TEMP_CLEANUP', true),
    ],
];
