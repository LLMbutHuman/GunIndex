<?php
declare(strict_types=1);
?>
</div>
<footer class="site-footer">
    <div class="footer-top"><div><a class="brand" href="/marketplace/index.php"><span class="brand-mark" aria-hidden="true"><span></span></span><?= h($brandName ?? site_setting($pdo, 'brand_name', 'GunIndex.eu')) ?></a><p><?= h($tagline ?? site_setting($pdo, 'tagline', 'Besonderes finden. Gemeinsam weitergeben.')) ?></p></div><nav aria-label="Weitere Informationen"><a href="/marketplace/search.php">Alle Entdeckungen</a><a href="/marketplace/search.php?type=auction">Auktionen</a><a href="/marketplace/policy.php">Regeln &amp; Transparenz</a><a href="/marketplace/policy.php?section=privacy">Datenschutz</a><a href="/marketplace/policy.php?section=operator">Betreiberangaben</a></nav><div class="footer-invite"><span class="eyebrow">Ein neuer Anfang</span><h2>Gute Dinge verdienen<br>eine nächste Geschichte.</h2><a class="text-link" href="/user/create-listing.php">Etwas weitergeben <?= ui_icon('arrow') ?></a></div></div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> <?= h($brandName ?? 'GunIndex.eu') ?></span><span>Für allgemeine Waren. Mit klaren Regeln.</span><button type="button" class="motion-toggle" hidden>Animationen pausieren</button><a href="/marketplace/policy.php?section=payments">Zahlungen &amp; Gebühren</a></div>
</footer>
</body>
</html>
