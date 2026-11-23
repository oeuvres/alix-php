<h1>Corpora</h1>
<ul>
<?php foreach ($bases as $key => $base): ?>
    <li><a href="<?= $e($link($mode, $key)) ?>"><?= $e($base['title']) ?></a></li>
<?php endforeach; ?>
</ul>
