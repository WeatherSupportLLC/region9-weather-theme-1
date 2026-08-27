<?php
if (!defined('ABSPATH')) exit;
?>
<aside class="r9-sidebar r9-home-sidebar" aria-label="Region 9 Weather sidebar">
  <?php
  if (is_active_sidebar('r9-live-sidebar')) {
    dynamic_sidebar('r9-live-sidebar');
  }
  if (function_exists('r9_home_sidebar_fallback')) {
    echo r9_home_sidebar_fallback();
  } elseif (function_exists('r9_default_sidebar')) {
    echo r9_default_sidebar();
  }
  ?>
</aside>
