<?php
// First code after wp-load.php: optional modules must still be unloaded.
foreach(array('NIL_License','NIL_Sheet','NIL_Media','NIL_Maintenance','NIL_Variations','NIL_Presets','NIL_Market','NIL_Selection','NIL_Dates') as $class)if(class_exists($class,false))throw new Exception('Eager optional module: '.$class);
global $wpdb;$before=$wpdb->num_queries;NIL_Bulk_Manager::boot();if($wpdb->num_queries!==$before)throw new Exception('Frontend boot added SQL');
NIL_Bulk_Manager::assets('not-the-plugin-page');if(wp_style_is('nil-bulk','enqueued')||wp_script_is('nil-bulk','enqueued')||wp_script_is('nil-suite','enqueued')||wp_script_is('nil-advanced','enqueued')||wp_script_is('nil-market','enqueued'))throw new Exception('Frontend assets leaked');
echo "PASS: frontend boot 0 SQL; 0 plugin assets; optional spreadsheet/media/maintenance modules lazy.\n";
