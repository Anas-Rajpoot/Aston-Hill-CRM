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
            <h2 class="text-xl font-semibold">Edit Lead Submission #<?php echo e($leadSubmission->id); ?></h2>
            <p class="text-sm text-gray-500">Update primary info, service selection, dynamic fields and documents.</p>
        </div>
        <a href="<?php echo e(route('lead-submissions.show', $leadSubmission)); ?>" class="px-4 py-2 rounded bg-gray-200 text-gray-900 text-sm">Back</a>
    </div>

    <form method="POST" action="<?php echo e(route('lead-submissions.update', $leadSubmission)); ?>" enctype="multipart/form-data" class="mt-5 space-y-5">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-ui/input name="company_name" label="Company Name" :value="$leadSubmission->company_name" />
            <x-ui/input name="account_number" label="Account Number" :value="$leadSubmission->account_number" />
            <x-ui/input name="request_type" label="Request Type" :value="$leadSubmission->request_type" />

            <x-ui/input name="email" label="Email" type="email" :value="$leadSubmission->email" />
            <x-ui/input name="contact_number" label="Contact Number" :value="$leadSubmission->contact_number" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-ui/select name="service_category_id" label="Service Category"
                :options="$categories->pluck('name','id')->toArray()" :value="$leadSubmission->service_category_id" />

            <x-ui/select name="service_type_id" label="Service Type"
                :options="$types->pluck('name','id')->toArray()" :value="$leadSubmission->service_type_id" />
        </div>

        <div class="border rounded-xl p-4">
            <p class="font-semibold text-gray-800 mb-2">Dynamic Fields</p>
            <?php if(empty($fields)): ?>
                <p class="text-sm text-gray-500">No schema fields found.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $key = $field['key'] ?? '';
                            $label = $field['label'] ?? $key;
                            $type = $field['type'] ?? 'text';
                            $val = data_get($leadSubmission->meta, $key);
                            $placeholder = $field['placeholder'] ?? '';
                        ?>

                        <?php if($type === 'textarea'): ?>
                            <div class="md:col-span-2">
                                <x-ui/textarea name="meta[<?php echo e($key); ?>]" label="<?php echo e($label); ?>" :value="$val" placeholder="<?php echo e($placeholder); ?>" />
                            </div>
                        <?php elseif($type === 'select'): ?>
                            <?php
                                $opts = [];
                                foreach(($field['options'] ?? []) as $o){
                                    $opts[(string)$o] = (string)$o;
                                }
                            ?>
                            <x-ui/select name="meta[<?php echo e($key); ?>]" label="<?php echo e($label); ?>" :options="$opts" :value="$val" />
                        <?php elseif($type === 'checkbox'): ?>
                            <div class="flex items-center gap-2 border rounded-md p-3">
                                <input type="checkbox" name="meta[<?php echo e($key); ?>]" value="1"
                                       class="rounded border-gray-300 focus:ring-brand-primary"
                                       <?php if(old("meta.$key", $val)): echo 'checked'; endif; ?> />
                                <div>
                                    <p class="text-sm font-medium text-gray-800"><?php echo e($label); ?></p>
                                    <?php $__errorArgs = ["meta.$key"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <x-ui/input name="meta[<?php echo e($key); ?>]" label="<?php echo e($label); ?>" type="<?php echo e($type); ?>" :value="$val" placeholder="<?php echo e($placeholder); ?>" />
                        <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="border rounded-xl p-4">
            <p class="font-semibold text-gray-800 mb-2">Documents</p>

            <?php if($existingDocs->isNotEmpty()): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                    <?php $__currentLoopData = $existingDocs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $existingDoc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a class="block border rounded-lg p-3 hover:bg-gray-50"
                           href="<?php echo e(asset('storage/'.$d->path)); ?>" target="_blank">
                            <p class="text-sm font-medium"><?php echo e($existingDoc->original_name); ?></p>
                            <p class="text-xs text-gray-500"><?php echo e($existingDoc->doc_key); ?></p>
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <?php if(!empty($docDefs)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php $__currentLoopData = $docDefs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $docDef): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $key = $docDef['key'] ?? '';
                            $label = $docDef['label'] ?? $key;
                        ?>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-gray-600"><?php echo e($label); ?></label>
                            <input type="file" name="documents[<?php echo e($key); ?>]"
                                   class="w-full rounded-md border-gray-300 focus:border-brand-primary focus:ring-brand-primary"/>
                            <?php $__errorArgs = ["documents.$key"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500">No document schema found.</p>
            <?php endif; ?>
        </div>

        <div class="flex justify-end gap-2">
            <button class="px-4 py-2 rounded bg-brand-primary text-white">Save Changes</button>
        </div>
    </form>
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\lead-submission\edit.blade.php ENDPATH**/ ?>