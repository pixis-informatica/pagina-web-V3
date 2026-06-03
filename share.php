<?php
// Anti-cache headers first thing
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$domain = 'https://pixistech.store';
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $domain = $protocol . $_SERVER['HTTP_HOST'];
}

// Redirect real users to the frontend immediately if not a bot
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$is_bot = preg_match('/(WhatsApp|facebookexternalhit|Twitterbot|Discordbot|LinkedInBot|TelegramBot|Slackbot|Googlebot|bingbot)/i', $user_agent);

$query_params = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
$redirect_url = rtrim($domain, '/') . '/index.html' . $query_params;

if (!$is_bot) {
    header('Location: ' . $redirect_url, true, 302);
    exit;
}

// Helper: normalize and build absolute URL
function build_absolute_url($domain, $path) {
    if (empty($path)) return '';
    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');
    $path = preg_replace('#/+#', '/', $path);
    return rtrim($domain, '/') . '/' . $path;
}

// Helper: format price
function format_price($price_val) {
    if (is_numeric($price_val)) {
        return '$' . number_format((float)$price_val, 2, ',', '.');
    }
    return $price_val;
}

// Helper: clean description
function clean_description($desc) {
    if (empty($desc)) return '';
    $desc = strip_tags($desc);
    $desc = str_replace(array("\r", "\n"), ' ', $desc);
    $desc = preg_replace('/\s+/', ' ', $desc);
    $desc = trim($desc);
    if (mb_strlen($desc) > 160) {
        $desc = mb_substr($desc, 0, 157) . '...';
    }
    return $desc;
}

// Helper: slugify
function get_slug($text) {
    $unwanted_array = array(
        'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C',
        'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
        'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a',
        'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i',
        'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u',
        'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
    );
    $text = strtr($text, $unwanted_array);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

// Default/fallback values
$fallback_title = "Pixis Informática | Especialistas en Computación";
$fallback_description = "Tienda de computación online en Santiago del Estero. Venta de accesorios gamer, hardware de alto rendimiento y servicio técnico especializado.";
$fallback_image = build_absolute_url($domain, 'img/TECH24.png');

$og_title = null;
$og_description = null;
$og_image = null;

// Scenario 1: Product
if (isset($_GET['producto'])) {
    $producto_query = trim($_GET['producto']);
    $found_product = null;

    if ($producto_query !== '') {
        $products_file = __DIR__ . '/data/products.json';
        if (file_exists($products_file)) {
            $products_data = json_decode(file_get_contents($products_file), true);
            if (is_array($products_data)) {
                foreach ($products_data as $p) {
                    $p_id = isset($p['id']) ? trim($p['id']) : '';
                    $p_slug = isset($p['slug']) ? trim($p['slug']) : '';
                    $p_title_slug = isset($p['title']) ? get_slug($p['title']) : '';

                    if (($p_id !== '' && strcasecmp($p_id, $producto_query) === 0) || 
                        ($p_slug !== '' && strcasecmp($p_slug, $producto_query) === 0) || 
                        ($p_title_slug !== '' && strcasecmp($p_title_slug, $producto_query) === 0)) {
                        $found_product = $p;
                        break;
                    }
                }
            }
        }
    }

    if ($found_product) {
        $p_title = isset($found_product['title']) ? trim($found_product['title']) : '';
        
        // Find price: check price, priceNum, priceVisible
        $price_val = null;
        if (isset($found_product['price']) && is_numeric($found_product['price']) && (float)$found_product['price'] > 0) {
            $price_val = (float)$found_product['price'];
        } elseif (isset($found_product['priceNum']) && is_numeric($found_product['priceNum']) && (float)$found_product['priceNum'] > 0) {
            $price_val = (float)$found_product['priceNum'];
        }

        if ($price_val !== null) {
            $formatted_price = format_price($price_val);
        } elseif (!empty($found_product['priceVisible'])) {
            $formatted_price = trim($found_product['priceVisible']);
        } else {
            $formatted_price = '';
        }

        if ($formatted_price !== '') {
            $og_title = $p_title . " - " . $formatted_price;
        } else {
            $og_title = $p_title;
        }

        $og_description = isset($found_product['desc']) ? clean_description($found_product['desc']) : '';
        if ($og_description === '') {
            $og_description = "Comprá " . $p_title . " al mejor precio en Pixis Informática. Hardware de alto rendimiento en Santiago del Estero.";
        }

        if (!empty($found_product['img'])) {
            $og_image = build_absolute_url($domain, $found_product['img']);
        }
    }
}

// Scenario 2: Category
if (!$og_title && isset($_GET['categoria'])) {
    $categoria_query = trim($_GET['categoria']);
    $found_category = null;

    if ($categoria_query !== '') {
        $categories_file = __DIR__ . '/data/categories.json';
        if (file_exists($categories_file)) {
            $categories_data = json_decode(file_get_contents($categories_file), true);
            if (is_array($categories_data)) {
                foreach ($categories_data as $cat) {
                    $cat_id = isset($cat['id']) ? trim($cat['id']) : '';
                    $cat_name_slug = isset($cat['name']) ? get_slug($cat['name']) : '';

                    if (($cat_id !== '' && strcasecmp($cat_id, $categoria_query) === 0) || 
                        ($cat_name_slug !== '' && strcasecmp($cat_name_slug, $categoria_query) === 0)) {
                        $found_category = $cat;
                        break;
                    }
                }
            }
        }
    }

    if ($found_category) {
        $cat_name = isset($found_category['name']) ? trim($found_category['name']) : '';
        $og_title = $cat_name . " - Pixis Informática";
        $og_description = "Explorá nuestra categoría de " . $cat_name . " en Pixis Informática. Encontrá los mejores precios y hardware de alto rendimiento.";
        
        if (!empty($found_category['customIcon'])) {
            $og_image = build_absolute_url($domain, $found_category['customIcon']);
        }
    }
}

// Scenario 3: Banner
if (!$og_title && isset($_GET['banner'])) {
    $banner_query = trim($_GET['banner']);
    $found_banner_info = null;
    $found_banner_img = '';
    $banner_key = null;

    if ($banner_query !== '') {
        $site_file = __DIR__ . '/data/site.json';
        if (file_exists($site_file)) {
            $site_data = json_decode(file_get_contents($site_file), true);
            if (is_array($site_data)) {
                if (isset($site_data['banners']) && is_array($site_data['banners'])) {
                    foreach ($site_data['banners'] as $b_id => $b_info) {
                        $b_title_slug = isset($b_info['t']) ? get_slug($b_info['t']) : '';
                        if (strcasecmp($b_id, $banner_query) === 0 || 
                            get_slug($b_id) === get_slug($banner_query) || 
                            strcasecmp($b_title_slug, $banner_query) === 0) {
                            $found_banner_info = $b_info;
                            $banner_key = $b_id;
                            break;
                        }
                    }
                }
                
                $carousels = array_merge(
                    isset($site_data['carouselTop']) && is_array($site_data['carouselTop']) ? $site_data['carouselTop'] : array(),
                    isset($site_data['carouselBottom']) && is_array($site_data['carouselBottom']) ? $site_data['carouselBottom'] : array()
                );
                foreach ($carousels as $slide) {
                    if (isset($slide['bannerId']) && (strcasecmp($slide['bannerId'], $banner_query) === 0 || ($banner_key !== null && strcasecmp($slide['bannerId'], $banner_key) === 0))) {
                        if (!empty($slide['imgPc'])) {
                            $found_banner_img = $slide['imgPc'];
                            break;
                        }
                    }
                }
            }
        }
    }

    if ($found_banner_info) {
        $banner_title = isset($found_banner_info['t']) ? trim($found_banner_info['t']) : '';
        $og_title = "🔥 ¡Equipate Ya! " . $banner_title . " en Pixis Informática";
        $og_description = "¡No dejes pasar esta oportunidad! Descubrí los mejores productos en " . $banner_title . " con envíos a todo el país y el mejor precio local.";
        
        if ($found_banner_img !== '') {
            $og_image = build_absolute_url($domain, $found_banner_img);
        }
    }
}

// Fallback logic
if (!$og_title) {
    $og_title = $fallback_title;
}
if (!$og_description) {
    $og_description = $fallback_description;
}
if (!$og_image) {
    $og_image = $fallback_image;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($og_title); ?></title>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Pixis Informática">
    <meta property="og:locale" content="es_AR">
    <meta property="og:title" content="<?php echo htmlspecialchars($og_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($og_description); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($redirect_url); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($og_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($og_description); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($og_description); ?>">
    <noscript>
        <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($redirect_url); ?>">
    </noscript>
</head>
<body>
    <script>
        window.location.replace(<?php echo json_encode($redirect_url); ?>);
    </script>
</body>
</html>
