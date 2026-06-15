<?php

namespace App\Services;

use App\Models\Donation;
use App\Repositories\DonorProfileRepository;
use Illuminate\Support\Collection;

class DonationService
{
    public function __construct(
        private readonly DonorProfileRepository $donorProfileRepository)
     {

     }

    public function createDonation(array $data): Donation
    {
        $donorProfile = $this->donorProfileRepository->findOrCreateByEmail(
            email: $data['donor_email'],
            name:  $data['donor_name'],
        );
        $this->donorProfileRepository->updateDonorType(
            profile:     $donorProfile,
            donorType:   $data['donor_type'] ?? null,
            isAnonymous: $data['is_anonymous'] ?? null,
        );
        return $donorProfile->donations()->create($this->donationFields($data));
    }
    public function getAllDonations(): Collection
    {
        return Donation::with('donorProfile.user')->latest()->get();
    }
    public function getDonationById(int $id): Donation
    {
        return Donation::with('donorProfile.user')->findOrFail($id);
    }
    public function deleteDonation(int $id): void
    {
        Donation::findOrFail($id)->delete();
    }
    private function donationFields(array $data): array
    {
        return [
            'name'   => $data['name'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'cat'    => $data['cat'],
            'date'   => $data['date'],
            'status' => $data['status'],
            'notes'  => $data['notes'] ?? '',
        ];
    }
}
