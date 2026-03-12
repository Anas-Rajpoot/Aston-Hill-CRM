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
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold">Create Lead Submission</h2>
                <p class="text-sm text-gray-500">Step 1 — Primary Information</p>
            </div>
        </div>

        <?php echo $__env->make('lead-submission.partials._wizard_steps', ['step' => 1], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <form method="POST" action="<?php echo e(route('lead-submissions.store.step1')); ?>" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php echo csrf_field(); ?>

            <x-ui/input name="company_name" label="Company Name" />
            <x-ui/input name="account_number" label="Account Number" />
            <x-ui/input name="authorized_signatory_name" label="Authorized Signatory" />

            <x-ui/input name="contact_number" label="Contact Number" />
            <x-ui/input name="alternate_contact_number" label="Alternate Contact" />
            <x-ui/input name="email" label="Email" type="email" />

            <div class="md:col-span-3">
                <x-ui/input name="address" label="Address" />
            </div>

            <x-ui/input name="emirates" label="Emirates" />
            <x-ui/input name="location_coordinates" label="Location Coordinates" placeholder="lat,lng" />

            <x-ui/input name="product" label="Product" />
            <x-ui/input name="offer" label="Offer" />
            <x-ui/input name="mrc_aed" label="MRC" />

            <x-ui/input name="quantity" label="Quantity" type="number" />
            <div class="md:col-span-3">
                <x-ui/input name="remarks" label="Remarks" />
            </div>

            <div class="md:col-span-3 flex justify-end gap-2 mt-2">
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\lead-submission\wizard\step1.blade.php ENDPATH**/ ?>