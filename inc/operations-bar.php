<?php
function r9ws_operations_bar(){
echo '<div class="r9ws-operations-bar">';
echo '<strong>Latest Update:</strong> '.esc_html(r9ws_latest_update_string());
echo ' | <strong>Risk:</strong> None';
echo '</div>';
}
