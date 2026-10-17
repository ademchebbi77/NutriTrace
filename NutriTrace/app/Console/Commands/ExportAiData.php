<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\Transformation;
use App\Models\Transport;
use App\Models\User;
use App\Services\Ai\AiContext;
use App\Services\Ai\DistributionAiContext;
use App\Services\Ai\TransformationAiContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('ai:export {--output= : Folder to write to (default: ai/data/export)}')]
#[Description('Export the data of NutriTrace in the format of the local AI service, to generate its training data')]
class ExportAiData extends Command
{
    public function handle(AiContext $context, DistributionAiContext $distribution, TransformationAiContext $transformation): int
    {
        $folder = $this->option('output') ?: config('ai.export_path');
        File::ensureDirectoryExists($folder);

        $files = [
            // Transport - Traçabilité
            'lots.json' => Lot::with('product.category')->orderBy('id')->get()
                ->map(fn (Lot $lot) => $context->forLot($lot)),
            'transports.json' => Transport::with('lot')->orderBy('id')->get()
                ->map(fn (Transport $transport) => $context->forTransport($transport)),
            // Distribution - Certification
            'distributors.json' => User::where('role', UserRole::DISTRIBUTEUR)
                ->whereIn('id', Distribution::select('distributor_id'))
                ->orderBy('id')
                ->get()
                ->map(fn (User $user) => $distribution->forDistributor($user)),
            // Every hand-over to a distributor, as it looked before the decision.
            'receptions.json' => Distribution::with(['lot.product.category', 'sender', 'transport.lot'])->orderBy('id')->get()
                ->map(fn (Distribution $handover) => $distribution->forReception($handover)),
            'certifications.json' => Certification::with(['owner.organization', 'certifiable'])->orderBy('id')->get()
                ->map(fn (Certification $certification) => $distribution->forCertification($certification)),
            // Transformation - Lots
            'transformations.json' => Transformation::orderBy('id')->get()
                ->map(fn (Transformation $item) => $transformation->fromTransformation($item)),
            'lots_expiry.json' => Lot::with('product.category')->orderBy('id')->get()
                ->map(fn (Lot $lot) => $transformation->forLotExpiry($lot)),
        ];

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        foreach ($files as $name => $rows) {
            File::put($folder.'/'.$name, json_encode($rows, $flags));
        }

        $this->components->info('Exporté vers '.$folder.' : '.collect($files)->map(fn ($rows, $name) => $rows->count().' '.basename($name, '.json'))->join(', '));

        return self::SUCCESS;
    }
}
