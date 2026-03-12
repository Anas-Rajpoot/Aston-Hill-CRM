<?php $__env->startSection('content'); ?>
<h2>Edit Permission</h2>

<form method="POST" action="<?php echo e(route('super-admin.permissions.update',$permission)); ?>">
  <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
  <label>Name</label>
  <input name="name" value="<?php echo e(old('name',$permission->name)); ?>">
  <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div style="color:red"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
  <button type="submit">Update</button>
</form>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\super-admin\permissions\edit.blade.php ENDPATH**/ ?>