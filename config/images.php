<?php
declare(strict_types=1);

function market_image_directory(): string
{
    $directory=getenv('GENERAL_IMAGE_DIR') ?: '/var/lib/gunindex/images';
    if (!str_starts_with($directory,'/') || is_link($directory)) throw new RuntimeException('Private image directory is not configured.');
    if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new RuntimeException('Private image storage unavailable.');
    $real=realpath($directory);
    $source=realpath(__DIR__.'/..');
    if ($real===false || $source===false || $real===$source || str_starts_with($real,$source.'/') || !is_writable($real)) throw new RuntimeException('Product images require private external storage.');
    return $real;
}

function market_remove_prepared_images(array $images): void
{
    foreach ($images as $image) {
        foreach ($image['paths'] ?? [] as $path) {
            if (is_string($path) && is_file($path) && !is_link($path)) unlink($path);
        }
    }
}

function market_write_webp(GdImage $image, string $path): void
{
    $file=fopen($path,'x+b');
    if ($file===false) throw new RuntimeException('Bild konnte nicht sicher gespeichert werden.');
    try {
        if (!imagewebp($image,$file,84)) throw new RuntimeException('Bild konnte nicht umgewandelt werden.');
    } finally {
        fclose($file);
    }
    chmod($path,0600);
    if (filesize($path)<1) throw new RuntimeException('Bildumwandlung fehlgeschlagen.');
}

function market_resize_image(GdImage $original, int $width, int $height, int $bound): GdImage
{
    $ratio=min(1,$bound/max($width,$height));
    $out=imagecreatetruecolor(max(1,(int)round($width*$ratio)),max(1,(int)round($height*$ratio)));
    imagealphablending($out,false);
    imagesavealpha($out,true);
    imagefill($out,0,0,imagecolorallocatealpha($out,0,0,0,127));
    if (!imagecopyresampled($out,$original,0,0,0,0,imagesx($out),imagesy($out),$width,$height)) {
        imagedestroy($out);
        throw new RuntimeException('Bild konnte nicht skaliert werden.');
    }
    return $out;
}

/** Validate actual HTTP uploads, recode, and retain only self-created paths for cleanup. */
function market_prepare_images(array $files, int $existing = 0): array
{
    if ($files===[]) return [];
    if (!isset($files['name'],$files['tmp_name'],$files['error'],$files['size']) || !is_array($files['name']) || !is_array($files['tmp_name']) || !is_array($files['error']) || !is_array($files['size'])) throw new InvalidArgumentException('Ungültige Bildupload-Struktur.');
    $active=[];
    foreach ($files['error'] as $index=>$error) {
        if (!is_int($error)) throw new InvalidArgumentException('Ungültiger Bildupload.');
        if ($error===UPLOAD_ERR_NO_FILE) continue;
        if ($error!==UPLOAD_ERR_OK) throw new InvalidArgumentException('Ein Bild wurde nicht vollständig hochgeladen. Maximal 5 MiB pro Bild.');
        $active[]=$index;
    }
    if (count($active)+$existing>5) throw new InvalidArgumentException('Maximal fünf Produktbilder pro Angebot.');
    if ($active===[]) return [];
    if (!extension_loaded('gd') || !function_exists('imagewebp')) throw new RuntimeException('Bildverarbeitung ist nicht verfügbar.');
    $directory=market_image_directory();
    $prepared=[];
    try {
        foreach ($active as $index) {
            $temporary=$files['tmp_name'][$index] ?? null;
            $size=$files['size'][$index] ?? null;
            if (!is_string($temporary) || !is_int($size) || $size<1 || $size>5*1024*1024 || !is_uploaded_file($temporary) || filesize($temporary)!==$size) throw new InvalidArgumentException('Nur vollständige HTTP-Bilder bis 5 MiB sind erlaubt.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($temporary);
            $decoders=['image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/webp'=>'imagecreatefromwebp'];
            $info=@getimagesize($temporary);
            if (!is_string($mime) || !isset($decoders[$mime]) || !$info || ($info['mime'] ?? '')!==$mime || $info[0]<1 || $info[1]<1 || $info[0]>8000 || $info[1]>8000 || $info[0]*$info[1]>20000000) throw new InvalidArgumentException('Bitte JPEG, PNG oder WebP mit maximal 20 Megapixeln und 8.000 Pixeln Seitenlänge hochladen.');
            $original=@($decoders[$mime])($temporary);
            if (!$original instanceof GdImage) throw new InvalidArgumentException('Das Bild konnte nicht vollständig gelesen werden.');
            $key=bin2hex(random_bytes(24));
            $largePath=$directory.'/'.$key.'.webp';
            $thumbPath=$directory.'/'.$key.'-thumb.webp';
            $prepared[]=['key'=>$key,'width'=>0,'height'=>0,'paths'=>[$largePath,$thumbPath]];
            $position=array_key_last($prepared);
            $large=null;
            $thumb=null;
            try {
                $large=market_resize_image($original,$info[0],$info[1],1600);
                $thumb=market_resize_image($original,$info[0],$info[1],400);
                market_write_webp($large,$largePath);
                market_write_webp($thumb,$thumbPath);
                $prepared[$position]['width']=imagesx($large);
                $prepared[$position]['height']=imagesy($large);
            } finally {
                imagedestroy($original);
                if ($large instanceof GdImage) imagedestroy($large);
                if ($thumb instanceof GdImage) imagedestroy($thumb);
            }
        }
        return $prepared;
    } catch (Throwable $error) {
        market_remove_prepared_images($prepared);
        throw $error;
    }
}
