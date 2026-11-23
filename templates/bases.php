<h1>Corpora</h1>
<ul>
<?php foreach ($bases as $key => $base): ?>
    <li><a href="<?= $e($link($mode, $key)) ?>"><?= $e((string) ($base['label'] ?? $key)) ?></a><?php if (isset($base['numDocs'])): ?> (<?= (int) $base['numDocs'] ?> documents)<?php endif; ?></li>
<?php endforeach; ?>
</ul>
