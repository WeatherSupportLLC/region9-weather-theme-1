<?php if(!defined('ABSPATH'))exit;
class R9_Studio_Status_Widget extends WP_Widget{function __construct(){parent::__construct('r9_status','Region 9 Studio Status');}function widget($a,$i){echo $a['before_widget'].'<strong>Studio Status</strong><p>Latest forecast products and live observation cards are active.</p>'.$a['after_widget'];}function form($i){echo '<p>This widget displays the studio status.</p>';}}
add_action('widgets_init',function(){register_widget('R9_Studio_Status_Widget');});
