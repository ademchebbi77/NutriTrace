<?php

namespace App\Console\Commands;

use App\Models\Lot;
use App\Services\Ai\LotAssistant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:warm {--lot= : Only this lot number}')]
#[Description('Answer the suggested questions of the AI assistant in advance, so that clicking them is instant')]
class WarmAiAnswers extends Command
{
    public function handle(LotAssistant $assistant): int
    {
        if (! $assistant->isAvailable()) {
            $this->components->error('The local AI service is not running: start ai/start.bat first.');

            return self::FAILURE;
        }

        $lots = Lot::publiclyVisible()
            ->when($this->option('lot'), fn ($query, $number) => $query->where('lot_number', $number))
            ->orderBy('id')
            ->get();
        $questions = __('ai.assistant.examples');

        $this->components->info("{$lots->count()} lots x ".count($questions).' questions');
        $failed = 0;

        $this->withProgressBar($lots, function (Lot $lot) use ($assistant, $questions, &$failed) {
            foreach ($questions as $question) {
                $failed += $assistant->ask($lot, $question) ? 0 : 1;
            }
        });

        $this->newLine();
        $failed ? $this->components->warn("{$failed} questions without an answer.") : $this->components->info('All answers are ready.');

        return self::SUCCESS;
    }
}
