<?php
// Test harness for the alx-site-redesign <p><img></p> fix.
// Stubs WordPress, includes the plugin file, runs alx_sr_transform() on
// representative post HTML and checks images survive with alt text.
error_reporting(E_ALL & ~E_DEPRECATED);
define('ABSPATH', '/tmp/');

function esc_html($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s){ return (string)$s; }
function apply_filters($tag, $v){ return $v; }
function wp_kses_post($s){ return $s; }
function add_filter(){}
function add_action(){}
function wp_enqueue_style(){}
function wp_enqueue_script(){}
function plugin_dir_url($f){ return ''; }
function get_option($k, $d=false){ return $d; }
function update_option(){ return true; }
function register_activation_hook(){}
function register_deactivation_hook(){}
function current_user_can(){ return true; }

require '/home/hatch/workspace/altus-bot/alx-site-redesign/alx-site-redesign.php';

$fail = 0;
function check($label, $cond){ global $fail; echo ($cond ? "PASS" : "FAIL") . ": $label\n"; if(!$cond) $fail++; }

// 1. Image wrapped in <p> (the exact markup our pipeline emits)
$html = '<p>Intro text here.</p>'
      . '<h2>The 9 things every invoice must include</h2>'
      . '<p><img src="https://example.com/inbody1.webp" alt="Freelancer desk with glowing invoices" /></p>'
      . '<p>Some body text after.</p>'
      . '<p><img src="https://example.com/infographic.png" alt="Infographic about invoices" /></p>'
      . '<p>Mixed <img src="https://example.com/inline.webp" alt="inline pic" /> paragraph with text.</p>';
$out = alx_sr_transform($html);
check('p-wrapped img #1 kept', strpos($out, 'inbody1.webp') !== false);
check('p-wrapped img #2 kept', strpos($out, 'infographic.png') !== false);
check('inline img in mixed paragraph kept', strpos($out, 'inline.webp') !== false);
check('alt text preserved (inbody1)', strpos($out, 'alt="Freelancer desk with glowing invoices"') !== false);
check('alt text preserved (infographic)', strpos($out, 'alt="Infographic about invoices"') !== false);
check('mixed paragraph text kept', strpos($out, 'Mixed') !== false && strpos($out, 'paragraph with text') !== false);
check('plain paragraph still kept', strpos($out, 'Intro text here') !== false);
check('h2 still kept', strpos($out, 'The 9 things every invoice must include') !== false);

// 2. Regression: bare <img>, figure, and image-only link still work
$html2 = '<img src="https://example.com/bare.webp" alt="bare image">'
       . '<figure><img src="https://example.com/fig.webp" alt="figure image"><figcaption>A caption</figcaption></figure>';
$out2 = alx_sr_transform($html2);
check('bare img kept', strpos($out2, 'bare.webp') !== false);
check('figure img kept', strpos($out2, 'fig.webp') !== false);
check('figcaption kept', strpos($out2, 'A caption') !== false);

// 3. Empty paragraph with only an image must not vanish (the original bug)
$out3 = alx_sr_transform('<p><img src="https://example.com/solo.webp" alt="solo image"></p>');
check('image-only paragraph renders a figure', strpos($out3, 'alx-fig') !== false && strpos($out3, 'solo.webp') !== false);

// 4. Real post content from today's draft
$real = file_get_contents('/home/hatch/workspace/altus-bot/blog-html/how-to-write-an-invoice-freelancer-guide.html');
$out4 = alx_sr_transform($real);
check('real post: inbody1 rendered', strpos($out4, 'how-to-write-an-invoice-freelancer-guide-inbody1.webp') !== false);
check('real post: inbody2 rendered', strpos($out4, 'how-to-write-an-invoice-freelancer-guide-inbody2.webp') !== false);
check('real post: infographic rendered', strpos($out4, 'how-to-write-an-invoice-freelancer-guide-infographic.png') !== false);
check('real post: infographic alt rendered', strpos($out4, 'alt="Infographic: the 9 essentials') !== false);
check('real post: body text intact', strpos($out4, 'Sending your first invoice') !== false);
check('real post: FAQ text intact', strpos($out4, 'Late Payment of Commercial Debts') !== false);

echo $fail === 0 ? "\nALL TESTS PASSED\n" : "\n$fail TEST(S) FAILED\n";
exit($fail === 0 ? 0 : 1);
