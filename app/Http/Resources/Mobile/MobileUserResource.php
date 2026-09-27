<?php

namespace App\Http\Resources\Mobile;

use App\Identity\MobileAccess;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * This resource is only ever built for a user who has already passed the
 * `mobile.access` middleware (or is about to be issued a token at login
 * after the same check) — mobile_access/can_access_mobile_review are
 * therefore always true here; a user who fails that check gets a 403
 * instead of ever reaching this resource.
 *
 * @property-read User $resource
 */
class MobileUserResource extends JsonResource
{
    public function __construct(User $resource, private readonly MobileAccess $mobileAccess)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $approverRoles = $this->mobileAccess->approverRolesFor($this->resource)
            ->map(fn ($role) => $role->value)
            ->values()
            ->all();

        return [
            'id' => $this->resource->id,
            'email' => $this->resource->email,
            'name' => $this->resource->name,
            'roles' => ['approver', ...$approverRoles],
            'mobile_access' => true,
            'capabilities' => [
                'can_access_mobile_review' => true,
            ],
        ];
    }
}
