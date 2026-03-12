<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['step']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['step']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
$steps = [
  1 => 'Primary Info',
  2 => 'Service Category',
  3 => 'Service Details',
  4 => 'Documents',
];
?>

<div class="mb-6">
  <div class="flex items-center justify-between">
    <?php $__currentLoopData = $steps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="flex-1 flex items-center">
        <div class="flex items-center gap-2">
          <div class="
            w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold
            <?php echo e($step >= $i ? 'bg-brand-primary text-white' : 'bg-gray-200 text-gray-600'); ?>

          ">
            <?php echo e($i); ?>

          </div>
          <span class="text-sm <?php echo e($step >= $i ? 'text-brand-text' : 'text-gray-400'); ?>">
            <?php echo e($label); ?>

          </span>
        </div>

        <?php if(!$loop->last): ?>
          <div class="flex-1 h-[2px] mx-3 <?php echo e($step > $i ? 'bg-brand-primary' : 'bg-gray-200'); ?>"></div>
        <?php endif; ?>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>
<?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\lead-submission\partials\wizard-progress.blade.php ENDPATH**/ ?>