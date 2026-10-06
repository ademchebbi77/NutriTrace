<?php

namespace App\Services;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CertificationService
{
    /**
     * Private disk: proofs are never reachable by URL, only through authorized routes.
     */
    public const DISK = 'local';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(User $owner, Product|Lot $target, array $data, ?UploadedFile $document = null): Certification
    {
        $certification = new Certification($data);
        $certification->owner_id = $owner->id;
        $certification->certifiable()->associate($target);
        $certification->document_path = $document?->store('certifications', self::DISK);
        $certification->save();

        return $certification;
    }

    /**
     * Editing a certification sends it back to the admin for a new review.
     *
     * @param  array<string, mixed>  $data
     */
    public function resubmit(Certification $certification, array $data, ?UploadedFile $document = null): Certification
    {
        $certification->fill($data);

        if ($document) {
            $this->deleteDocument($certification);
            $certification->document_path = $document->store('certifications', self::DISK);
        }

        $certification->forceFill([
            'status' => CertificationStatus::PENDING,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ])->save();

        return $certification;
    }

    public function approve(Certification $certification, User $admin): void
    {
        $certification->forceFill([
            // A certificate already past its date can be recognised as genuine but not as valid.
            'status' => $certification->isPastExpiration() ? CertificationStatus::EXPIRED : CertificationStatus::VERIFIED,
            'rejection_reason' => null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit->log('certification.approved', $certification, $certification->name);
    }

    public function reject(Certification $certification, User $admin, string $reason): void
    {
        $certification->forceFill([
            'status' => CertificationStatus::REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit->log('certification.rejected', $certification, $certification->name, ['reason' => $reason]);
    }

    public function delete(Certification $certification): void
    {
        $this->deleteDocument($certification);
        $certification->delete();
    }

    /**
     * Mark verified certifications whose date has passed as expired. Saving each one
     * triggers CertificationObserver, which recomputes the scores of the affected lots.
     *
     * @return int Number of certifications expired.
     */
    public function expireOutdated(): int
    {
        $outdated = Certification::query()
            ->where('status', CertificationStatus::VERIFIED)
            ->whereDate('expiration_date', '<', today())
            ->get();

        $outdated->each(fn (Certification $certification) => $certification->forceFill(['status' => CertificationStatus::EXPIRED])->save());

        return $outdated->count();
    }

    private function deleteDocument(Certification $certification): void
    {
        if ($certification->document_path) {
            Storage::disk(self::DISK)->delete($certification->document_path);
        }
    }
}
