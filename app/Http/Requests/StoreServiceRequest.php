<?php

namespace App\Http\Requests;

use App\Enums\ServiceImportance;
use App\Enums\ServiceType;
use App\Models\Service;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->effectiveType();
        $isPortRequired = $type?->usesPort() === true
            && ($this->has('port') || $this->currentService()?->port === null);

        return [
            'name' => [...$this->presenceRules(), 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'type' => [...$this->presenceRules(), Rule::enum(ServiceType::class)],
            'host' => [...$this->presenceRules(), 'string', 'max:253', $this->hostRule()],
            'port' => [
                'nullable',
                Rule::requiredIf($isPortRequired),
                Rule::prohibitedIf($type === ServiceType::Ping),
                'integer',
                'between:1,65535',
            ],
            'use_ssl' => ['sometimes', 'boolean'],
            'importance' => ['sometimes', Rule::enum(ServiceImportance::class)],
            'check_interval' => ['sometimes', 'integer', 'min:10', 'max:86400'],
            'timeout' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'collect_metrics' => ['sometimes', 'boolean'],
            'stream_metrics' => ['sometimes', 'boolean'],
            'enabled' => ['sometimes', 'boolean'],
            'user_id' => [
                Rule::prohibitedIf(! $this->user()->can('assignOwner', Service::class)),
                'nullable',
                'integer',
                Rule::exists(User::class, 'id'),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'port.prohibited' => __('Ping services do not use a port.'),
            'user_id.prohibited' => __('Only administrators can choose who a service is assigned to.'),
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['check_interval', 'timeout'])) {
                    return;
                }

                $defaults = $this->currentService() ?? new Service;
                $checkInterval = (int) $this->input('check_interval', $defaults->check_interval);
                $timeout = (int) $this->input('timeout', $defaults->timeout);

                if ($timeout >= $checkInterval) {
                    $validator->errors()->add('timeout', __('The timeout must be shorter than the check interval.'));
                }
            },
        ];
    }

    /**
     * Build a new service from the validated input, owned by the current user
     * unless an administrator assigned it elsewhere.
     */
    public function makeService(): Service
    {
        $service = new Service;
        $service->owner()->associate($this->user());

        return $this->fillService($service);
    }

    /**
     * Apply the validated input to the given service without saving it.
     *
     * The owner is not mass assignable, so it is only changed here, and only for administrators.
     */
    public function fillService(Service $service): Service
    {
        $service->fill($this->safe()->except('user_id'));

        if ($service->type === ServiceType::Ping) {
            $service->port = null;
        }

        if ($this->has('user_id') && $this->user()->can('assignOwner', Service::class)) {
            $service->user_id = $this->validated('user_id');
        }

        return $service;
    }

    /**
     * Get the presence rules for the fields every service must have.
     *
     * @return list<string>
     */
    protected function presenceRules(): array
    {
        return ['required'];
    }

    /**
     * Get the service being updated, if any.
     */
    protected function currentService(): ?Service
    {
        return null;
    }

    /**
     * Get the type the service will have once the request is applied.
     */
    protected function effectiveType(): ?ServiceType
    {
        return ServiceType::tryFrom((string) $this->input('type')) ?? $this->currentService()?->type;
    }

    /**
     * Get a rule that accepts an IPv4 address, IPv6 address or hostname.
     *
     * @return Closure(string, mixed, Closure(string): mixed): void
     */
    protected function hostRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $isIpAddress = filter_var($value, FILTER_VALIDATE_IP) !== false;
            $isHostname = filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

            if (! $isIpAddress && ! $isHostname) {
                $fail(__('The :attribute must be a valid IP address or hostname.'));
            }
        };
    }
}
