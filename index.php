<?php
/**
 * TheGunSite - Root Redirector
 * Leitet den Besucher direkt zum Marktplatz weiter.
 */

// Permanent Redirect (301) für Suchmaschinen und Browser-Cache
header("Location: marketplace/index.php", true, 301);
exit();
?>