<?php

namespace Database\Seeders;

use App\Models\Certification;
use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use App\Services\CertificationService;
use App\Services\Scoring\FootprintCalculator;
use App\Services\Scoring\LotScoreManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Certifications in every status and environmental data declared by the actors.
 */
class SustainabilitySeeder extends Seeder
{
    private const DEMO_DOCUMENT = 'certifications/demo-certificat.pdf';

    public function __construct(
        private readonly CertificationService $certifications,
        private readonly FootprintCalculator $footprint,
        private readonly LotScoreManager $scores,
    ) {}

    public function run(): void
    {
        Storage::disk(CertificationService::DISK)->put(self::DEMO_DOCUMENT, $this->demoPdf());

        $admin = User::where('email', 'admin@nutritrace.test')->firstOrFail();
        $oil = $this->lotOf('Huile d\'olive extra vierge', '2025-11-22');

        $rows = [
            // [target, name, type, issuer, number, issued, expires, proof, decision, reason]
            [$this->product('Olives Chemlali'), 'Agriculture biologique', 'BIO', 'Ecocert', 'TN-BIO-2025-0412', '2025-03-01', '2027-02-28', true, 'approve', null],
            [$oil, 'Charte de l\'huilerie durable', 'SUSTAINABLE_AGRICULTURE', 'Office National de l\'Huile', 'ONH-2025-118', '2025-10-01', '2027-09-30', true, 'approve', null],
            [$this->product('Olives de table Meski'), 'Produit du terroir local', 'LOCAL', 'Groupement interprofessionnel des fruits', 'GIF-2025-077', '2025-09-15', '2027-09-14', true, 'approve', null],
            [$this->product('Fromage Sicilien de Béja'), 'Produit local de Béja', 'LOCAL', 'Groupement des fromagers de Béja', 'GFB-2026-021', '2026-01-10', '2028-01-09', true, 'approve', null],
            [$this->product('Tomates de plein champ'), 'Agriculture biologique', 'BIO', 'Certibio Méditerranée', 'CBM-0000', '2026-05-02', '2027-05-01', true, 'reject', 'Numéro de certificat inconnu de l\'organisme indiqué.'],
            // Past its date: approving it marks it as expired, not valid.
            [$this->product('Oranges Maltaises'), 'Agriculture durable', 'SUSTAINABLE_AGRICULTURE', 'INNORPI', 'INN-2023-554', '2023-01-10', '2026-01-09', true, 'approve', null],
            [$this->product('Miel de thym'), 'Agriculture biologique', 'BIO', 'Ecocert', 'TN-BIO-2026-0098', '2026-02-01', '2028-01-31', true, 'approve', null],
            [$this->product('Miel de romarin'), 'Agriculture biologique', 'BIO', 'Ecocert', 'TN-BIO-2026-0099', '2026-02-01', '2028-01-31', true, null, null],
            [$this->product('Lait cru de vache'), 'Commerce équitable', 'FAIR_TRADE', 'Fair for Life', null, '2026-06-01', null, false, null, null],
        ];

        foreach ($rows as [$target, $name, $type, $issuer, $number, $issued, $expires, $proof, $decision, $reason]) {
            $owner = $target instanceof Product ? $target->creator : User::findOrFail($target->transformation->transformer_id);

            $certification = $this->certifications->submit($owner, $target, [
                'name' => $name,
                'type' => $type,
                'issuing_organization' => $issuer,
                'certificate_number' => $number,
                'issue_date' => $issued,
                'expiration_date' => $expires,
            ]);

            if ($proof) {
                $certification->forceFill(['document_path' => self::DEMO_DOCUMENT])->save();
            }

            match ($decision) {
                'approve' => $this->certifications->approve($certification, $admin),
                'reject' => $this->certifications->reject($certification, $admin, $reason),
                default => null,
            };
        }

        // Figures the actors measured or provide themselves, on top of the calculated footprint.
        $this->declare($oil, [
            'waste_kg' => ['value' => 3200, 'source' => 'MEASURED'],
            'packaging_co2_kg' => ['value' => 95, 'source' => 'PROVIDED'],
        ]);

        $this->declare($this->lotOf('Fromage Sicilien de Béja', '2026-09-29'), [
            'packaging_co2_kg' => ['value' => 12, 'source' => 'PROVIDED'],
        ]);

        $this->declare($this->lotOf('Miel de thym', '2026-07-10'), [
            'waste_kg' => ['value' => 4, 'source' => 'MEASURED'],
            'packaging_co2_kg' => ['value' => 18, 'source' => 'PROVIDED'],
            'energy_kwh' => ['value' => 26.5, 'source' => 'MEASURED'],
        ]);
    }

    private function product(string $name): Product
    {
        return Product::with('creator')->where('name', $name)->firstOrFail();
    }

    private function lotOf(string $product, string $date): Lot
    {
        return Lot::with('transformation')
            ->whereDate('production_date', $date)
            ->whereHas('product', fn ($query) => $query->where('name', $product))
            ->firstOrFail();
    }

    /**
     * @param  array<string, array{value: float, source: string}>  $declared
     */
    private function declare(Lot $lot, array $declared): void
    {
        $maker = User::findOrFail($lot->transformation?->transformer_id ?? $lot->production->producer_id);

        $this->footprint->declare($lot, $maker, $declared);
        $this->scores->refresh($lot);
    }

    /**
     * A one-page PDF used as the proof of every demo certification.
     * Seeded certificates do not point to real documents.
     */
    private function demoPdf(): string
    {
        $text = 'BT /F1 20 Tf 60 760 Td (NutriTrace - certificat de demonstration) Tj ET'
            ."\nBT /F1 12 Tf 60 730 Td (Document fictif genere pour les donnees de demonstration.) Tj ET";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($text)." >>\nstream\n".$text."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
