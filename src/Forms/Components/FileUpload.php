<?php

namespace Lodestone\Forms\Components;

/**
 * One file. The submit callback gets an UploadedFile, or nothing when editing and no new file
 * was chosen, so keep the old one unless $data has the key. Store it yourself:
 *
 *     if (isset($data['contract'])) $tenancy->contract_path = $data['contract']->store('contracts');
 */
class FileUpload extends Field
{
    protected ?string $accept = null;

    protected ?int $maxKilobytes = null;

    /**
     * Set the browser's file filter, e.g. 'image/*' or '.pdf,.csv'. Validate types with rules('mimes:pdf').
     */
    public function accept(string $accept): static
    {
        $this->accept = $accept;

        return $this;
    }

    /**
     * Accept images only.
     */
    public function image(): static
    {
        $this->accept = 'image/*';
        $this->rules('image');

        return $this;
    }

    /**
     * Set the maximum size in kilobytes.
     */
    public function maxSize(int $kilobytes): static
    {
        $this->maxKilobytes = $kilobytes;

        return $this;
    }

    /**
     * Get the validation rules. When editing, the browser only sends a newly chosen file,
     * so a required file is only required on create.
     */
    public function validationRules(array $input = [], bool $creating = true): array
    {
        $rules = parent::validationRules($input, $creating);

        if (! $creating && $rules[$this->name] !== ['exclude']) {
            array_unshift($rules[$this->name], 'sometimes');
        }

        return $rules;
    }

    /**
     * Get the control type.
     */
    protected function type(): string
    {
        return 'file';
    }

    /**
     * Get the validation rules the control implies.
     */
    protected function typeRules(): array
    {
        return array_values(array_filter(['file', $this->maxKilobytes ? "max:{$this->maxKilobytes}" : null]));
    }

    /**
     * Get the settings the control needs.
     */
    protected function extra(): array
    {
        return ['accept' => $this->accept];
    }
}
