<?php
/**
 * Plugin Name: Altus Site Redesign
 * Description: Renders all inner pages and the homepage in the new Altus design language (pill header, cinematic hero, new sections) while preserving every page's real content. Footer (Elementor template 108) is kept as-is. Deactivating restores the previous design instantly.
 * Version: 1.0.41
 * Author: Altus Web Solutions
 */
defined('ABSPATH') || exit;

define('ALX_SR_DIR', plugin_dir_path(__FILE__));
define('ALX_SR_URL', plugin_dir_url(__FILE__));
define('ALX_SR_VER', '1.0.41');

/* ---------- template takeover ---------- */
function alx_sr_template($template) {
    if (is_admin() || !is_singular(array('page', 'post'))) return $template;
    $pid = get_queried_object_id();
    if (get_post_meta($pid, '_alx_redesign_skip', true)) return $template;
    $file = ALX_SR_DIR . 'templates/page-redesign.php';
    if (file_exists($file)) return $file;
    return $template;
}
add_filter('template_include', 'alx_sr_template', 99);

function alx_sr_assets() {
    if (is_admin() || !is_singular(array('page', 'post'))) return;
    $pid = get_queried_object_id();
    if (get_post_meta($pid, '_alx_redesign_skip', true)) return;
    wp_enqueue_style('alx-redesign', ALX_SR_URL . 'assets/redesign.css', array(), ALX_SR_VER);
    wp_enqueue_script('alx-redesign', ALX_SR_URL . 'assets/redesign.js', array(), ALX_SR_VER, true);
}
add_action('wp_enqueue_scripts', 'alx_sr_assets', 20);

/* New-design typography on every page/post, including bespoke (skipped) pages */
function alx_sr_type_assets() {
    if (is_admin() || !is_singular(array('page', 'post'))) return;
    wp_enqueue_style('alx-type', ALX_SR_URL . 'assets/type.css', array(), ALX_SR_VER);
}
add_action('wp_enqueue_scripts', 'alx_sr_type_assets', 21);

/* ---------- activation: medical billing bespoke page ---------- */
function alx_sr_activate() {
    // Backup + replace medical billing page (8994) with the approved bespoke design.
    $pid = 8994;
    $p = get_post($pid);
    if ($p && !get_option('alx_sr_8994_backup_done')) {
        update_option('alx_sr_8994_backup', array(
            'content' => $p->post_content,
            'time' => current_time('mysql'),
        ), false);
        update_option('alx_sr_8994_backup_done', 1);
    }
    $bundle = ALX_SR_DIR . 'bundled/medical-billing-content.html';
    if ($p && file_exists($bundle)) {
        wp_update_post(array('ID' => $pid, 'post_content' => file_get_contents($bundle)));
        update_post_meta($pid, '_alx_redesign_skip', '1');
    }
    // Bespoke approved homepage design for the front page (8986).
    $hpid = 8986;
    $hp = get_post($hpid);
    if ($hp && !get_option('alx_sr_8986_backup_done')) {
        update_option('alx_sr_8986_backup', array(
            'content' => $hp->post_content,
            'time' => current_time('mysql'),
        ), false);
        update_option('alx_sr_8986_backup_done', 1);
    }
    $hbundle = ALX_SR_DIR . 'bundled/homepage-content.html';
    if ($hp && file_exists($hbundle)) {
        wp_update_post(array('ID' => $hpid, 'post_content' => file_get_contents($hbundle)));
        update_post_meta($hpid, '_alx_redesign_skip', '1');
    }
    if (function_exists('w3tc_flush_all')) w3tc_flush_all();
}
register_activation_hook(__FILE__, 'alx_sr_activate');
// Refresh bespoke bundles whenever the plugin version changes, even if the
// activation hook was skipped (e.g. "Replace current with uploaded" keeps
// the plugin active without re-running activation).
add_action('init', 'alx_sr_maybe_refresh');
function alx_sr_maybe_refresh() {
    if (get_option('alx_sr_ver') === ALX_SR_VER) return;
    // Only refresh as an admin: wp_update_post as a visitor would run kses
    // and strip parts of the bespoke bundles.
    if (!current_user_can('manage_options')) return;
    $pid = 8994;
    $bundle = ALX_SR_DIR . 'bundled/medical-billing-content.html';
    if (get_post($pid) && file_exists($bundle)) {
        wp_update_post(array('ID' => $pid, 'post_content' => file_get_contents($bundle)));
        update_post_meta($pid, '_alx_redesign_skip', '1');
    }
    $hpid = 8986;
    $hbundle = ALX_SR_DIR . 'bundled/homepage-content.html';
    if (get_post($hpid) && file_exists($hbundle)) {
        wp_update_post(array('ID' => $hpid, 'post_content' => file_get_contents($hbundle)));
        update_post_meta($hpid, '_alx_redesign_skip', '1');
    }
    update_option('alx_sr_ver', ALX_SR_VER);
    if (function_exists('w3tc_flush_all')) w3tc_flush_all();
}
function alx_sr_bust($post_id) {
    delete_post_meta($post_id, '_alx_rh');
}
add_action('save_post', 'alx_sr_bust');

/* ---------- public helpers ---------- */
function alx_sr_hero_sub($post_id) {
    $m = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
    if ($m) return $m;
    $ex = get_post_field('post_excerpt', $post_id);
    if ($ex) return $ex;
    return '';
}

function alx_sr_content($post_id) {
    if (isset($_GET['alx_refresh']) && current_user_can('manage_options')) {
        delete_post_meta($post_id, '_alx_rh');
    }
    $cached = get_post_meta($post_id, '_alx_rh', true);
    $rendered = '';
    // Rendered content (Elementor builds via the_content filter)
    $old = $GLOBALS['post'] ?? null;
    $p = get_post($post_id);
    if ($p) {
        $GLOBALS['post'] = $p;
        setup_postdata($p);
        $rendered = apply_filters('the_content', $p->post_content);
        wp_reset_postdata();
        if ($old) $GLOBALS['post'] = $old;
    }
    $hash = md5($rendered);
    if (is_array($cached) && isset($cached['hash'], $cached['html'], $cached['ver'])
        && $cached['hash'] === $hash && $cached['ver'] === ALX_SR_VER) {
        return $cached['html'];
    }
    $html = alx_sr_transform($rendered);
    // Curated tools index for the Free Tools page (8850) — its content is a single line.
    if ((int) $post_id === 8850) {
        $html .= alx_sr_tools_index();
    }
    update_post_meta($post_id, '_alx_rh', array('hash' => $hash, 'html' => $html, 'ver' => ALX_SR_VER));
    return $html;
}

function alx_sr_tools_index() {
    $tools = array(
        array('Free Website Grader', 'Grade your homepage on speed, SEO, mobile and security — score out of 100 in 60 seconds.', '/tools/website-grader/'),
        array('Privacy Policy Generator', 'Generate a tailored privacy policy for your UK small business in minutes.', '/tools/privacy-policy-generator/'),
        array('Free Invoice Generator', 'Create professional invoices in seconds — free, no signup.', '/free-invoice-generator/'),
        array('School Website Compliance Check', 'Free DfE/Ofsted compliance check for UK school websites.', '/free-school-website-compliance-check/'),
    );
    $h = '<section class="sec sec-cloud"><div class="wrap"><div class="alx-tools-grid">';
    foreach ($tools as $t) {
        $h .= '<a class="alx-tool-card reveal" href="' . esc_url(home_url($t[2])) . '">'
            . '<h3>' . esc_html($t[0]) . '</h3><p>' . esc_html($t[1]) . '</p>'
            . '<span class="lm">Open tool →</span></a>';
    }
    return $h . '</div></div></section>';
}

function alx_sr_footer() {
    if (class_exists('\\Elementor\\Plugin')) {
        try {
            $f = \Elementor\Plugin::instance()->frontend->get_builder_content(108);
            if (trim($f) !== '') return $f;
        } catch (Exception $e) {}
    }
    ob_start();
    get_footer();
    return ob_get_clean();
}

/* ================================================================
   CONTENT TRANSFORMER — Elementor/block HTML -> new design blocks
   ================================================================ */
function alx_sr_transform($html) {
    if (trim($html) === '') return '';
    // strip style/link/script blocks up front (walker skips them anyway;
    // huge inline style blocks can choke libxml)
    $html = preg_replace('/<style\b[^>]*>.*?<\/style\s*>/is', '', $html);
    $html = preg_replace('/<(link|script|noscript)\b[^>]*>(?:.*?<\/\1\s*>)?/is', '', $html);
    if (trim($html) === '') return '';
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $body = $dom->getElementsByTagName('body')->item(0);
    // With NOIMPLIED and no <body>, documentElement is just the first element,
    // so walk the document node itself to reach all top-level siblings.
    $root = $body ? $body : $dom;
    $blocks = array();
    alx_sr_walk($root, $blocks);
    return alx_sr_assemble($blocks);
}

function alx_sr_cls($node) {
    if ($node->nodeType !== XML_ELEMENT_NODE) return '';
    return ' ' . strtolower($node->getAttribute('class')) . ' ';
}

function alx_sr_has_cls($node, $token) {
    if ($node->nodeType !== XML_ELEMENT_NODE) return false;
    return strpos(alx_sr_cls($node), ' ' . $token . ' ') !== false;
}

function alx_sr_skip($node) {
    if ($node->nodeType !== XML_ELEMENT_NODE) return false;
    $tag = strtolower($node->tagName);
    if (in_array($tag, array('script', 'style', 'nav', 'noscript', 'link', 'meta'), true)) return true;
    $tokens = array('site-header', 'post-header', 'entry-header', 'page-header',
        'wpr-nav-menu', 'elementor-widget-nav-menu', 'wpr-logo', 'elementor-widget-theme-site-logo',
        'elementor-widget-wpr-logo', 'elementor-spacer', 'elementor-widget-spacer', 'elementor-widget-divider',
        'elementor-widget-social-icons', 'wpr-post-info', 'elementor-widget-wpr-post-info', 'post-header',
        'wpr-page-list', 'elementor-widget-wpr-page-list', 'elementor-widget-search-form', 'elementor-widget-icon',
        'elementor-widget-wpr-page-list');
    foreach ($tokens as $t) { if (alx_sr_has_cls($node, $t)) return true; }
    return false;
}

function alx_sr_has($node, $selector) {
    // tiny "contains tag/class" check on subtree
    if ($node->nodeType !== XML_ELEMENT_NODE) return false;
    $tag = strtolower($node->tagName);
    if ($tag === $selector) return true;
    $c = alx_sr_cls($node);
    if (strpos($c, ' ' . ltrim($selector, '.') . ' ') !== false && $selector[0] === '.') return true;
    foreach ($node->childNodes as $ch) { if (alx_sr_has($ch, $selector)) return true; }
    return false;
}

function alx_sr_interactive($node) {
    if ($node->nodeType !== XML_ELEMENT_NODE) return false;
    $tag = strtolower($node->tagName);
    if (in_array($tag, array('form', 'iframe', 'video', 'input', 'select', 'textarea'), true)) return true;
    $c = alx_sr_cls($node);
    foreach (array('swiper', 'elementor-widget-wpr', 'wpr-form', 'wpr-grid', 'elementor-widget-form',
        'elementor-widget-shortcode', 'elementor-widget-html', 'elementor-widget-video',
        'wp-block-shortcode', 'altus-tool', 'lna-', 'wpr-carousel') as $p) {
        if (strpos($c, $p) !== false) return true;
    }
    foreach (array('form', 'iframe', 'video', 'input', 'select', 'textarea') as $t) {
        if (alx_sr_has($node, $t)) return true;
    }
    return false;
}

function alx_sr_outer($node) {
    $d = new DOMDocument();
    $d->appendChild($d->importNode($node, true));
    return $d->saveHTML();
}

function alx_sr_clean_inline($node) {
    // returns cleaned inner HTML keeping only safe inline tags
    $d = new DOMDocument();
    $wrap = $d->createElement('div');
    foreach (iterator_to_array($node->childNodes) as $ch) { $wrap->appendChild($d->importNode($ch, true)); }
    $d->appendChild($wrap);
    $xp = new DOMXPath($d);
    foreach (iterator_to_array($xp->query('//*')) as $el) {
        if (!$el->parentNode) continue;
        $tag = strtolower($el->tagName);
        if ($tag === 'div' && $el === $wrap) continue;
        if (!in_array($tag, array('a', 'strong', 'b', 'em', 'i', 'u', 'br', 'code', 'img'), true)) {
            while ($el->firstChild) { $el->parentNode->insertBefore($el->firstChild, $el); }
            $el->parentNode->removeChild($el);
            continue;
        }
        if ($tag === 'img') {
            foreach (iterator_to_array($el->attributes) as $a) {
                if (!in_array(strtolower($a->name), array('src', 'alt', 'title', 'loading', 'width', 'height'), true)) $el->removeAttribute($a->name);
            }
        } elseif ($tag === 'a') {
            foreach (iterator_to_array($el->attributes) as $a) {
                if (!in_array(strtolower($a->name), array('href', 'target', 'rel', 'title'), true)) $el->removeAttribute($a->name);
            }
        } else {
            foreach (iterator_to_array($el->attributes) as $a) { $el->removeAttribute($a->name); }
        }
    }
    $html = '';
    foreach (iterator_to_array($wrap->childNodes) as $ch) { $html .= $d->saveHTML($ch); }
    return trim($html);
}

function alx_sr_walk($node, &$blocks) {
    foreach (iterator_to_array($node->childNodes) as $ch) {
        if ($ch->nodeType === XML_TEXT_NODE) {
            $t = trim($ch->textContent);
            if ($t !== '') $blocks[] = array('t' => 'p', 'html' => esc_html($t));
            continue;
        }
        if ($ch->nodeType !== XML_ELEMENT_NODE) continue;
        $tag = strtolower($ch->tagName);
        if (alx_sr_skip($ch)) continue;

        // headings
        if (in_array($tag, array('h1', 'h2', 'h3', 'h4'), true) || strpos(alx_sr_cls($ch), 'elementor-heading-title') !== false) {
            $lvl = $tag === 'h1' ? 2 : (int) substr($tag, 1);
            if (strpos(alx_sr_cls($ch), 'elementor-heading-title') !== false && !in_array($tag, array('h1','h2','h3','h4'), true)) $lvl = 2;
            $txt = trim(preg_replace('/\s+/', ' ', $ch->textContent));
            if ($txt !== '') $blocks[] = array('t' => 'h', 'lvl' => min(4, max(2, $lvl)), 'text' => $txt);
            continue;
        }
        if ($tag === 'p') {
            // Images wrapped in <p> (e.g. <p><img ...></p>, the standard WP editor markup):
            // emit them as image blocks first, then keep any remaining text as a paragraph.
            $pimgs = iterator_to_array($ch->getElementsByTagName('img'));
            if ($pimgs) {
                foreach ($pimgs as $im) { $b = alx_sr_img_block($im); if ($b) $blocks[] = $b; }
                $clone = $ch->cloneNode(true);
                foreach (iterator_to_array($clone->getElementsByTagName('img')) as $im) {
                    $im->parentNode->removeChild($im);
                }
                $inner = alx_sr_clean_inline($clone);
                if (trim(strip_tags($inner)) !== '') $blocks[] = array('t' => 'p', 'html' => $inner);
                continue;
            }
            $inner = alx_sr_clean_inline($ch);
            if (trim(strip_tags($inner)) !== '') $blocks[] = array('t' => 'p', 'html' => $inner);
            continue;
        }
        if (in_array($tag, array('ul', 'ol'), true)) {
            $items = array();
            foreach (iterator_to_array($ch->childNodes) as $lich) {
                if ($lich->nodeType === XML_ELEMENT_NODE && strtolower($lich->tagName) === 'li') {
                    $items[] = alx_sr_clean_inline($lich);
                }
            }
            if (!$items) continue;
            $blocks[] = array('t' => 'list', 'ordered' => $tag === 'ol', 'items' => $items);
            continue;
        }
        if ($tag === 'img') {
            $b = alx_sr_img_block($ch);
            if ($b) $blocks[] = $b;
            continue;
        }
        if ($tag === 'figure') {
            $img = null; $cap = '';
            foreach ($ch->getElementsByTagName('img') as $im) { $img = $im; break; }
            foreach ($ch->getElementsByTagName('figcaption') as $fc) { $cap = trim($fc->textContent); break; }
            if ($img) { $b = alx_sr_img_block($img, $cap); if ($b) $blocks[] = $b; }
            continue;
        }
        if ($tag === 'a') {
            $c = alx_sr_cls($ch);
            $isBtn = strpos($c, 'elementor-button') !== false || strpos($c, 'wp-block-button__link') !== false || strpos($c, 'btn') !== false;
            $hasImg = alx_sr_has($ch, 'img');
            $txt = trim(preg_replace('/\s+/', ' ', $ch->textContent));
            if ($isBtn && $txt !== '') {
                $blocks[] = array('t' => 'btn', 'href' => $ch->getAttribute('href'), 'text' => $txt);
                continue;
            }
            if ($hasImg && $txt === '') {
                foreach ($ch->getElementsByTagName('img') as $im) { $b = alx_sr_img_block($im, '', $ch->getAttribute('href')); if ($b) $blocks[] = $b; break; }
                continue;
            }
            if ($txt !== '') { $blocks[] = array('t' => 'p', 'html' => alx_sr_clean_inline($ch)); continue; }
            continue;
        }
        if ($tag === 'blockquote') {
            $blocks[] = array('t' => 'quote', 'html' => alx_sr_clean_inline($ch));
            continue;
        }
        if ($tag === 'hr') { $blocks[] = array('t' => 'hr'); continue; }
        if ($tag === 'table') { $blocks[] = array('t' => 'raw', 'html' => alx_sr_outer($ch)); continue; }
        // Elementor / Royal button widgets -> new-design pill buttons
        if (alx_sr_has_cls($ch, 'elementor-widget-button') || alx_sr_has_cls($ch, 'elementor-widget-wpr-button')) {
            foreach ($ch->getElementsByTagName('a') as $a) {
                $txt = trim(preg_replace('/\s+/', ' ', $a->textContent));
                if ($txt !== '') { $blocks[] = array('t' => 'btn', 'href' => $a->getAttribute('href'), 'text' => $txt); break; }
            }
            continue;
        }
        if (alx_sr_interactive($ch)) { $blocks[] = array('t' => 'raw', 'html' => alx_sr_outer($ch)); continue; }
        // default: descend
        alx_sr_walk($ch, $blocks);
    }
}

function alx_sr_img_block($img, $cap = '', $link = '') {
    $src = $img->getAttribute('src');
    if (!$src || strpos($src, 'data:image') === 0) $src = $img->getAttribute('data-src');
    if (!$src || strpos($src, 'data:image') === 0) return null;
    return array('t' => 'img', 'src' => $src,
        'srcset' => $img->getAttribute('srcset'),
        'alt' => $img->getAttribute('alt'), 'cap' => $cap, 'link' => $link);
}

function alx_sr_assemble($blocks) {
    if (!$blocks) return '';
    // split into sections at h2
    $sections = array();
    $cur = array('title' => null, 'blocks' => array());
    foreach ($blocks as $b) {
        if ($b['t'] === 'h' && $b['lvl'] === 2) {
            if ($cur['title'] !== null || $cur['blocks']) $sections[] = $cur;
            $cur = array('title' => $b['text'], 'blocks' => array());
        } else {
            $cur['blocks'][] = $b;
        }
    }
    $sections[] = $cur;
    // drop empty sections
    $sections = array_values(array_filter($sections, function ($s) { return $s['title'] !== null || $s['blocks']; }));
    if (!$sections) return '';
    $out = '';
    $alt = false;
    foreach ($sections as $s) {
        // skip sections that are only a heading with nothing else and look like nav
        $cls = $alt ? 'sec sec-cloud' : 'sec sec-light';
        $alt = !$alt;
        $out .= '<section class="' . $cls . '"><div class="wrap alx-narrow">';
        if ($s['title'] !== null) {
            $out .= '<h2 class="reveal">' . esc_html($s['title']) . '</h2>';
        }
        foreach ($s['blocks'] as $b) { $out .= alx_sr_block($b); }
        $out .= '</div></section>';
    }
    return $out;
}

function alx_sr_block($b) {
    switch ($b['t']) {
        case 'h':
            $tag = 'h' . $b['lvl'];
            return '<' . $tag . ' class="alx-h' . $b['lvl'] . ' reveal">' . esc_html($b['text']) . '</' . $tag . '>';
        case 'p':
            return '<p class="reveal">' . $b['html'] . '</p>';
        case 'list':
            $tag = $b['ordered'] ? 'ol' : 'ul';
            $h = '<' . $tag . ' class="alx-list reveal">';
            foreach ($b['items'] as $it) { $h .= '<li>' . $it . '</li>'; }
            return $h . '</' . $tag . '>';
        case 'img':
            $img = '<img src="' . esc_url($b['src']) . '"'
                . ($b['srcset'] ? ' srcset="' . esc_attr($b['srcset']) . '"' : '')
                . ' alt="' . esc_attr($b['alt']) . '" loading="lazy" decoding="async">';
            if ($b['link']) $img = '<a href="' . esc_url($b['link']) . '">' . $img . '</a>';
            $cap = $b['cap'] ? '<figcaption>' . esc_html($b['cap']) . '</figcaption>' : '';
            return '<figure class="alx-fig reveal">' . $img . $cap . '</figure>';
        case 'btn':
            return '<div class="reveal" style="margin:26px 0"><a class="btn-pill" href="' . esc_url($b['href'] ? $b['href'] : '#') . '">' . esc_html($b['text']) . '</a></div>';
        case 'quote':
            return '<blockquote class="alx-quote reveal">' . $b['html'] . '</blockquote>';
        case 'hr':
            return '<hr class="alx-hr">';
        case 'raw':
        default:
            return '<div class="alx-raw reveal">' . $b['html'] . '</div>';
    }
}

/* Bespoke intro overlay (output directly to avoid post_content filters stripping it) */
function alx_sr_intro_overlay() {
    if (is_admin() || !is_singular(array('page', 'post'))) return;
    $pid = get_queried_object_id();
    if (!$pid || !in_array((int)$pid, array(8986, 8994), true)) return;
    if (!get_post_meta($pid, '_alx_redesign_skip', true)) return;
    echo '<div class="intro" id="intro"><div class="intro-stage"><div class="intro-logo" id="introLogo"><img src="https://altuswebsolutions.com/wp-content/uploads/2026/02/Altus-Web-Solutions-White-Logo-1.png" alt="Altus Web Solutions" loading="eager" data-no-lazy="1"></div><div class="intro-bar"><i></i></div></div></div>';
}
add_action('wp_body_open', 'alx_sr_intro_overlay', 1);

/* Plugin header — replaces theme header, visible on all pages */
function alx_sr_plugin_header() {
    if (is_admin() || !is_singular(array('page', 'post'))) return;
    $f = ALX_SR_DIR . 'parts/header-plugin.html';
    if (file_exists($f)) echo file_get_contents($f);
}
add_action('wp_body_open', 'alx_sr_plugin_header', 5);

function alx_sr_header_js() {
    if (is_admin() || !is_singular(array('page', 'post'))) return;
    $f = ALX_SR_DIR . 'parts/header-plugin.js';
    if (file_exists($f)) echo '<script>' . file_get_contents($f) . '</script>';
}
add_action('wp_footer', 'alx_sr_header_js', 5);


/* Mega menu CSS (from bundle) */
function alx_sr_mega_css() {
    if (is_admin() || !is_singular(array('page', 'post'))) return;
    echo '<style>' . file_get_contents(ALX_SR_DIR . 'parts/mega-menu.css') . '</style>';
}
add_action('wp_head', 'alx_sr_mega_css', 15);


/* TEMP: Set Elementor hero for page 9103 */
function alx_sr_set_hero() {
    if (!is_admin() || !current_user_can('manage_options')) return;
    if (!isset($_GET['alx_set_hero'])) return;
    $pid = 9103;
    $data = "[{\"id\": \"utzn3qq\", \"elType\": \"section\", \"settings\": {\"layout\": \"full_width\", \"content_width\": {\"unit\": \"px\", \"size\": 1460}}, \"elements\": [{\"id\": \"n1fusek\", \"elType\": \"column\", \"settings\": {\"_column_size\": 100, \"_inline_size\": null}, \"elements\": [{\"id\": \"nsi6d9d\", \"elType\": \"widget\", \"widgetType\": \"html\", \"settings\": {\"html\": \"<!-- =========================================================\\n     ALTUS WEB SOLUTIONS \\u2014 HERO ONLY\\n     Fully scoped for WordPress HTML Widget\\n     No Header / No Navigation\\n========================================================= -->\\n\\n<div id=\\\"altus-hero\\\">\\n\\n  <div class=\\\"altus-hero-bg\\\"></div>\\n\\n  <!-- Animated light streaks -->\\n  <span class=\\\"altus-streak altus-streak-1\\\"></span>\\n  <span class=\\\"altus-streak altus-streak-2\\\"></span>\\n\\n  <!-- Hero Content -->\\n  <div class=\\\"altus-hero-inner\\\">\\n\\n    <h1 class=\\\"altus-hero-title\\\">\\n\\n      <span class=\\\"altus-mask\\\">\\n        <span>\\n          We Build Websites\\n          <span class=\\\"altus-accent\\\">&amp;</span>\\n        </span>\\n      </span>\\n\\n      <span class=\\\"altus-mask\\\">\\n        <span>\\n          AI That\\n          <span class=\\\"altus-accent\\\">Grow</span>\\n        </span>\\n      </span>\\n\\n      <span class=\\\"altus-mask\\\">\\n        <span>Your Business</span>\\n      </span>\\n\\n    </h1>\\n\\n    <p class=\\\"altus-hero-sub\\\">\\n      Altus Web Solutions designs, builds and markets digital experiences UK businesses rely on \\u2014 from school websites to AI-powered customer engagement.\\n    </p>\\n\\n    <div class=\\\"altus-hero-ctas\\\">\\n\\n      <a class=\\\"altus-btn-primary\\\" href=\\\"https://altuswebsolutions.com/work/\\\">\\n        View Our Work\\n      </a>\\n\\n      <a class=\\\"altus-btn-outline\\\" href=\\\"https://altuswebsolutions.com/schedule-free-consultation/\\\">\\n        Speak With an Advisor\\n      </a>\\n\\n    </div>\\n\\n  </div>\\n\\n\\n  <!-- =====================================================\\n       TRUST BAR\\n  ====================================================== -->\\n\\n  <div class=\\\"altus-trust\\\">\\n\\n    <div class=\\\"altus-trust-inner\\\">\\n\\n      <span class=\\\"altus-trust-label\\\">\\n        TRUSTED BY AMBITIOUS BUSINESSES\\n      </span>\\n\\n      <div class=\\\"altus-marquee\\\">\\n\\n        <div class=\\\"altus-marquee-track\\\">\\n\\n          <span>SCHOOLS <b>\\u25cf</b></span>\\n          <span>HEALTHCARE</span>\\n          <span>REAL ESTATE</span>\\n          <span>E-COMMERCE <b>\\u25cf</b></span>\\n          <span>FINTECH</span>\\n          <span>TRADES</span>\\n          <span>HOSPITALITY <b>\\u25cf</b></span>\\n          <span>PROFESSIONAL SERVICES</span>\\n\\n          <!-- Duplicate for seamless animation -->\\n\\n          <span>SCHOOLS <b>\\u25cf</b></span>\\n          <span>HEALTHCARE</span>\\n          <span>REAL ESTATE</span>\\n          <span>E-COMMERCE <b>\\u25cf</b></span>\\n          <span>FINTECH</span>\\n          <span>TRADES</span>\\n          <span>HOSPITALITY <b>\\u25cf</b></span>\\n          <span>PROFESSIONAL SERVICES</span>\\n\\n          <span>SCHOOLS <b>\\u25cf</b></span>\\n          <span>HEALTHCARE</span>\\n          <span>REAL ESTATE</span>\\n          <span>E-COMMERCE <b>\\u25cf</b></span>\\n          <span>FINTECH</span>\\n          <span>TRADES</span>\\n          <span>HOSPITALITY <b>\\u25cf</b></span>\\n          <span>PROFESSIONAL SERVICES</span>\\n\\n          <span>SCHOOLS <b>\\u25cf</b></span>\\n          <span>HEALTHCARE</span>\\n          <span>REAL ESTATE</span>\\n          <span>E-COMMERCE <b>\\u25cf</b></span>\\n          <span>FINTECH</span>\\n          <span>TRADES</span>\\n          <span>HOSPITALITY <b>\\u25cf</b></span>\\n          <span>PROFESSIONAL SERVICES</span>\\n\\n        </div>\\n\\n      </div>\\n\\n    </div>\\n\\n  </div>\\n\\n</div>\\n\\n\\n<style>\\n\\n/* =========================================================\\n   ALTUS HERO \\u2014 GLOBAL VARIABLES\\n========================================================= */\\n\\n#altus-hero {\\n\\n  --altus-black: #0A0A12;\\n  --altus-navy: #242A36;\\n  --altus-brand: #10B3FE;\\n  --altus-brand-dark: #1D4ED8;\\n  --altus-white: #FFFFFF;\\n\\n  position: relative;\\n\\n  width: 100%;\\n  min-height: 100vh;\\n\\n  display: flex;\\n  align-items: center;\\n\\n  overflow: hidden;\\n\\n  background: #212329;\\n\\n  color: #FFFFFF;\\n\\n  font-family:\\n    'Aeonik',\\n    system-ui,\\n    -apple-system,\\n    BlinkMacSystemFont,\\n    'Segoe UI',\\n    sans-serif;\\n\\n  -webkit-font-smoothing: antialiased;\\n\\n  isolation: isolate;\\n\\n}\\n\\n\\n/* =========================================================\\n   ANIMATED BACKGROUND\\n========================================================= */\\n\\n#altus-hero .altus-hero-bg {\\n\\n  position: absolute;\\n\\n  inset: -6%;\\n\\n  z-index: -2;\\n\\n  will-change: transform;\\n\\n  background:\\n\\n    radial-gradient(\\n      1100px 520px at 70% 30%,\\n      rgba(16,179,254,.28),\\n      transparent 62%\\n    ),\\n\\n    radial-gradient(\\n      900px 620px at 20% 75%,\\n      rgba(64,42,160,.40),\\n      transparent 60%\\n    ),\\n\\n    radial-gradient(\\n      700px 500px at 85% 85%,\\n      rgba(16,179,254,.14),\\n      transparent 60%\\n    ),\\n\\n    linear-gradient(\\n      180deg,\\n      #0a0a18 0%,\\n      #212329 70%\\n    );\\n\\n  animation:\\n    altus-bg-drift 16s ease-in-out infinite alternate,\\n    altus-bg-in 2.6s cubic-bezier(.22,1,.36,1) 1.05s backwards;\\n\\n}\\n\\n\\n/* Background entrance */\\n\\n@keyframes altus-bg-in {\\n\\n  from {\\n    transform: scale(1.16);\\n  }\\n\\n  to {\\n    transform: scale(1);\\n  }\\n\\n}\\n\\n\\n/* Background slow movement */\\n\\n@keyframes altus-bg-drift {\\n\\n  from {\\n    transform:\\n      scale(1)\\n      translate(0,0);\\n  }\\n\\n  to {\\n    transform:\\n      scale(1.12)\\n      translate(-2.5%,2%);\\n  }\\n\\n}\\n\\n\\n/* Dark overlay */\\n\\n#altus-hero .altus-hero-bg::after {\\n\\n  content: \\\"\\\";\\n\\n  position: absolute;\\n\\n  inset: 0;\\n\\n  background:\\n    linear-gradient(\\n      180deg,\\n      rgba(7,7,15,.25) 0%,\\n      transparent 35%,\\n      rgba(7,7,15,.55) 100%\\n    );\\n\\n}\\n\\n\\n/* =========================================================\\n   LIGHT STREAKS\\n========================================================= */\\n\\n#altus-hero .altus-streak {\\n\\n  position: absolute;\\n\\n  height: 2px;\\n\\n  border-radius: 2px;\\n\\n  background:\\n    linear-gradient(\\n      90deg,\\n      transparent,\\n      rgba(16,179,254,.8),\\n      transparent\\n    );\\n\\n  filter: blur(1px);\\n\\n  animation:\\n    altus-streak-animation 9s linear infinite;\\n\\n  opacity: .7;\\n\\n  pointer-events: none;\\n\\n}\\n\\n\\n/* First streak */\\n\\n#altus-hero .altus-streak-1 {\\n\\n  width: 44vw;\\n\\n  top: 22%;\\n\\n  left: -10%;\\n\\n}\\n\\n\\n/* Second streak */\\n\\n#altus-hero .altus-streak-2 {\\n\\n  width: 34vw;\\n\\n  top: 58%;\\n\\n  left: 20%;\\n\\n  animation-delay: -4s;\\n\\n  animation-duration: 12s;\\n\\n}\\n\\n\\n@keyframes altus-streak-animation {\\n\\n  from {\\n    transform: translateX(-30vw);\\n  }\\n\\n  to {\\n    transform: translateX(120vw);\\n  }\\n\\n}\\n\\n\\n/* =========================================================\\n   HERO INNER\\n========================================================= */\\n\\n#altus-hero .altus-hero-inner {\\n\\n  position: relative;\\n\\n  z-index: 2;\\n\\n  width: 100%;\\n\\n  max-width: 1460px;\\n\\n  margin: 0 auto;\\n\\n  padding:\\n    150px\\n    32px\\n    120px;\\n\\n}\\n\\n\\n/* =========================================================\\n   TITLE MASK ANIMATION\\n========================================================= */\\n\\n#altus-hero .altus-mask {\\n\\n  display: block;\\n\\n  overflow: hidden;\\n\\n}\\n\\n\\n#altus-hero .altus-mask > span {\\n\\n  display: block;\\n\\n  transform: translateY(112%);\\n\\n  animation:\\n    altus-rise-up\\n    1.05s\\n    cubic-bezier(.22,1,.36,1)\\n    1.75s\\n    both;\\n\\n}\\n\\n\\n/* Individual line delays */\\n\\n#altus-hero .altus-mask:nth-child(2) > span {\\n\\n  animation-delay: 1.89s;\\n\\n}\\n\\n\\n#altus-hero .altus-mask:nth-child(3) > span {\\n\\n  animation-delay: 2.03s;\\n\\n}\\n\\n\\n@keyframes altus-rise-up {\\n\\n  to {\\n    transform: translateY(0);\\n  }\\n\\n}\\n\\n\\n/* =========================================================\\n   HERO TITLE\\n========================================================= */\\n\\n#altus-hero .altus-hero-title {\\n\\n  margin: 0;\\n\\n  padding: 0;\\n\\n  font-size:\\n    clamp(44px, 7vw, 104px);\\n\\n  font-weight: 800;\\n\\n  letter-spacing: -.03em;\\n\\n  line-height: 1.04;\\n\\n  color: #FFFFFF;\\n\\n}\\n\\n\\n/* Blue highlighted words */\\n\\n#altus-hero .altus-accent {\\n\\n  color: #10B3FE;\\n\\n}\\n\\n\\n/* =========================================================\\n   DESCRIPTION\\n========================================================= */\\n\\n#altus-hero .altus-hero-sub {\\n\\n  margin:\\n    26px\\n    0\\n    0;\\n\\n  max-width: 600px;\\n\\n  color:\\n    rgba(255,255,255,.72);\\n\\n  font-size: 17px;\\n\\n  line-height: 1.6;\\n\\n  font-weight: 400;\\n\\n  opacity: 0;\\n\\n  animation:\\n    altus-fade-up\\n    1s\\n    ease\\n    2.4s\\n    forwards;\\n\\n}\\n\\n\\n@keyframes altus-fade-up {\\n\\n  from {\\n\\n    opacity: 0;\\n\\n    transform:\\n      translateY(18px);\\n\\n  }\\n\\n  to {\\n\\n    opacity: 1;\\n\\n    transform: none;\\n\\n  }\\n\\n}\\n\\n\\n/* =========================================================\\n   CTA BUTTONS\\n========================================================= */\\n\\n#altus-hero .altus-hero-ctas {\\n\\n  display: flex;\\n\\n  gap: 14px;\\n\\n  margin-top: 36px;\\n\\n  flex-wrap: wrap;\\n\\n  opacity: 0;\\n\\n  animation:\\n    altus-fade-up\\n    1s\\n    ease\\n    2.55s\\n    forwards;\\n\\n}\\n\\n\\n/* Primary */\\n\\n#altus-hero .altus-btn-primary {\\n\\n  display: inline-flex;\\n\\n  align-items: center;\\n\\n  justify-content: center;\\n\\n  gap: 9px;\\n\\n  background: #10B3FE;\\n\\n  color: #FFFFFF;\\n\\n  font-size: 13.5px;\\n\\n  font-weight: 600;\\n\\n  padding:\\n    13px\\n    26px;\\n\\n  border-radius: 100px;\\n\\n  text-decoration: none;\\n\\n  white-space: nowrap;\\n\\n  transition:\\n    transform .22s ease,\\n    box-shadow .25s ease,\\n    background .25s ease;\\n\\n}\\n\\n\\n/* Primary hover */\\n\\n#altus-hero .altus-btn-primary:hover {\\n\\n  transform:\\n    translateY(-2px);\\n\\n  background: #1D4ED8;\\n\\n  box-shadow:\\n    0 12px 28px\\n    rgba(16,179,254,.4);\\n\\n}\\n\\n\\n/* Outline */\\n\\n#altus-hero .altus-btn-outline {\\n\\n  display: inline-flex;\\n\\n  align-items: center;\\n\\n  justify-content: center;\\n\\n  gap: 9px;\\n\\n  border:\\n    1.6px solid\\n    #10B3FE;\\n\\n  color: #FFFFFF;\\n\\n  font-size: 13.5px;\\n\\n  font-weight: 600;\\n\\n  padding:\\n    12px\\n    26px;\\n\\n  border-radius: 100px;\\n\\n  text-decoration: none;\\n\\n  white-space: nowrap;\\n\\n  transition:\\n    .25s;\\n\\n}\\n\\n\\n/* Outline hover */\\n\\n#altus-hero .altus-btn-outline:hover {\\n\\n  background: #10B3FE;\\n\\n  transform:\\n    translateY(-2px);\\n\\n  box-shadow:\\n    0 12px 28px\\n    rgba(16,179,254,.35);\\n\\n}\\n\\n\\n/* =========================================================\\n   TRUST BAR\\n========================================================= */\\n\\n#altus-hero .altus-trust {\\n\\n  position: absolute;\\n\\n  bottom: 0;\\n\\n  left: 0;\\n\\n  right: 0;\\n\\n  z-index: 3;\\n\\n  border-top:\\n    1px solid\\n    rgba(255,255,255,.1);\\n\\n  background:\\n    rgba(5,5,10,.45);\\n\\n  backdrop-filter:\\n    blur(8px);\\n\\n  -webkit-backdrop-filter:\\n    blur(8px);\\n\\n  opacity: 0;\\n\\n  animation:\\n    altus-fade-up\\n    1s\\n    ease\\n    2.75s\\n    forwards;\\n\\n}\\n\\n\\n/* Trust inner */\\n\\n#altus-hero .altus-trust-inner {\\n\\n  max-width: 1460px;\\n\\n  margin: 0 auto;\\n\\n  padding:\\n    18px\\n    32px;\\n\\n  display: flex;\\n\\n  align-items: center;\\n\\n  gap: 34px;\\n\\n  overflow: hidden;\\n\\n}\\n\\n\\n/* Trust label */\\n\\n#altus-hero .altus-trust-label {\\n\\n  font-size: 12px;\\n\\n  font-weight: 600;\\n\\n  letter-spacing: .14em;\\n\\n  color:\\n    rgba(255,255,255,.55);\\n\\n  white-space: nowrap;\\n\\n  flex-shrink: 0;\\n\\n}\\n\\n\\n/* =========================================================\\n   MARQUEE\\n========================================================= */\\n\\n#altus-hero .altus-marquee {\\n\\n  overflow: hidden;\\n\\n  flex: 1;\\n\\n  mask-image:\\n    linear-gradient(\\n      90deg,\\n      transparent,\\n      #000 8%,\\n      #000 92%,\\n      transparent\\n    );\\n\\n  -webkit-mask-image:\\n    linear-gradient(\\n      90deg,\\n      transparent,\\n      #000 8%,\\n      #000 92%,\\n      transparent\\n    );\\n\\n}\\n\\n\\n#altus-hero .altus-marquee-track {\\n\\n  display: flex;\\n\\n  gap: 54px;\\n\\n  width: max-content;\\n\\n  animation:\\n    altus-marquee\\n    26s\\n    linear\\n    infinite;\\n\\n}\\n\\n\\n#altus-hero .altus-marquee-track span {\\n\\n  font-size: 13.5px;\\n\\n  font-weight: 600;\\n\\n  letter-spacing: .1em;\\n\\n  color:\\n    rgba(255,255,255,.5);\\n\\n  white-space: nowrap;\\n\\n}\\n\\n\\n#altus-hero .altus-marquee-track span b {\\n\\n  color: #10B3FE;\\n\\n  font-weight: 600;\\n\\n}\\n\\n\\n@keyframes altus-marquee {\\n\\n  to {\\n\\n    transform:\\n      translateX(-50%);\\n\\n  }\\n\\n}\\n\\n\\n/* =========================================================\\n   TABLET\\n========================================================= */\\n\\n@media (max-width: 820px) {\\n\\n  #altus-hero {\\n\\n    min-height: 100svh;\\n\\n  }\\n\\n\\n  #altus-hero .altus-hero-inner {\\n\\n    padding:\\n      130px\\n      24px\\n      130px;\\n\\n  }\\n\\n\\n  #altus-hero .altus-hero-sub {\\n\\n    font-size: 16px;\\n\\n    max-width: 560px;\\n\\n  }\\n\\n\\n  #altus-hero .altus-trust-inner {\\n\\n    padding:\\n      16px\\n      24px;\\n\\n    gap: 20px;\\n\\n  }\\n\\n\\n  #altus-hero .altus-trust-label {\\n\\n    display: none;\\n\\n  }\\n\\n}\\n\\n\\n/* =========================================================\\n   MOBILE\\n========================================================= */\\n\\n@media (max-width: 600px) {\\n\\n  #altus-hero {\\n\\n    min-height: 100svh;\\n\\n  }\\n\\n\\n  #altus-hero .altus-hero-inner {\\n\\n    padding:\\n      120px\\n      20px\\n      120px;\\n\\n  }\\n\\n\\n  #altus-hero .altus-hero-title {\\n\\n    font-size:\\n      clamp(42px, 12vw, 64px);\\n\\n    line-height: 1.05;\\n\\n  }\\n\\n\\n  #altus-hero .altus-hero-sub {\\n\\n    margin-top: 22px;\\n\\n    font-size: 15px;\\n\\n    line-height: 1.65;\\n\\n  }\\n\\n\\n  #altus-hero .altus-hero-ctas {\\n\\n    margin-top: 30px;\\n\\n    flex-direction: column;\\n\\n    align-items: flex-start;\\n\\n  }\\n\\n\\n  #altus-hero .altus-btn-primary,\\n  #altus-hero .altus-btn-outline {\\n\\n    font-size: 13px;\\n\\n    padding:\\n      12px\\n      22px;\\n\\n  }\\n\\n\\n  #altus-hero .altus-trust-inner {\\n\\n    padding:\\n      15px\\n      20px;\\n\\n  }\\n\\n\\n  #altus-hero .altus-marquee-track span {\\n\\n    font-size: 12px;\\n\\n  }\\n\\n\\n  #altus-hero .altus-streak-1 {\\n\\n    width: 70vw;\\n\\n  }\\n\\n\\n  #altus-hero .altus-streak-2 {\\n\\n    width: 60vw;\\n\\n  }\\n\\n}\\n\\n\\n/* =========================================================\\n   ACCESSIBILITY \\u2014 REDUCE MOTION\\n========================================================= */\\n\\n@media (prefers-reduced-motion: reduce) {\\n\\n  #altus-hero *,\\n  #altus-hero *::before,\\n  #altus-hero *::after {\\n\\n    animation-duration: .01ms !important;\\n\\n    animation-iteration-count: 1 !important;\\n\\n    transition-duration: .01ms !important;\\n\\n  }\\n\\n\\n  #altus-hero .altus-mask > span {\\n\\n    transform: none;\\n\\n  }\\n\\n\\n  #altus-hero .altus-hero-sub,\\n  #altus-hero .altus-hero-ctas,\\n  #altus-hero .altus-trust {\\n\\n    opacity: 1;\\n\\n  }\\n\\n}\\n\\n</style>\\n\\n\\n<script>\\n\\n(function () {\\n\\n  \\\"use strict\\\";\\n\\n  /*\\n   * ALTUS HERO\\n   * Lightweight JavaScript enhancement.\\n   * The visual animation itself is CSS-based.\\n   */\\n\\n  const hero = document.getElementById(\\\"altus-hero\\\");\\n\\n  if (!hero) return;\\n\\n\\n  /*\\n   * Optional subtle mouse movement\\n   * on desktop for a cinematic depth effect.\\n   */\\n\\n  if (\\n    window.matchMedia &&\\n    window.matchMedia(\\\"(pointer:fine)\\\").matches\\n  ) {\\n\\n    const background =\\n      hero.querySelector(\\\".altus-hero-bg\\\");\\n\\n    let targetX = 0;\\n    let targetY = 0;\\n\\n    let currentX = 0;\\n    let currentY = 0;\\n\\n\\n    hero.addEventListener(\\n      \\\"mousemove\\\",\\n      function (event) {\\n\\n        const rect =\\n          hero.getBoundingClientRect();\\n\\n        const x =\\n          (event.clientX - rect.left)\\n          / rect.width\\n          - 0.5;\\n\\n        const y =\\n          (event.clientY - rect.top)\\n          / rect.height\\n          - 0.5;\\n\\n        targetX = x * 10;\\n        targetY = y * 6;\\n\\n      },\\n      { passive: true }\\n    );\\n\\n\\n    function animateDepth() {\\n\\n      currentX +=\\n        (targetX - currentX) * 0.035;\\n\\n      currentY +=\\n        (targetY - currentY) * 0.035;\\n\\n\\n      /*\\n       * Preserve the background's CSS animation\\n       * by using a subtle additional transform\\n       * through a CSS custom property.\\n       */\\n\\n      background.style.setProperty(\\n        \\\"--altus-mouse-x\\\",\\n        currentX + \\\"px\\\"\\n      );\\n\\n      background.style.setProperty(\\n        \\\"--altus-mouse-y\\\",\\n        currentY + \\\"px\\\"\\n      );\\n\\n\\n      requestAnimationFrame(animateDepth);\\n\\n    }\\n\\n\\n    animateDepth();\\n\\n  }\\n\\n\\n})();\\n\\n</script>\\n\"}}]}]}]";
    update_post_meta($pid, '_elementor_data', $data);
    update_post_meta($pid, '_elementor_edit_mode', 'builder');
    update_post_meta($pid, '_elementor_template_type', 'wp-page');
    wp_die('Hero set for page 9103');
}
add_action('admin_init', 'alx_sr_set_hero');

/* Critical palette + hero visibility (output directly, not via post_content) */
function alx_sr_critical_css() {
    if (is_admin() || !is_singular(array('page', 'post'))) return;
    ?>
    <style id="alx-critical">
    /* Hide Royal Elementor theme header - only our floating pill shows */
    .post-header { display:none !important; }
    .wpr-logo, a.wpr-logo-url { display:none !important; }
    /* Altus palette: blue/white on dark, blue/black on light - NO pink/purple */
    .dl-header a, .dl-header a:visited, .dl-header a:hover, .dl-header a:active { color:#fff !important; }
    .dl-header .dl-phone, .dl-header .dl-phone:visited { color:rgba(255,255,255,.92) !important; }
    .dl-header .dl-phone:hover { color:#10B3FE !important; }
    /* Scrolled header has white bg - use dark text */
    .dl-header.scrolled .dl-nav>li>a, .dl-header.scrolled .dl-nav>li>a:visited { color:#212329 !important; }
    .dl-header.scrolled .dl-phone, .dl-header.scrolled .dl-phone:visited { color:#212329 !important; }
    .dl-header.scrolled .dl-tagline { color:rgba(20,20,30,.62) !important; }
    .btn-pill, a.btn-pill, a.btn-pill:visited, .btn-pill:visited { background:#10B3FE !important; color:#fff !important; }
    .btn-pill:hover { background:#1D4ED8 !important; color:#fff !important; }
    .btn-outline, a.btn-outline, a.btn-outline:visited { border-color:rgba(255,255,255,.35) !important; color:#fff !important; }
    /* Hero visibility: ensure headline shows */
    .hero h1, .hero .mask, .hero .mask>span { opacity:1 !important; visibility:visible !important; }
    .hero .mask>span { transform:none !important; animation:none !important; }
    .hero-sub, .hero-ctas { opacity:1 !important; visibility:visible !important; transform:none !important; }
    .hero-inner { opacity:1 !important; visibility:visible !important; display:block !important; }
    .hero-inner h1 { color:#fff !important; opacity:1 !important; visibility:visible !important; display:block !important; position:relative !important; z-index:5 !important; font-size:clamp(42px,6vw,96px) !important; font-weight:800 !important; letter-spacing:-.03em !important; line-height:1.08 !important; }
    .hero-inner h1 br { display:block !important; }
    .hero-inner .hline { display:block !important; white-space:nowrap !important; }
    .hero-inner { padding:150px 32px 120px !important; }
    .hero { min-height:100vh !important; display:flex !important; align-items:flex-start !important; }
/* Hero background animations (streaks + drift) */
    .hero-vid { animation: alxVidDrift 16s ease-in-out infinite alternate !important; }
    @keyframes alxVidDrift { from { transform:scale(1) translate(0,0); } to { transform:scale(1.12) translate(-2.5%,2%); } }
    .streak { position:absolute !important; height:2px !important; border-radius:2px !important; background:linear-gradient(90deg,transparent,rgba(16,179,254,.8),transparent) !important; filter:blur(1px) !important; opacity:.7 !important; animation:alxStreak 9s linear infinite !important; }
    .streak.s1 { width:44vw !important; top:22% !important; left:-10% !important; }
    .streak.s2 { width:34vw !important; top:58% !important; left:20% !important; animation-delay:-4s !important; animation-duration:12s !important; }
    @keyframes alxStreak { from { transform:translateX(-30vw); } to { transform:translateX(120vw); } }
    .hero-inner p, .hero-inner div { opacity:1 !important; visibility:visible !important; }
    /* Intro failsafe: hide after 6s even if JS breaks */
    #intro { animation: alxIntroHide 0.8s ease 5.5s forwards; }
    #intro ~ #intro { display:none !important; }
    @keyframes alxIntroHide { to { opacity:0; visibility:hidden; } }
    /* Aeonik font enforcement */
    body, body *:not(i):not([class*="fa-"]):not(.dashicons) { font-family:'Aeonik',system-ui,sans-serif !important; }
    </style>
    <?php
}
add_action('wp_head', 'alx_sr_critical_css', 999);


/* Remove duplicate intros/logos (from old bundle versions) */
function alx_sr_dedup() {
    if (is_admin() || !is_singular(array('page', 'post'))) return;
    ?>
    <script>
    (function(){
      var intros = document.querySelectorAll('#intro');
      for(var i=1; i<intros.length; i++){ intros[i].parentNode.removeChild(intros[i]); }
      var logoBox = document.getElementById('introLogo');
      if(logoBox){
        var imgs = logoBox.querySelectorAll('img');
        for(var j=1; j<imgs.length; j++){ imgs[j].parentNode.removeChild(imgs[j]); }
      }
    })();
    </script>
    <?php
}
add_action('wp_footer', 'alx_sr_dedup', 1);

