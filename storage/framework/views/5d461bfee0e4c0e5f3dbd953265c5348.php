<?php $__env->startSection('content'); ?>
<div style="max-width:420px;margin:40px auto;padding:20px;border:1px solid #ddd;border-radius:10px;">
  <h2>Enable Two-Factor Authentication</h2>
  <p>Scan the QR with Google Authenticator or Microsoft Authenticator:</p>

  <div style="margin:15px 0;">
    <img
      src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo e(urlencode($qrCodeUrl)); ?>"
      alt="QR Code"
    />
  </div>

  <form method="POST" action="<?php echo e(route('2fa.enable')); ?>">
    <?php echo csrf_field(); ?>
    <label>Enter 6-digit OTP</label>
    <input name="otp" maxlength="6" style="width:100%;padding:10px;margin-top:6px;" />
    <?php $__errorArgs = ['otp'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div style="color:red;margin-top:8px;"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

    <button style="margin-top:15px;width:100%;padding:10px;">Enable</button>
  </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\auth\2fa-setup.blade.php ENDPATH**/ ?>