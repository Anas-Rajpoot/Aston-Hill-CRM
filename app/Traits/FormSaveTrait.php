<?php

namespace App\Traits;

use Illuminate\Support\Facades\Session;

trait FormSaveTrait
{
    protected $sessionKey = 'lead_submission_form';

    // Save form data by step
    public function saveStepData($step, array $data)
    {
        $formData = Session::get($this->sessionKey, []);
        $formData[$step] = $data;
        Session::put($this->sessionKey, $formData);
    }

    // Get form data for all steps or a specific step
    public function getFormData($step = null)
    {
        $formData = Session::get($this->sessionKey, []);
        return $step ? ($formData[$step] ?? null) : $formData;
    }

    // Clear form data after final save or cancel
    public function clearFormData()
    {
        Session::forget($this->sessionKey);
    }
}
