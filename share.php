<?php
/**
 * PIXIS INFORMATICA — Mediador de Metadatos Dinámicos (SEO Social)
 * 
 * Este archivo intercepta las solicitudes de scrapers de redes sociales
 * (WhatsApp, Facebook, Twitter, etc.) para entregarles metadatos Open Graph
 * y Twitter Cards dinámicos leídos de los archivos JSON del sistema.
 * 
 * Si un usuario normal (navegador real) ingresa a este enlace, es redirigido
 * automáticamente al frontend (index.html) conservando los parámetros de búsqueda.
 */

// 1. Cabeceras Anti-Caché y Codificación (Evitar cacheo agresivo y caracteres rotos)
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Determinar el dominio base del sitio de manera dinámica
$domain = 'https://pixistech.store';
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $domain = $protocol . $_SERVER['HTTP_HOST'];
}

// 2. Filtro de Agentes (Filtro Temprano de Bots)
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$is_bot = preg_match('/(WhatsApp|facebookexternalhit|Twitterbot|Discordbot|LinkedInBot|TelegramBot|Slackbot|Googlebot|bingbot)/i', $user_agent);

if (!$is_bot) {
    // Si NO es un bot, redirigir inmediatamente al frontend con sus parámetros intactos
    $query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: ' . $domain . '/index.html' . $query);
    exit;
}

// 3. Funciones Helper de Formateo y Limpieza
function format_price($price_val) {
    if (is_numeric($price_val)) {
        return '$' . number_format((float)$price_val, 2, ',', '.');
    }
    return $price_val;
}

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

// 4. Lógica de Asignación de Variables
$og_title = null;
$og_description = null;
$og_image = null;

// CASO A: VISTA DE PRODUCTO (?producto=slug/id)
if (isset($_GET['producto'])) {
    $producto_query = trim($_GET['producto']);
    $found_product = null;

    if (!empty($producto_query)) {
        $products_file = __DIR__ . '/data/products.json';
        if (file_exists($products_file)) {
            $products_data = json_decode(file_get_contents($products_file), true);
            if (is_array($products_data)) {
                foreach ($products_data as $p) {
                    if ((isset($p['id']) && strcasecmp($p['id'], $producto_query) === 0) || 
                        (isset($p['slug']) && strcasecmp($p['slug'], $producto_query) === 0)) {
                        $found_product = $p;
                        break;
                    }
                }
            }
        }
    }

    if ($found_product) {
        $p_title = isset($found_product['title']) ? $found_product['title'] : '';
        
        $p_price = '';
        if (isset($found_product['price']) && $found_product['price'] > 0) {
            $p_price = format_price($found_product['price']);
        } elseif (isset($found_product['priceNum']) && (int)$found_product['priceNum'] > 0) {
            $p_price = format_price($found_product['priceNum']);
        } elseif (isset($found_product['priceVisible'])) {
            $p_price = $found_product['priceVisible'];
        }
        
        if (!empty($p_price)) {
            $og_title = $p_title . " - Pixis Informática | Precio especial: " . $p_price;
        } else {
            $og_title = $p_title . " - Pixis Informática";
        }
        
        $og_description = isset($found_product['desc']) ? clean_description($found_product['desc']) : '';
        if (empty($og_description)) {
            $og_description = "Comprá " . $p_title . " al mejor precio en Pixis Informática. Hardware de alto rendimiento en Santiago del Estero.";
        }
        
        if (!empty($found_product['img'])) {
            $img_path = ltrim($found_product['img'], '/');
            $img_path = str_replace('\\', '/', $img_path);
            $og_image = $domain . '/' . $img_path;
        }
    }
}

// CASO B: VISTA DE CATEGORÍA (?categoria=id)
if (!$og_title && isset($_GET['categoria'])) {
    $categoria_query = trim($_GET['categoria']);
    $found_category = null;

    if (!empty($categoria_query)) {
        $categories_file = __DIR__ . '/data/categories.json';
        if (file_exists($categories_file)) {
            $categories_data = json_decode(file_get_contents($categories_file), true);
            if (is_array($categories_data)) {
                foreach ($categories_data as $cat) {
                    if (isset($cat['id']) && strcasecmp($cat['id'], $categoria_query) === 0) {
                        $found_category = $cat;
                        break;
                    }
                }
            }
        }
    }

    if ($found_category) {
        $cat_name = $found_category['name'];
        $og_title = "Categoría: " . $cat_name . " - Pixis Informática";
        $og_description = "Explorá nuestra categoría de " . $cat_name . " en Pixis Informática. Encontrá los mejores precios y hardware de alto rendimiento.";
        
        if (!empty($found_category['customIcon'])) {
            $img_path = ltrim($found_category['customIcon'], '/');
            $img_path = str_replace('\\', '/', $img_path);
            $og_image = $domain . '/' . $img_path;
        }
    }
}

// CASO C: VISTA DE BANNER / PROMOCIÓN (?banner=id)
if (!$og_title && isset($_GET['banner'])) {
    $banner_query = trim($_GET['banner']);
    $found_banner_info = null;
    $found_banner_img = '';

    if (!empty($banner_query)) {
        $site_file = __DIR__ . '/data/site.json';
        if (file_exists($site_file)) {
            $site_data = json_decode(file_get_contents($site_file), true);
            if (is_array($site_data)) {
                if (isset($site_data['banners'][$banner_query])) {
                    $found_banner_info = $site_data['banners'][$banner_query];
                }
                
                $carousels = array_merge(
                    isset($site_data['carouselTop']) ? $site_data['carouselTop'] : array(),
                    isset($site_data['carouselBottom']) ? $site_data['carouselBottom'] : array()
                );
                foreach ($carousels as $slide) {
                    if (isset($slide['bannerId']) && strcasecmp($slide['bannerId'], $banner_query) === 0) {
                        $found_banner_img = $slide['imgPc'];
                        break;
                    }
                }
            }
        }
    }

    if ($found_banner_info) {
        $banner_title = $found_banner_info['t'];
        $og_title = "🔥 ¡Equipate Ya! " . $banner_title . " en Pixis Informática";
        $og_description = "¡No dejes pasar esta oportunidad! Descubrí los mejores productos en " . $banner_title . " con envíos a todo el país y el mejor precio local.";
        
        if (!empty($found_banner_img)) {
            $img_path = ltrim($found_banner_img, '/');
            $img_path = str_replace('\\', '/', $img_path);
            $og_image = $domain . '/' . $img_path;
        }
    }
}

// CASO D: VISTA POR DEFECTO (Home / Fallback)
if (!$og_title) {
    $og_title = "Pixis Informática | Especialistas en Computación";
    $og_description = "Tienda de computación online en Santiago del Estero. Venta de accesorios gamer, hardware de alto rendimiento y servicio técnico especializado.";
    $og_image = $domain . '/img/TECH24.png';
}

if (!$og_image) {
    $og_image = $domain . '/img/TECH24.png';
}

// Construcción del og:url canonical (evitando incluir el parámetro de cache-buster 'cc')
$clean_uri = $_SERVER['REQUEST_URI'];
// Remover parámetro cc vía regex
$clean_uri = preg_replace('/([?&])cc=[^&]*(&?)/', '$1', $clean_uri);
$clean_uri = rtrim($clean_uri, '?&');
// Reemplazar share.php por index.html para apuntar a la app interactiva real
$clean_uri = str_replace('share.php', 'index.html', $clean_uri);
$clean_uri = str_replace('//', '/', $clean_uri);
$og_url = $domain . $clean_uri;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($og_title); ?></title>
    
    <!-- Open Graph (Facebook, WhatsApp) -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Pixis Informática">
    <meta property="og:locale" content="es_AR">
    <meta property="og:title" content="<?php echo htmlspecialchars($og_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($og_description); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($og_url); ?>">
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($og_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($og_description); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">
    
    <!-- Standard SEO Metas -->
    <meta name="description" content="<?php echo htmlspecialchars($og_description); ?>">
</head>
<body>
    <!-- Fallback JavaScript para redirección si de alguna manera accede un navegador real -->
    <script>
        window.location.replace(<?php echo json_encode($og_url); ?>);
    </script>
</body>
</html>
