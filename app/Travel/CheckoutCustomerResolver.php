<?php

namespace App\Travel;

use App\Models\Customer;
use App\Models\User;
use App\Travel\Exceptions\CheckoutIdentityException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CheckoutCustomerResolver
{
    /**
     * Resolve the local Customer that owns this order without confusing the
     * account owner with the traveller or booking contact.
     *
     * Authenticated checkout always belongs to the signed-in user's Customer.
     * Guest checkout never claims a Customer that belongs to a registered user
     * merely because the guest typed that user's email address.
     *
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $contact
     */
    public function resolve(?User $user, array $identity, array $contact): Customer
    {
        $contactEmail = strtolower(trim((string) ($contact['email'] ?? '')));

        return DB::transaction(function () use ($user, $identity, $contact, $contactEmail): Customer {
            if ($user) {
                $customer = Customer::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($customer) {
                    return $customer;
                }

                $accountEmail = strtolower(trim((string) $user->email));
                $emailOwner = Customer::withTrashed()
                    ->whereRaw('LOWER(email) = ?', [$accountEmail])
                    ->lockForUpdate()
                    ->first();

                if ($emailOwner && $emailOwner->user_id && (int) $emailOwner->user_id !== (int) $user->id) {
                    throw new CheckoutIdentityException(
                        'Your Karossy account profile needs attention before payment. No payment has been started. Please contact support.',
                        "Authenticated user {$user->id} cannot use Customer {$emailOwner->id}; the account email is linked to another user.",
                    );
                }

                if ($emailOwner) {
                    if ($emailOwner->trashed()) {
                        $emailOwner->restore();
                    }

                    $emailOwner->forceFill([
                        'user_id' => $user->id,
                        'status' => 'active',
                    ])->save();

                    return $emailOwner->fresh();
                }

                [$firstName, $lastName] = $this->accountNames($user, $identity);

                try {
                    return Customer::create([
                        'user_id' => $user->id,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $accountEmail,
                        // A booking contact may be a different person from the account owner.
                        // Do not copy that traveller/contact phone into a newly repaired account profile.
                        'phone' => null,
                        'status' => 'active',
                    ]);
                } catch (\Throwable $exception) {
                    throw new CheckoutIdentityException(
                        'Your Karossy account profile could not be prepared for checkout. No payment has been started. Please retry or contact support.',
                        'Failed to create authenticated Customer before payment: '.$exception->getMessage(),
                        previous: $exception,
                    );
                }
            }

            $emailOwner = Customer::withTrashed()
                ->whereRaw('LOWER(email) = ?', [$contactEmail])
                ->lockForUpdate()
                ->first();

            // An existing guest Customer can own another guest booking, but a
            // registered account is never claimed by an unauthenticated checkout.
            if ($emailOwner && ! $emailOwner->user_id && ! $emailOwner->trashed()) {
                return $emailOwner;
            }

            if ($emailOwner) {
                return $this->transientCustomer($identity, $contact);
            }

            try {
                return Customer::create([
                    'title' => $identity['title'] ?? null,
                    'first_name' => $this->requiredName($identity['first_name'] ?? null, 'Guest'),
                    'last_name' => $this->requiredName($identity['last_name'] ?? null, 'Traveller'),
                    'email' => $contactEmail,
                    'phone' => $contact['phone'] ?? null,
                    'status' => 'active',
                ]);
            } catch (\Throwable $exception) {
                // Handle a concurrent insert safely. Never turn a duplicate email
                // into ownership of a registered account.
                $existing = Customer::query()
                    ->whereRaw('LOWER(email) = ?', [$contactEmail])
                    ->first();

                if ($existing && ! $existing->user_id) {
                    return $existing;
                }

                if ($existing) {
                    return $this->transientCustomer($identity, $contact);
                }

                throw new CheckoutIdentityException(
                    'Your booking contact could not be prepared safely. No payment has been started. Please check the details and try again.',
                    'Failed to create guest Customer before payment: '.$exception->getMessage(),
                    'contact.email',
                    $exception,
                );
            }
        });
    }

    /** @param array<string, mixed> $identity @param array<string, mixed> $contact */
    public function transientCustomer(array $identity, array $contact): Customer
    {
        return new Customer([
            'title' => $identity['title'] ?? null,
            'first_name' => $this->requiredName($identity['first_name'] ?? null, 'Guest'),
            'last_name' => $this->requiredName($identity['last_name'] ?? null, 'Traveller'),
            'email' => strtolower(trim((string) ($contact['email'] ?? ''))),
            'phone' => $contact['phone'] ?? null,
            'status' => 'active',
        ]);
    }

    /** @param array<string, mixed> $identity @param array<string, mixed> $contact @return array{name:string,first_name:string,last_name:string,email:string,phone:?string} */
    public function bookingContact(array $identity, array $contact): array
    {
        $name = trim(collect([
            $identity['title'] ?? null,
            $identity['first_name'] ?? null,
            $identity['last_name'] ?? null,
        ])->filter()->implode(' '));

        return [
            'name' => $name !== '' ? $name : 'Valued Traveller',
            'first_name' => $this->requiredName($identity['first_name'] ?? null, 'Guest'),
            'last_name' => $this->requiredName($identity['last_name'] ?? null, 'Traveller'),
            'email' => strtolower(trim((string) ($contact['email'] ?? ''))),
            'phone' => isset($contact['phone']) ? trim((string) $contact['phone']) : null,
        ];
    }

    /** @param array<string, mixed> $identity @return array{0:string,1:string} */
    private function accountNames(User $user, array $identity): array
    {
        $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];
        $firstName = trim((string) ($parts[0] ?? ''));
        $lastName = trim((string) ($parts[1] ?? ''));

        if ($firstName === '') {
            $firstName = $this->requiredName($identity['first_name'] ?? null, 'Karossy');
        }
        if ($lastName === '') {
            $lastName = $this->requiredName($identity['last_name'] ?? null, 'Traveller');
        }

        return [Str::limit($firstName, 80, ''), Str::limit($lastName, 80, '')];
    }

    private function requiredName(mixed $value, string $fallback): string
    {
        $name = trim((string) $value);

        return Str::limit($name !== '' ? $name : $fallback, 80, '');
    }
}
