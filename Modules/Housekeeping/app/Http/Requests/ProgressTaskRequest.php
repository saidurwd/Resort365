<?php

namespace Modules\Housekeeping\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Housekeeping\Models\HousekeepingTask;
use Modules\Housekeeping\Services\TaskTransitions;

/**
 * One step of a cleaning task (start, finish, pass, fail, skip), with an optional note.
 */
class ProgressTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($task = $this->route('task')) instanceof HousekeepingTask && in_array($this->route('step'), TaskTransitions::STEPS, true)
            && ($this->user()?->can('step', [$task, (string) $this->route('step')]) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
