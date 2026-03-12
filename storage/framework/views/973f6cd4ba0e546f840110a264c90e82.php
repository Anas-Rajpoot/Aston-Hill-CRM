<?php $__env->startSection('content'); ?>
<h2>Permissions</h2>
<?php if(session('success')): ?> <p style="color:green"><?php echo e(session('success')); ?></p> <?php endif; ?>
<a href="<?php echo e(route('super-admin.permissions.create')); ?>">Add Permission</a>

<table border="1" cellpadding="8">
  <thead>
    <tr><th>ID</th><th>Name</th><th>Actions</th></tr>
  </thead>
  <tbody>
  <?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <tr>
      <td><?php echo e($p->id); ?></td>
      <td><?php echo e($p->name); ?></td>
      <td>
        <a href="<?php echo e(route('super-admin.permissions.show',$p)); ?>">Show</a>
        <a href="<?php echo e(route('super-admin.permissions.edit',$p)); ?>">Edit</a>
        <form action="<?php echo e(route('super-admin.permissions.destroy',$p)); ?>" method="POST" style="display:inline">
          <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
          <button type="submit" onclick="return confirm('Delete?')">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </tbody>
</table>

<?php echo e($permissions->links()); ?>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\super-admin\permissions\index.blade.php ENDPATH**/ ?>