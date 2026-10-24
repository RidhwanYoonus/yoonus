<?php

// Only the upload limit differs from Livewire's defaults: product videos may be up to 50 MB.
return [
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:51200'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => ['png', 'gif', 'bmp', 'svg', 'wav', 'mp4', 'mov', 'avi', 'wmv', 'mp3', 'm4a', 'jpg', 'jpeg', 'mpga', 'webp', 'wma'],
        'max_upload_time' => 10,
        'cleanup' => true,
    ],
];
