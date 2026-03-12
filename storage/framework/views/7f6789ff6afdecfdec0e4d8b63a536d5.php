<?php $__env->startSection('content'); ?>
<h2>Create Permission</h2>

<?php if($errors->any()): ?>
  <div style="color:red">
    <ul>
      <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?> <li><?php echo e($e); ?></li> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
  </div>
<?php endif; ?>

<form method="POST" action="<?php echo e(route('super-admin.permissions.store')); ?>">
  <?php echo csrf_field(); ?>

  <label>
    <input type="radio" name="mode" value="module_actions" checked>
    Create by Module + Actions (recommended)
  </label>
  <br>

  <div style="margin:10px 0; padding:10px; border:1px solid #ddd;">
    <label>Module</label>
    <select name="module">
      <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($k); ?>" <?php if(old('module')===$k): echo 'selected'; endif; ?>><?php echo e($label); ?> (<?php echo e($k); ?>)</option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>

    <p style="margin-top:10px;"><b>Actions</b></p>
    <?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $actionKey => $actionLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <label style="display:inline-block;margin-right:12px;">
        <input type="checkbox" name="actions[]" value="<?php echo e($actionKey); ?>">
        <?php echo e($actionLabel); ?>

      </label>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>

  <hr>

  <label>
    <input type="radio" name="mode" value="custom" <?php if(old('mode')==='custom'): echo 'checked'; endif; ?>>
    Create Custom Permission (must be module.action)
  </label>

  <div style="margin:10px 0; padding:10px; border:1px solid #ddd;">
    <label>Custom Name</label>
    <input name="custom_name" placeholder="accounts.edit" value="<?php echo e(old('custom_name')); ?>">
    <small>Format: module.action (example: accounts.edit)</small>
  </div>

  <button type="submit">Save</button>
</form>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\super-admin\permissions\create.blade.php ENDPATH**/ ?>