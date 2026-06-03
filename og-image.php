<?php
/**
 * og-image.php — Generador de imágenes OG 1200×630 para Facebook/WhatsApp
 * Centra la imagen del producto sobre un fondo oscuro con márgenes,
 * de forma que siempre salga 1200×630 sin recortar nada.
 *
 * Uso: og-image.php?src=img/productos/foto.jpg
 */

// ─── Seguridad: solo permitir rutas relativas al proyecto ────────────────────
$src = isset($_GET['src']) ? trim($_GET['src']) : '';

// Sanitizar: eliminar ../ y caracteres peligrosos
$src = preg_replace('/\.\./', '', $src);
$src = ltrim($src, '/\\');
$src = str_replace('\\', '/', $src);

if ($src === '') {
    http_response_code(400);
    exit('No se especificó imagen.');
}

// Ruta física en el servidor
$abs_path = __DIR__ . '/' . $src;

if (!file_exists($abs_path) || !is_file($abs_path)) {
    // Fallback a imagen genérica del sitio
    $abs_path = __DIR__ . '/img/TECH24.png';
    if (!file_exists($abs_path)) {
        http_response_code(404);
        exit('Imagen no encontrada.');
    }
}

// ─── Cache en disco ──────────────────────────────────────────────────────────
$cache_dir = __DIR__ . '/cache';
$cache_hash = md5($src . filemtime($abs_path));
$cache_file = $cache_dir . '/og_' . $cache_hash . '.jpg';

if (file_exists($cache_file)) {
    // Servir desde el archivo de cache estático (Cero delay para Facebook/bots)
    $cache_seconds = 86400 * 7; // Cachear por 7 días
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=' . $cache_seconds);
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache_seconds) . ' GMT');
    readfile($cache_file);
    exit;
}

// ─── Verificar que GD esté disponible ────────────────────────────────────────
if (!function_exists('imagecreatetruecolor')) {
    // Sin GD: redirigir a la imagen original
    header('Location: /' . $src, true, 302);
    exit;
}

// ─── Cargar imagen fuente ─────────────────────────────────────────────────────
$ext = strtolower(pathinfo($abs_path, PATHINFO_EXTENSION));

$src_img = null;
switch ($ext) {
    case 'jpg': case 'jpeg':
        $src_img = @imagecreatefromjpeg($abs_path); break;
    case 'png':
        $src_img = @imagecreatefrompng($abs_path);  break;
    case 'webp':
        $src_img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($abs_path) : null; break;
    case 'gif':
        $src_img = @imagecreatefromgif($abs_path);  break;
}

if (!$src_img) {
    // No se pudo cargar → redirigir a original
    header('Location: /' . $src, true, 302);
    exit;
}

// ─── Dimensiones del canvas OG (Facebook recomienda 1200×630) ─────────────────
$OG_W = 1200;
$OG_H = 630;

// ─── Crear canvas ─────────────────────────────────────────────────────────────
$canvas = imagecreatetruecolor($OG_W, $OG_H);

// Fondo: color oscuro corporativo #0d0d0d
$bg_color = imagecolorallocate($canvas, 13, 13, 13);
imagefill($canvas, 0, 0, $bg_color);

$sw = imagesx($src_img);
$sh = imagesy($src_img);

// ─── Detectar si es banner panorámico o imagen de producto ────────────────────
// Ratio del canvas: 1200/630 ≈ 1.905
// Si la imagen fuente es más ancha (ratio >= 1.6), es un banner panorámico:
//   → modo COVER: la imagen llena todo el canvas (recorte centrado, sin bordes negros)
// Si es producto (cuadrado o vertical): modo CONTAIN centrado con padding y borde
$img_ratio    = ($sh > 0) ? ($sw / $sh) : 1;
$canvas_ratio = $OG_W / $OG_H; // ~1.905
$is_banner    = ($img_ratio >= 1.6); // panorámica → banner

imagealphablending($canvas, true);
imagesavealpha($canvas, false);

if ($is_banner) {
    // ── MODO COVER: la imagen llena todo el canvas 1200×630 ──────────────────
    // Escalar para que ambos lados cubran el canvas (tomar el ratio mayor)
    $scale    = max($OG_W / $sw, $OG_H / $sh);
    $scaled_w = (int)round($sw * $scale);
    $scaled_h = (int)round($sh * $scale);

    // Posición fuente para recorte centrado
    $src_x = (int)round(($scaled_w - $OG_W) / 2 / $scale);
    $src_y = (int)round(($scaled_h - $OG_H) / 2 / $scale);
    $src_w = (int)round($OG_W / $scale);
    $src_h = (int)round($OG_H / $scale);

    imagecopyresampled(
        $canvas, $src_img,
        0, 0,          // destino: esquina superior-izquierda
        $src_x, $src_y,
        $OG_W, $OG_H,
        $src_w, $src_h
    );

    imagedestroy($src_img);
    // Sin borde para banners: la imagen ya llena todo
} else {
    // ── MODO CONTAIN: imagen de producto centrada con padding ─────────────────
    $padding = 60;
    $max_w   = $OG_W - ($padding * 2);
    $max_h   = $OG_H - ($padding * 2);

    $ratio = min($max_w / $sw, $max_h / $sh, 1.0);
    $new_w = (int)round($sw * $ratio);
    $new_h = (int)round($sh * $ratio);

    $dst_x = (int)round(($OG_W - $new_w) / 2);
    $dst_y = (int)round(($OG_H - $new_h) / 2);

    imagecopyresampled(
        $canvas, $src_img,
        $dst_x, $dst_y,
        0, 0,
        $new_w, $new_h,
        $sw, $sh
    );

    imagedestroy($src_img);

    // Borde sutil violeta Pixis alrededor del producto
    $border_color = imagecolorallocate($canvas, 176, 38, 255); // #b026ff
    imagerectangle($canvas, $dst_x - 1, $dst_y - 1, $dst_x + $new_w, $dst_y + $new_h, $border_color);
}

// Guardar en caché física del servidor
if (!file_exists($cache_dir)) {
    @mkdir($cache_dir, 0755, true);
}
if (is_writable($cache_dir) || (!file_exists($cache_file) && is_writable(__DIR__))) {
    @imagejpeg($canvas, $cache_file, 92);
}

// ─── Caché de la imagen generada en navegador (7 días) ───────────────────────
$cache_seconds = 86400 * 7;
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=' . $cache_seconds);
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache_seconds) . ' GMT');

// Salida JPEG calidad 92
imagejpeg($canvas, null, 92);
imagedestroy($canvas);
exit;
