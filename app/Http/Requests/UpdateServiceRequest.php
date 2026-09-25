<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use LogicException;

class UpdateServiceRequest extends StoreServiceRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorizing before validation means users cannot probe other users' services through validation errors.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->currentService());
    }

    /**
     * Get the presence rules for the fields every service must have.
     *
     * They may be omitted so the API can apply partial updates, but cannot be blanked.
     *
     * @return list<string>
     */
    protected function presenceRules(): array
    {
        return ['sometimes', 'required'];
    }

    /**
     * Get the service being updated.
     */
    protected function currentService(): Service
    {
        $service = $this->route('service');

        if (! $service instanceof Service) {
            throw new LogicException('The update service request must be used on a route bound to a service.');
        }

        return $service;
    }
}
