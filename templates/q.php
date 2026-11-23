<form action="<?= $e($link($mode, $baseKey, '/q')) ?>" method="get">
    <label for="q">Search</label>
    <input type="search" id="q" name="q" value="<?= $e($q) ?>">
    <button type="submit">Search</button>
</form>
<?php if ($results !== null): ?>
<section aria-label="Search results">
    <?= $results /* HTML from the configured Alix servlet */ ?>
</section>
<?php endif; ?>
