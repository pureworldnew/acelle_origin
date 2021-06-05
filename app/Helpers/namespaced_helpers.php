<?php

namespace Acelle\Helpers;

use Acelle\Library\StringHelper;

function generatePublicPath($relativePath)
{
    // Notice: $relativePath must be relative to storage/ folder
    // For example, with a real path of /home/deploy/acellemail/storage/app/sub/example.png
    // then $relativePath should be "app/sub/example.png"

    $encoded = StringHelper::base64UrlEncode($relativePath);

    return route('public_assets', [ 'path' => $encoded ]);
}
