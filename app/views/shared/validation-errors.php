<?php /** Data: errors (list of sentences) */ if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert" id="form-errors">
    <ul class="mb-0 ps-3">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
