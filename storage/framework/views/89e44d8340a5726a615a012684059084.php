<?php $__env->startSection('content'); ?>
<h2>Set Permissions for Role: <?php echo e($role->name); ?></h2>
<?php if (isset($component)) { $__componentOriginal360d002b1b676b6f84d43220f22129e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal360d002b1b676b6f84d43220f22129e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.breadcrumbs','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('breadcrumbs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $attributes = $__attributesOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__attributesOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $component = $__componentOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__componentOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>

<div class="flex items-center gap-2">
    <?php
      $tabId = request()->query('__tab') ?? request()->cookie('__tab');
      $trail = $tabId ? session("breadcrumbs_trail.$tabId", []) : [];
      $backUrl = count($trail) > 1 ? ($trail[count($trail)-2]['url'] ?? url()->previous()) : url()->previous();
    ?>

    <a href="<?php echo e($backUrl); ?>"
        class="text-sm text-gray-600 hover:text-indigo-600">
        ← Back
    </a>
    <a href="<?php echo e(route('super-admin.roles.create')); ?>"
    class="bg-indigo-600 text-white px-4 py-2 rounded-md">Add Role</a>
</div>

<?php if(session('success')): ?> <p style="color:green"><?php echo e(session('success')); ?></p> <?php endif; ?>
<?php if(session('error')): ?> <p style="color:red"><?php echo e(session('error')); ?></p> <?php endif; ?>


<div style="margin:12px 0; padding:10px; border:1px solid #ddd;">
  <b>Global Controls</b><br><br>

  <button type="button" onclick="checkAll(true)">Check All (All Modules)</button>
  <button type="button" onclick="checkAll(false)">Uncheck All (All Modules)</button>

  
  <button type="submit" form="save-all-form">Save All</button>
</div>


<form id="save-all-form" method="POST" action="<?php echo e(route('super-admin.roles.permissions.update', $role)); ?>">
  <?php echo csrf_field(); ?>
  <?php echo method_field('PUT'); ?>
</form>


<?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $moduleKey => $moduleLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <form
    id="perm-form-<?php echo e($moduleKey); ?>"
    method="POST"
    action="<?php echo e(route('super-admin.roles.permissions.updateModule', [$role, $moduleKey])); ?>"
  >
    <?php echo csrf_field(); ?>
    <?php echo method_field('PUT'); ?>
  </form>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<table border="1" cellpadding="10" style="width:100%; border-collapse:collapse;">
  <thead>
    <tr>
      <th style="width:220px;">Module</th>
      <?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $actionKey => $actionLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <th style="text-align:center"><?php echo e($actionLabel); ?></th>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tr>
  </thead>

  <tbody>
    <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $moduleKey => $moduleLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php
        $moduleFormId = "perm-form-{$moduleKey}";
      ?>

      <tr>
        <td>
          <b><?php echo e($moduleLabel); ?></b>
          <div style="font-size:12px;color:#666"><?php echo e($moduleKey); ?></div>

          <div style="margin-top:8px;">
            <button type="button" onclick="toggleModule('<?php echo e($moduleKey); ?>', true)">Check All</button>
            <button type="button" onclick="toggleModule('<?php echo e($moduleKey); ?>', false)">Uncheck All</button>

            
            <button type="submit" form="<?php echo e($moduleFormId); ?>">Save</button>
          </div>
        </td>

        <?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $actionKey => $actionLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            $perm = "{$moduleKey}.{$actionKey}";
            $exists = isset($allPermissions[$perm]);
            $checked = isset($rolePermissions[$perm]);
          ?>

          <td style="text-align:center">
            <?php if($exists): ?>
              <input
                class="perm-cb perm-<?php echo e($moduleKey); ?>"
                type="checkbox"
                name="permissions[]"
                value="<?php echo e($perm); ?>"
                <?php if($checked): echo 'checked'; endif; ?>

                form="save-all-form"
                data-module-form="<?php echo e($moduleFormId); ?>"
              >
              <div style="font-size:11px;color:#666"><?php echo e($perm); ?></div>

              <input
                type="checkbox"
                name="permissions[]"
                value="<?php echo e($perm); ?>"
                <?php if($checked): echo 'checked'; endif; ?>
                form="<?php echo e($moduleFormId); ?>"
                class="mirror-<?php echo e($moduleKey); ?>"
                style="display:none;"
              >
            <?php else: ?>
              <span style="color:red;font-size:12px">Missing</span>
            <?php endif; ?>
          </td>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </tbody>
</table>
<?php $__env->stopSection(); ?>

<script>
  function toggleModule(moduleKey, state) {
    document.querySelectorAll('.perm-' + moduleKey).forEach(cb => cb.checked = state);
    syncModuleMirrors(moduleKey);
  }

  function checkAll(state){
    document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = state);
    // sync all mirrors
    <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $moduleKey => $moduleLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      syncModuleMirrors("<?php echo e($moduleKey); ?>");
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  }

  // Keep module-form hidden inputs synced with visible checkboxes
  function syncModuleMirrors(moduleKey){
    const visibles = document.querySelectorAll('.perm-' + moduleKey);
    const mirrors  = document.querySelectorAll('.mirror-' + moduleKey);

    visibles.forEach((v, idx) => {
      if (mirrors[idx]) mirrors[idx].checked = v.checked;
    });
  }

  // initial sync on page load
  document.addEventListener('DOMContentLoaded', () => {
    <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $moduleKey => $moduleLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      syncModuleMirrors("<?php echo e($moduleKey); ?>");
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    // whenever a checkbox changes, sync its module mirrors
    document.querySelectorAll('.perm-cb').forEach(cb => {
      cb.addEventListener('change', () => {
        const classes = [...cb.classList];
        const moduleClass = classes.find(c => c.startsWith('perm-') && c !== 'perm-cb');
        if(moduleClass){
          const moduleKey = moduleClass.replace('perm-','');
          syncModuleMirrors(moduleKey);
        }
      });
    });
  });
</script>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\super-admin\roles\permissions.blade.php ENDPATH**/ ?>