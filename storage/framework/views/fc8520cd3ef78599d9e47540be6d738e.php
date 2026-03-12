<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['step' => 1]));

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

foreach (array_filter((['step' => 1]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
  $items = [
    1 => 'Primary Info',
    2 => 'Service Category',
    3 => 'Service Type & Fields',
    4 => 'Upload Documents',
  ];
?>

<div class="flex flex-wrap gap-2">
  <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="px-3 py-2 rounded-lg text-sm border
      <?php echo e($step == $i ? 'bg-brand-primary/10 border-brand-primary text-brand-dark' : 'bg-white border-gray-200 text-gray-600'); ?>">
      <span class="font-semibold"><?php echo e($i); ?></span> — <?php echo e($label); ?>

    </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\lead-submission\partials\_wizard_steps.blade.php ENDPATH**/ ?>