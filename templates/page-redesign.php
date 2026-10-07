<?php
/**
 * Altus redesigned page template — new design language, existing footer.
 * Served via template_include for all singular pages/posts.
 */
$pid = get_queried_object_id();
if (have_posts()) { the_post(); }
$is_home = is_front_page();
$title   = get_the_title($pid);
$sub     = alx_sr_hero_sub($pid);
$content = alx_sr_content($pid);
$home_url = home_url('/');
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php wp_head(); ?>
</head>
<body <?php body_class('alx-redesign'); ?>>
<?php wp_body_open(); ?>
<div class="intro" id="intro"><div class="intro-stage"><div class="intro-logo" id="introLogo"></div><div class="intro-bar"><i></i></div></div></div>
<?php include ALX_SR_DIR . 'parts/header.html'; ?>
<main>
<?php if ($is_home): ?>
  <?php include ALX_SR_DIR . 'bundled/dusty-hero.html'; ?>
<?php else: ?>
  <section class="hero inner">
    <div class="hero-vid"></div>
    <span class="streak s1"></span><span class="streak s2"></span>
    <div class="hero-inner">
      <nav class="crumb" aria-label="Breadcrumb"><a href="<?php echo esc_url($home_url); ?>">Home</a><span> / </span><b><?php echo esc_html($title); ?></b></nav>
      <h1>
        <span class="mask"><span><?php echo esc_html($title); ?></span></span>
      </h1>
      <?php if ($sub): ?><p class="hero-sub"><?php echo esc_html($sub); ?></p><?php endif; ?>
      <div class="hero-ctas">
        <a class="btn-pill" href="https://altuswebsolutions.com/start-a-project/">Start a Project</a>
        <a class="btn-outline" href="https://altuswebsolutions.com/schedule-free-consultation/">Free Consultation</a>
      </div>
    </div>
  </section>
<?php endif; ?>
<?php echo $content; ?>
<?php include ALX_SR_DIR . 'parts/cta.html'; ?>
</main>
<?php echo alx_sr_footer(); ?>
<?php wp_footer(); ?>
</body>
</html>
