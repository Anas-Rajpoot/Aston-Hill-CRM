

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
            <p class="text-sm text-gray-500">Step 2 — Service Category</p>
        </div>

        <?php echo $__env->make('lead-submissions.partials._wizard_steps', ['step' => 2], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <form method="POST" action="<?php echo e(route('lead-submissions.wizard.step2.store', $leadSubmission)); ?>" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php echo csrf_field(); ?>

            <x-ui/select
                name="service_category_id"
                label="Service Category"
                :options="$categories->pluck('name','id')->toArray()"
                :value="$leadSubmission->service_category_id"
                placeholder="Select Category"
            />

            <div class="md:col-span-2 flex justify-between gap-2 mt-2">
                <a href="<?php echo e(route('lead-submissions.wizard.step1')); ?>" class="px-4 py-2 rounded bg-gray-200 text-gray-900">Back</a>
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\lead-submission\wizard\step2.blade.php ENDPATH**/ ?>