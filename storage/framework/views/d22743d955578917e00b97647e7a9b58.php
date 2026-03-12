<?php
  $isEdit = isset($expense);
?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="text-sm font-medium">Date</label>
        <input type="date" name="expense_date"
               value="<?php echo e(old('expense_date', $isEdit ? $expense->expense_date?->format('Y-m-d') : '')); ?>"
               class="w-full border-gray-300 rounded-md" required>
        <?php $__errorArgs = ['expense_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-600 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label class="text-sm font-medium">Product Category</label>
        <input name="product_category"
               value="<?php echo e(old('product_category', $isEdit ? $expense->product_category : '')); ?>"
               class="w-full border-gray-300 rounded-md" required>
        <?php $__errorArgs = ['product_category'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-600 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div class="md:col-span-2">
        <label class="text-sm font-medium">Product Description</label>
        <textarea name="product_description" rows="3"
                  class="w-full border-gray-300 rounded-md"><?php echo e(old('product_description', $isEdit ? $expense->product_description : '')); ?></textarea>
        <?php $__errorArgs = ['product_description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-600 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label class="text-sm font-medium">Invoice Number</label>
        <input name="invoice_number"
               value="<?php echo e(old('invoice_number', $isEdit ? $expense->invoice_number : '')); ?>"
               class="w-full border-gray-300 rounded-md">
        <?php $__errorArgs = ['invoice_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-600 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label class="text-sm font-medium">VAT (amount)</label>
        <input type="number" step="0.01" min="0" name="vat_amount"
               value="<?php echo e(old('vat_amount', $isEdit ? $expense->vat_amount : 0)); ?>"
               class="w-full border-gray-300 rounded-md" required>
        <?php $__errorArgs = ['vat_amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-600 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label class="text-sm font-medium">Amount without VAT</label>
        <input type="number" step="0.01" min="0" name="amount_without_vat"
               value="<?php echo e(old('amount_without_vat', $isEdit ? $expense->amount_without_vat : 0)); ?>"
               class="w-full border-gray-300 rounded-md" required>
        <?php $__errorArgs = ['amount_without_vat'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-600 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label class="text-sm font-medium">Full Amount</label>
        <input type="number" step="0.01" min="0" name="full_amount"
               value="<?php echo e(old('full_amount', $isEdit ? $expense->full_amount : 0)); ?>"
               class="w-full border-gray-300 rounded-md" required>
        <?php $__errorArgs = ['full_amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-600 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div class="md:col-span-2">
        <label class="text-sm font-medium">Comment</label>
        <textarea name="comment" rows="3"
                  class="w-full border-gray-300 rounded-md"><?php echo e(old('comment', $isEdit ? $expense->comment : '')); ?></textarea>
        <?php $__errorArgs = ['comment'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-600 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>
</div>
<?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\expenses\_form.blade.php ENDPATH**/ ?>