<?php

namespace App\Console\Commands;

use App\Models\Lot;
use App\Models\Transport;
use App\Services\Ai\AiContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('ai:export {--output= : Folder to write to (default: ai/data/export)}')]
#[Description('Export the lots and transports in the format of the local AI service, to generate its training data')]
class ExportAiData extends Command
{
    public function handle(AiContext $context): int
    {
        $folder = $this->option('output') ?: base_path('ai/data/export');
        File::ensureDirectoryExists($folder);

        $lots = Lot::with('product.category')->orderBy('id')->get()
            ->map(fn (Lot $lot) => $context->forLot($lot));

        $transports = Transport::with('lot')->orderBy('id')->get()
            ->map(fn (Transport $transport) => $context->forTransport($transport));

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        File::put($folder.'/lots.json', json_encode($lots, $flags));
        File::put($folder.'/transports.json', json_encode($transports, $flags));

        $this->components->info("Lots exportés : {$lots->count()}, transports : {$transports->count()} -> {$folder}");

        return self::SUCCESS;
    }
}
