<?php

namespace App\Console\Commands;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\Lot;
use App\Services\CertificationService;
use App\Support\SimplePdf;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('ai:demo-certificates {--all : Also replace documents uploaded by users}')]
#[Description('Give each demo certificate its own PDF proof, with the details of the certificate, so the local AI can read and check it')]
class MakeDemoCertificates extends Command
{
    /**
     * The single placeholder PDF the seeders attach to every demo certificate.
     */
    private const SHARED_DEMO = 'certifications/demo-certificat.pdf';

    public function handle(): int
    {
        $disk = Storage::disk(CertificationService::DISK);
        $certifications = Certification::with(['owner.organization', 'certifiable'])
            ->when(! $this->option('all'), fn ($query) => $query->where('document_path', self::SHARED_DEMO))
            ->get();

        foreach ($certifications as $certification) {
            $path = "certifications/demo/certificat-{$certification->id}.pdf";
            $disk->put($path, SimplePdf::make($this->lines($certification)));
            $certification->forceFill(['document_path' => $path])->save();
        }

        $this->components->info("{$certifications->count()} justificatif(s) PDF généré(s).");

        return self::SUCCESS;
    }

    /**
     * What a real certificate states. A certificate the admin refused gets a proof that does
     * not match the form (another number), as in the cases the demo data describes.
     *
     * @return list<array{0: int, 1: string}>
     */
    private function lines(Certification $certification): array
    {
        $number = $certification->certificate_number;
        if ($number && $certification->status === CertificationStatus::REJECTED) {
            $number = preg_replace_callback('/\d+$/', fn ($m) => str_pad((string) (((int) $m[0] + 4271) % 10 ** strlen($m[0])), strlen($m[0]), '0', STR_PAD_LEFT), $number);
        }

        $target = $certification->certifiable;
        $covers = $target instanceof Lot ? $target->product->name.' (lot '.$target->lot_number.')' : $target?->name;

        return array_values(array_filter([
            [18, $certification->issuing_organization],
            [14, 'CERTIFICAT DE CONFORMITÉ'],
            [11, ' '],
            $number ? [12, 'Certificat n° '.$number] : null,
            [12, 'Délivré à : '.($certification->owner->organization?->name ?? $certification->owner->displayName())],
            [12, 'Produit(s) couvert(s) : '.$covers],
            [12, 'Référentiel : '.$certification->name],
            [12, 'Date de délivrance : '.$certification->issue_date->format('d/m/Y')],
            $certification->expiration_date ? [12, 'Valable jusqu\'au : '.$certification->expiration_date->format('d/m/Y')] : null,
            [11, ' '],
            [10, 'Ce certificat atteste que les produits désignés sont conformes au référentiel indiqué.'],
            [9, 'Document de démonstration généré pour NutriTrace.'],
        ]));
    }
}
