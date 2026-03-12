

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
    <div class="space-y-4">
        <div>
            <h2 class="text-xl font-semibold">Create Lead Submission</h2>
            <p class="text-sm text-gray-500">Step 3 — Service Type & Dynamic Fields</p>
        </div>

        <?php echo $__env->make('lead-submissions.partials._wizard_steps', ['step' => 3], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <form method="GET" action="<?php echo e(route('lead-submissions.wizard.step3', $leadSubmission)); ?>" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-ui/select
                name="service_type_id"
                label="Service Type"
                :options="$types->pluck('name','id')->toArray()"
                :value="request('service_type_id') ?: $leadSubmission->service_type_id"
                placeholder="Select Service Type"
            />
            <div class="flex items-end">
                <button class="px-4 py-2 rounded bg-gray-900 text-white">Load Fields</button>
            </div>
        </form>

        <form method="POST" action="<?php echo e(route('lead-submissions.wizard.step3.store', $leadSubmission)); ?>" class="mt-2 space-y-4">
            <?php echo csrf_field(); ?>

            <input type="hidden" name="service_type_id" value="<?php echo e($selectedType?->id ?? $leadSubmission->service_type_id); ?>"/>

            <?php if(!$selectedType): ?>
                <div class="p-4 rounded-lg border bg-gray-50 text-gray-600 text-sm">
                    Please select Service Type to load dynamic fields.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $key = $f['key'] ?? '';
                            $label = $f['label'] ?? $key;
                            $type = $f['type'] ?? 'text';
                            $placeholder = $f['placeholder'] ?? '';
                            $required = (bool)($f['required'] ?? false);
                            $val = data_get($leadSubmission->meta, $key);
                        ?>

                        <?php if($type === 'textarea'): ?>
                            <div class="md:col-span-2">
                                <x-ui/textarea name="meta[<?php echo e($key); ?>]" label="<?php echo e($label); ?>" :value="$val" placeholder="<?php echo e($placeholder); ?>" />
                            </div>
                        <?php elseif($type === 'select'): ?>
                            <?php
                                $opts = [];
                                foreach(($f['options'] ?? []) as $o){
                                    $opts[(string)$o] = (string)$o;
                                }
                            ?>
                            <x-ui/select
                                name="meta[<?php echo e($key); ?>]"
                                label="<?php echo e($label); ?>"
                                :options="$opts"
                                :value="$val"
                                placeholder="Select"
                            />
                        <?php elseif($type === 'checkbox'): ?>
                            <div class="flex items-center gap-2 border rounded-md p-3">
                                <input type="checkbox" name="meta[<?php echo e($key); ?>]" value="1"
                                       class="rounded border-gray-300 focus:ring-brand-primary"
                                       <?php if(old("meta.$key", $val)): echo 'checked'; endif; ?> />
                                <div>
                                    <p class="text-sm font-medium text-gray-800"><?php echo e($label); ?></p>
                                    <?php if($placeholder): ?>
                                        <p class="text-xs text-gray-500"><?php echo e($placeholder); ?></p>
                                    <?php endif; ?>
                                    <?php $__errorArgs = ["meta.$key"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                        <p class="text-xs text-red-600 mt-1"><?php echo e($message); ?></p>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            
                            <x-ui/input
                                name="meta[<?php echo e($key); ?>]"
                                label="<?php echo e($label); ?>"
                                type="<?php echo e($type); ?>"
                                :value="$val"
                                placeholder="<?php echo e($placeholder); ?>"
                            />
                        <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <div class="flex justify-between gap-2 mt-2">
                <a href="<?php echo e(route('lead-submissions.wizard.step2', $leadSubmission)); ?>" class="px-4 py-2 rounded bg-gray-200 text-gray-900">Back</a>
                <button class="px-4 py-2 rounded bg-brand-primary text-white">Continue</button>
            </div>
        </form>
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\lead-submission\wizard\step3.blade.php ENDPATH**/ ?>