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

// ─── Calcular posición centrada con padding ───────────────────────────────────
$padding = 60; // px de margen en todos los lados
$max_w = $OG_W - ($padding * 2);
$max_h = $OG_H - ($padding * 2);

$sw = imagesx($src_img);
$sh = imagesy($src_img);

// Escalar manteniendo aspecto, sin agrandar más que el original
$ratio = min($max_w / $sw, $max_h / $sh, 1.0);
$new_w = (int)round($sw * $ratio);
$new_h = (int)round($sh * $ratio);

// Centrar
$dst_x = (int)round(($OG_W - $new_w) / 2);
$dst_y = (int)round(($OG_H - $new_h) / 2);

// ─── Copiar imagen fuente centrada y escalada ─────────────────────────────────
// Soporte transparencia PNG
imagealphablending($canvas, true);
imagesavealpha($canvas, false);

imagecopyresampled(
    $canvas, $src_img,
    $dst_x, $dst_y,   // destino X, Y
    0, 0,              // fuente X, Y
    $new_w, $new_h,    // ancho/alto destino
    $sw, $sh           // ancho/alto fuente
);

imagedestroy($src_img);

// ─── Borde sutil alrededor de la imagen (opcional) ───────────────────────────
// Pequeño rectángulo de borde violeta Pixis
$border_color = imagecolorallocate($canvas, 176, 38, 255); // #b026ff
imagerectangle($canvas, $dst_x - 1, $dst_y - 1, $dst_x + $new_w, $dst_y + $new_h, $border_color);

// ─── Caché de la imagen generada (1 hora) ────────────────────────────────────
$cache_seconds = 3600;
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=' . $cache_seconds);
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache_seconds) . ' GMT');

// Salida JPEG calidad 92
imagejpeg($canvas, null, 92);
imagedestroy($canvas);
exit;
