<?php
  $tabId = request()->query('__tab') ?? request()->cookie('__tab');
  $trail = $tabId ? session("breadcrumbs_trail.$tabId", []) : [];
?>

<?php if(count($trail)): ?>
  <nav class="mb-3 text-sm text-gray-600">
    <ol class="flex flex-wrap items-center gap-2">
      <?php $__currentLoopData = $trail; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(!$loop->first): ?>
          <span class="text-gray-400">/</span>
        <?php endif; ?>

        <?php if(!$loop->last && !empty($item['url'])): ?>
          <a href="<?php echo e($item['url']); ?>" class="text-indigo-600 hover:underline">
            <?php echo e($item['label'] ?? '...'); ?>

          </a>
        <?php else: ?>
          <span class="font-medium text-gray-900">
            <?php echo e($item['label'] ?? '...'); ?>

          </span>
        <?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ol>
  </nav>
<?php endif; ?>
<?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\components\breadcrumbs.blade.php ENDPATH**/ ?>