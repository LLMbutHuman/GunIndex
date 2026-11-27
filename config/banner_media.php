<?php
declare(strict_types=1);

/** Read a bounded GIF field. The original upload is never written to public storage. */
function banner_gif_field(string $bytes, int &$offset, int $length): string
{
    if ($offset + $length > strlen($bytes)) throw new InvalidArgumentException(t('banner_ads.invalid_media'));
    $field = substr($bytes, $offset, $length);
    $offset += $length;
    return $field;
}

/** Validate compressed pixel output by tracking code lengths without allocating decoded frames. */
function banner_gif_pixels(string $compressed, int $minimum, int $colors, int $pixels): void
{
    $clear = 1 << $minimum;
    $end = $clear + 1;
    $size = $minimum + 1;
    $next = $end + 1;
    $lengths = [];
    $last = 0;
    $count = 0;
    $buffer = 0;
    $bits = 0;
    $seenClear = false;
    for ($offset=0; $offset<strlen($compressed); $offset++) {
        $buffer |= ord($compressed[$offset]) << $bits;
        $bits += 8;
        while ($bits >= $size) {
            $code = $buffer & ((1 << $size)-1);
            $buffer >>= $size;
            $bits -= $size;
            if ($code===$clear) {
                $seenClear = true;
                $size = $minimum+1;
                $next = $end+1;
                $lengths = [];
                $last = 0;
                continue;
            }
            if (!$seenClear) throw new InvalidArgumentException(t('banner_ads.invalid_media'));
            if ($code===$end && $count===$pixels && $offset===strlen($compressed)-1) return;
            $length = $code<$clear ? ($code<$colors ? 1 : 0) : ($lengths[$code]??($code===$next && $last!==0 ? $last+1 : 0));
            if ($length===0 || ($count += $length)>$pixels) throw new InvalidArgumentException(t('banner_ads.invalid_media'));
            if ($last!==0 && $next<4096) {
                $lengths[$next++] = $last+1;
                if ($next===(1 << $size) && $size<12) $size++;
            }
            $last = $length;
        }
    }
    throw new InvalidArgumentException(t('banner_ads.invalid_media'));
}

/** Accept a small, bounded GIF89a subset and discard metadata and application payloads. */
function banner_safe_gif(string $bytes): string
{
    $offset = 0;
    $header = banner_gif_field($bytes, $offset, 6);
    if (!in_array($header, ['GIF87a', 'GIF89a'], true) || strlen($bytes) > 1048576) {
        throw new InvalidArgumentException(t('banner_ads.invalid_media'));
    }
    $screen = banner_gif_field($bytes, $offset, 7);
    ['width' => $width, 'height' => $height, 'packed' => $packed] = unpack('vwidth/vheight/Cpacked', $screen);
    if ($width < 1 || $height < 1 || $width > 1600 || $height > 900) {
        throw new InvalidArgumentException(t('banner_ads.invalid_media'));
    }
    $global = ($packed & 128) ? banner_gif_field($bytes, $offset, 3 << (($packed & 7) + 1)) : '';
    $output = 'GIF89a'.$screen.$global;
    $frames = 0;
    while (($tag = banner_gif_field($bytes, $offset, 1)) !== "\x3b") {
        if ($tag === "\x21") {
            $type = banner_gif_field($bytes, $offset, 1);
            $length = ord(banner_gif_field($bytes, $offset, 1));
            if ($type === "\xf9") {
                if ($length !== 4) throw new InvalidArgumentException(t('banner_ads.invalid_media'));
                $control = banner_gif_field($bytes, $offset, 4);
                if (banner_gif_field($bytes, $offset, 1) !== "\0" || (ord($control[0]) & 0xe0) !== 0
                    || ((ord($control[0]) >> 2) & 7) > 3) throw new InvalidArgumentException(t('banner_ads.invalid_media'));
                $delay = max(12, unpack('v', substr($control, 1, 2))[1]);
                $output .= "\x21\xf9\x04".chr(ord($control[0]) & 0xfd).pack('v', $delay).$control[3]."\0";
                continue;
            }
            if (!in_array($type, ["\xfe", "\xff"], true)) throw new InvalidArgumentException(t('banner_ads.invalid_media'));
            while ($length) {
                banner_gif_field($bytes, $offset, $length);
                $length = ord(banner_gif_field($bytes, $offset, 1));
            }
            continue;
        }
        if ($tag !== "\x2c" || ++$frames > 30 || $frames * $width * $height > 18000000) {
            throw new InvalidArgumentException(t('banner_ads.invalid_media'));
        }
        $descriptor = banner_gif_field($bytes, $offset, 9);
        $frame = unpack('vleft/vtop/vwidth/vheight/Cpacked', $descriptor);
        if ($frame['width'] < 1 || $frame['height'] < 1 || $frame['left'] + $frame['width'] > $width
            || $frame['top'] + $frame['height'] > $height || ($frame['packed'] & 24) !== 0) {
            throw new InvalidArgumentException(t('banner_ads.invalid_media'));
        }
        $local = ($frame['packed'] & 128) ? banner_gif_field($bytes, $offset, 3 << (($frame['packed'] & 7) + 1)) : '';
        $lzw = banner_gif_field($bytes, $offset, 1);
        if (ord($lzw) < 2 || ord($lzw) > 8 || ($global === '' && $local === '')) {
            throw new InvalidArgumentException(t('banner_ads.invalid_media'));
        }
        $output .= $tag.$descriptor.$local.$lzw;
        $start = $offset;
        $compressed = '';
        while (($length = ord(banner_gif_field($bytes, $offset, 1))) !== 0) $compressed .= banner_gif_field($bytes, $offset, $length);
        banner_gif_pixels($compressed,ord($lzw),intdiv(strlen($local!==''?$local:$global),3),$frame['width']*$frame['height']);
        $output .= substr($bytes, $start, $offset - $start);
    }
    if ($frames === 0 || $offset !== strlen($bytes)) throw new InvalidArgumentException(t('banner_ads.invalid_media'));
    return $output."\x3b";
}

function banner_prepare_media(array $file, string $overlay): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if ($overlay !== '') return ['key'=>'', 'format'=>'text', 'paths'=>[]];
        throw new InvalidArgumentException(t('banner_ads.invalid_media'));
    }
    $temporary = $file['tmp_name'] ?? null;
    $size = $file['size'] ?? null;
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($temporary) || !is_int($size)
        || $size < 1 || $size > 5 * 1024 * 1024 || !is_uploaded_file($temporary) || filesize($temporary) !== $size) {
        throw new InvalidArgumentException(t('banner_ads.invalid_media'));
    }
    if ((new finfo(FILEINFO_MIME_TYPE))->file($temporary) !== 'image/gif') {
        $prepared = market_prepare_images(array_map(static fn($value) => [$value], $file));
        return $prepared[0] + ['format'=>'webp'];
    }
    if ($size > 1048576) throw new InvalidArgumentException(t('banner_ads.invalid_media'));
    $bytes = file_get_contents($temporary);
    if (!is_string($bytes)) throw new InvalidArgumentException(t('banner_ads.invalid_media'));
    $clean = banner_safe_gif($bytes);
    $image = @imagecreatefromstring($clean);
    if (!$image instanceof GdImage) throw new InvalidArgumentException(t('banner_ads.invalid_media'));
    $key = bin2hex(random_bytes(24));
    $path = market_image_directory().'/'.$key.'.gif';
    $preview = market_image_directory().'/'.$key.'-thumb.webp';
    $media = ['key'=>$key, 'format'=>'gif', 'paths'=>[$path, $preview]];
    $owns = false;
    try {
        $handle = fopen($path, 'x+b');
        if ($handle === false) throw new RuntimeException(t('banner_ads.retry'));
        $owns = true;
        try {
            if (!chmod($path, 0600) || fwrite($handle, $clean) !== strlen($clean)) throw new RuntimeException(t('banner_ads.retry'));
        } finally {
            fclose($handle);
        }
        $thumb = market_resize_image($image, imagesx($image), imagesy($image), 400);
        try {
            market_write_webp($thumb, $preview);
        } finally {
            imagedestroy($thumb);
        }
        return $media;
    } catch (Throwable $error) {
        if ($owns) market_remove_prepared_images([$media]);
        throw $error;
    } finally {
        imagedestroy($image);
    }
}
