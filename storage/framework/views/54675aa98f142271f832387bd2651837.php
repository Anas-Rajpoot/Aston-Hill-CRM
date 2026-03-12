<?php $__env->startSection('content'); ?>
<?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-brand-dark">Lead Submission #<?php echo e($leadSubmission->id); ?></h2>
            <p class="text-sm text-gray-500">View Lead Submission details & documents</p>
        </div>

        <div class="flex gap-2">
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('lead-submissions.edit')): ?>
                <a href="<?php echo e(route('lead-submissions.edit', $leadSubmission)); ?>" class="px-4 py-2 rounded bg-indigo-600 text-white text-sm">Edit</a>
            <?php endif; ?>
            <a href="<?php echo e(route('lead-submissions.index')); ?>" class="px-4 py-2 rounded bg-gray-200 text-gray-900 text-sm">Back</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5">
        <div class="md:col-span-2 space-y-3">
            <div class="border rounded-xl p-4">
                <p class="text-xs text-gray-500">Company</p>
                <p class="font-semibold"><?php echo e($leadSubmission->company_name); ?></p>
                <p class="text-sm text-gray-500 mt-1">Account: <?php echo e($leadSubmission->account_number ?? '-'); ?></p>
            </div>

            <div class="border rounded-xl p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <p class="text-xs text-gray-500">Category</p>
                    <p class="font-medium"><?php echo e($leadSubmission->category?->name ?? '-'); ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Type</p>
                    <p class="font-medium"><?php echo e($leadSubmission->type?->name ?? '-'); ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Status</p>
                    <p class="font-medium"><?php echo e(ucfirst($leadSubmission->status ?? '-')); ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Created</p>
                    <p class="font-medium"><?php echo e($leadSubmission->created_at?->format('d-M-Y h:i A')); ?></p>
                </div>
            </div>

            <div class="border rounded-xl p-4">
                <p class="font-semibold text-gray-800 mb-2">Dynamic Fields</p>
                <?php if(empty($fields)): ?>
                    <p class="text-sm text-gray-500">No dynamic fields.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $key = $field['key'] ?? '';
                                $label = $field['label'] ?? $key;
                                $val = data_get($leadSubmission->meta, $key);
                                if(is_bool($val)) $val = $val ? 'Yes' : 'No';
                            ?>
                            <div class="border rounded-lg p-3">
                                <p class="text-xs text-gray-500"><?php echo e($label); ?></p>
                                <p class="text-sm font-medium text-gray-800 break-words"><?php echo e($val ?? '-'); ?></p>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="space-y-3">
            <div class="border rounded-xl p-4">
                <p class="font-semibold text-gray-800 mb-2">Creator</p>
                <p class="text-sm text-gray-700"><?php echo e($leadSubmission->creator?->name ?? '-'); ?></p>
                <p class="text-xs text-gray-500"><?php echo e($leadSubmission->creator?->email ?? ''); ?></p>
            </div>

            <div class="border rounded-xl p-4">
                <p class="font-semibold text-gray-800 mb-2">Documents</p>
                <?php if($docs->isEmpty()): ?>
                    <p class="text-sm text-gray-500">No documents.</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php $__currentLoopData = $docs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <a class="block border rounded-lg p-3 hover:bg-gray-50"
                               href="<?php echo e(asset('storage/'.$d->path)); ?>" target="_blank">
                                <p class="text-sm font-medium text-gray-800"><?php echo e($doc->original_name); ?></p>
                                <p class="text-xs text-gray-500"><?php echo e($doc->doc_key); ?> • <?php echo e(number_format(($doc->size ?? 0)/1024, 1)); ?> KB</p>
                            </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\lead-submission\show.blade.php ENDPATH**/ ?>